<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Entity\VisitorProfile;
use Statnive\Privacy\PrivacyManager;
use Statnive\Security\HmacValidator;

/**
 * AJAX fallback endpoint for tracking hits.
 *
 * Used when the REST API is disabled by the host or blocked by ad blockers.
 * Processes the same payload as HitController via admin-ajax.php.
 */
final class AjaxFallback {

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
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tracker uses HMAC, not nonces.
		$body = file_get_contents( 'php://input' );

		if ( false === $body ) {
			wp_send_json_error( [ 'message' => 'Unable to read request body.' ], 400 );
			return;
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => 'Invalid request body.' ], 400 );
			return;
		}

		$resource_type = sanitize_text_field( $data['resource_type'] ?? '' );
		$resource_id   = absint( $data['resource_id'] ?? 0 );
		$signature     = sanitize_text_field( $data['signature'] ?? '' );

		if ( empty( $resource_type ) || empty( $signature ) ) {
			wp_send_json_error( [ 'message' => 'Required fields missing.' ], 400 );
			return;
		}

		// Validate HMAC signature.
		if ( ! HmacValidator::verify( $signature, $resource_type, $resource_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid signature.' ], 403 );
		}

		// Privacy enforcement: check consent mode, DNT, GPC headers.
		$consent_granted = ! empty( $data['consent_granted'] );
		$privacy_check   = PrivacyManager::check_request_privacy(
			[
				'HTTP_DNT'     => sanitize_text_field( wp_unslash( $_SERVER['HTTP_DNT'] ?? '' ) ),
				'HTTP_SEC_GPC' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_GPC'] ?? '' ) ),
			],
			$consent_granted
		);

		if ( ! $privacy_check->allowed ) {
			wp_send_json_success( null, 204 );
			return;
		}

		// Create VisitorProfile, enrich with services, and persist.
		$profile = VisitorProfile::from_request( $data );
		$profile->enrich();

		wp_send_json_success( null, 204 );
	}
}
