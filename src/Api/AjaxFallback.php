<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Entity\VisitorProfile;
use Statnive\Http\PayloadValidator;
use Statnive\Http\PayloadValidatorException;
use Statnive\Privacy\PrivacyManager;
use Statnive\Security\HmacValidator;
use Statnive\Service\IpExtractor;

/**
 * AJAX fallback endpoint for tracking hits.
 *
 * Used when the REST API is disabled by the host or blocked by ad blockers.
 * Processes the same payload as HitController via admin-ajax.php.
 */
final class AjaxFallback {

	/**
	 * Allowed top-level payload keys. Mirrors HitController::ALLOWED_KEYS.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_KEYS = [
		'resource_type',
		'resource_id',
		'referrer',
		'screen_width',
		'screen_height',
		'language',
		'timezone',
		'signature',
		'page_url',
		'page_query',
		'pvid',
		'consent_granted',
		'_statnonce',
	];

	/**
	 * Register AJAX action hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_statnive_hit', [ self::class, 'handle' ] );
		add_action( 'wp_ajax_nopriv_statnive_hit', [ self::class, 'handle' ] );
	}

	/**
	 * Handle the AJAX hit request.
	 *
	 * Reads the raw POST body (text/plain JSON), validates signature,
	 * and records the hit via the same pipeline as the REST endpoint.
	 *
	 * Every short-circuit path ends with an explicit `return;` — do not
	 * rely on `wp_send_json_*` exiting. Hosts and security plugins may
	 * filter `wp_die_handler`, and unit tests stub the helpers to throw
	 * instead of exit; an explicit return guarantees execution stops.
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tracker uses HMAC, not nonces.
		$body = file_get_contents( 'php://input' );

		if ( false === $body ) {
			self::reject( 'invalid_body', 'Unable to read request body.', 400 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same HMAC rationale.
		$raw_ct = isset( $_SERVER['CONTENT_TYPE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) )
			: '';

		$ct_error = PayloadValidator::validate_content_type_string( $raw_ct );
		if ( null !== $ct_error ) {
			self::reject( $ct_error[0], $ct_error[1], $ct_error[2] );
			return;
		}

		$size_error = PayloadValidator::validate_body_size( $body );
		if ( null !== $size_error ) {
			self::reject( $size_error[0], $size_error[1], $size_error[2] );
			return;
		}

		try {
			$data = PayloadValidator::decode_json_object( $body );
		} catch ( PayloadValidatorException $e ) {
			self::reject( $e->get_error_code(), $e->getMessage(), $e->get_status_code() );
			return;
		}

		$keys_error = PayloadValidator::validate_allowed_keys( $data, self::ALLOWED_KEYS );
		if ( null !== $keys_error ) {
			self::reject( $keys_error[0], $keys_error[1], $keys_error[2] );
			return;
		}

		// Validate page_url host against site origin.
		if ( ! empty( $data['page_url'] ) && ! HitController::validate_page_url_host( (string) $data['page_url'] ) ) {
			self::reject( 'invalid_host', 'page_url host does not match this site.', 400 );
			return;
		}

		$resource_type = sanitize_text_field( $data['resource_type'] ?? '' );
		$resource_id   = absint( $data['resource_id'] ?? 0 );
		$signature     = sanitize_text_field( $data['signature'] ?? '' );

		if ( empty( $resource_type ) || empty( $signature ) ) {
			self::reject( 'missing_fields', 'Required fields missing.', 400 );
			return;
		}

		// Validate HMAC signature.
		if ( ! HmacValidator::verify( $signature, $resource_type, $resource_id ) ) {
			self::reject( 'invalid_signature', 'Request signature is invalid.', 403 );
			return;
		}

		// CSRF nonce — hardening layer alongside HMAC (Checklist §7).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce IS verified via PayloadValidator::validate_nonce(); ignore suppresses the check_ajax_referer suggestion.
		$nonce_error = PayloadValidator::validate_nonce( $data );
		if ( null !== $nonce_error ) {
			self::reject( $nonce_error[0], $nonce_error[1], $nonce_error[2] );
			return;
		}

		// Privacy enforcement: check consent mode, DNT, GPC headers.
		$ip              = IpExtractor::extract();
		$consent_granted = ! empty( $data['consent_granted'] );
		$privacy_check   = PrivacyManager::check_request_privacy(
			[
				'HTTP_DNT'     => sanitize_text_field( wp_unslash( $_SERVER['HTTP_DNT'] ?? '' ) ),
				'HTTP_SEC_GPC' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_GPC'] ?? '' ) ),
			],
			$consent_granted,
			$ip
		);

		if ( ! $privacy_check->allowed() ) {
			wp_send_json_success( null, 204 );
			// Defensive: wp_send_json_* normally calls wp_die(), but hosts
			// and security plugins can filter wp_die_handler. The explicit
			// return guarantees we never fall through to the persistence
			// path on a privacy-blocked request. Do not remove.
			return; // @phpstan-ignore-line deadCode.unreachable
		}

		// Rate limiting (60 req/min per IP, matching HitController).
		$ip_key = 'statnive_rate_' . hash( 'sha256', $ip . wp_salt( 'auth' ) );
		$count  = (int) get_transient( $ip_key );
		if ( $count >= 60 ) {
			self::reject( 'rate_limited', 'Too many requests.', 429 );
			return;
		}
		set_transient( $ip_key, $count + 1, MINUTE_IN_SECONDS );

		// Circuit-breaker: stop writes if too many recent failures (§28.3.2).
		if ( HitController::is_circuit_open() ) {
			self::reject( 'circuit_open', 'Tracking temporarily paused due to repeated errors.', 503 );
			return;
		}

		// Create VisitorProfile, enrich with services, and persist.
		$profile = VisitorProfile::from_request( $data );
		$profile->enrich();

		wp_send_json_success( null, 204 );
	}

	/**
	 * Send a structured JSON error and stop processing.
	 *
	 * @param string $code        Machine-readable error code.
	 * @param string $message     Human-readable error message.
	 * @param int    $status_code HTTP status code.
	 */
	private static function reject( string $code, string $message, int $status_code ): void {
		wp_send_json_error(
			[
				'code'    => $code,
				'message' => $message,
			],
			$status_code
		);
	}
}
