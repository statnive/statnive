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
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for tracking hits.
 *
 * Endpoint: POST /wp-json/statnive/v1/hit
 * Accepts text/plain JSON body to avoid CORS preflight (P-12).
 */
final class HitController extends WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'statnive/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'hit';

	/**
	 * Allowed top-level payload keys. Unknown keys are rejected.
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
	];

	/**
	 * Register the /hit route.
	 *
	 * Schema-driven validation lets WordPress core run sanitize/validate
	 * callbacks for every accepted field before our handler executes.
	 * The endpoint is intentionally public (`__return_true`); HMAC signature
	 * verification + transient rate limiting + DNT/GPC headers provide the
	 * security boundary.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => '__return_true',
					'args'                => self::get_route_args(),
				],
			]
		);
	}

	/**
	 * Argument schema for the /hit route.
	 *
	 * Note: the tracker sends `Content-Type: text/plain` to avoid the CORS
	 * preflight, so the JSON body is parsed manually inside `create_item()`
	 * via {@see PayloadValidator}. The schema below is therefore primarily
	 * documentation + a hint to Plugin Check / OpenAPI scanners that every
	 * field has a declared type and sanitizer. Runtime sanitization still
	 * happens in `create_item()`.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_route_args(): array {
		// IMPORTANT: do NOT mark any of these args as `required` => true.
		// The tracker sends its JSON payload with Content-Type: text/plain to
		// bypass the CORS preflight, and WordPress's REST framework cannot
		// parse text/plain bodies into request args. A `required` flag here
		// would cause the framework to reject every real tracker hit with
		// `rest_missing_callback_param` BEFORE create_item() runs. The schema
		// stays for documentation / Plugin Check / OpenAPI scanners; runtime
		// validation of resource_type / signature happens inside create_item()
		// via PayloadValidator and the explicit empty checks below.
		return [
			'resource_type'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resource_id'     => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'referrer'        => [
				'type'              => 'string',
				'format'            => 'uri',
				'sanitize_callback' => 'esc_url_raw',
			],
			'screen_width'    => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'screen_height'   => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'language'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'timezone'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'signature'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'page_url'        => [
				'type'              => 'string',
				'format'            => 'uri',
				'sanitize_callback' => 'esc_url_raw',
			],
			'page_query'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'pvid'            => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'consent_granted' => [
				'type' => 'boolean',
			],
		];
	}

	/**
	 * Handle incoming hit request.
	 *
	 * @param WP_REST_Request $request Full request object.
	 * @return WP_REST_Response Response object.
	 */
	public function create_item( $request ): WP_REST_Response {
		$ct_error = PayloadValidator::validate_content_type( $request );
		if ( null !== $ct_error ) {
			return self::error_response( $ct_error );
		}

		$body = $request->get_body();

		$size_error = PayloadValidator::validate_body_size( $body );
		if ( null !== $size_error ) {
			return self::error_response( $size_error );
		}

		try {
			$data = PayloadValidator::decode_json_object( $body );
		} catch ( PayloadValidatorException $e ) {
			return self::error_response( $e->to_tuple() );
		}

		$keys_error = PayloadValidator::validate_allowed_keys( $data, self::ALLOWED_KEYS );
		if ( null !== $keys_error ) {
			return self::error_response( $keys_error );
		}

		// Validate required fields.
		$resource_type = sanitize_text_field( $data['resource_type'] ?? '' );
		$resource_id   = absint( $data['resource_id'] ?? 0 );
		$signature     = sanitize_text_field( $data['signature'] ?? '' );

		if ( empty( $resource_type ) || empty( $signature ) ) {
			return self::error_response( [ 'missing_fields', 'Required fields missing.', 400 ] );
		}

		// Validate HMAC signature.
		if ( ! HmacValidator::verify( $signature, $resource_type, $resource_id ) ) {
			return self::error_response( [ 'invalid_signature', 'Request signature is invalid.', 403 ] );
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

		if ( ! $privacy_check->allowed() ) {
			// Silent drop — return 204 so tracker doesn't retry.
			return new WP_REST_Response( null, 204 );
		}

		// Basic rate limiting via transient (60 req/min per IP).
		// Key is salted SHA-256 of the raw IP — raw IP is never persisted.
		$ip     = IpExtractor::extract();
		$ip_key = 'statnive_rate_' . hash( 'sha256', $ip . wp_salt( 'auth' ) );
		$count  = (int) get_transient( $ip_key );
		if ( $count >= 60 ) {
			return self::error_response( [ 'rate_limited', 'Too many requests.', 429 ] );
		}
		set_transient( $ip_key, $count + 1, MINUTE_IN_SECONDS );

		// Create VisitorProfile, enrich with services, and persist.
		$profile = VisitorProfile::from_request( $data );
		$profile->enrich();

		// Invalidate realtime cache so new data appears immediately.
		delete_transient( 'statnive_realtime' );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Translate an error tuple into a WP_REST_Response.
	 *
	 * Increments the `statnive_failed_requests` counter so the diagnostics
	 * export (§29) and admin observability (§28.3.1) can surface the number
	 * of dropped tracking events.
	 *
	 * @param array{0: string, 1: string, 2: int} $tuple [code, message, status].
	 * @return WP_REST_Response
	 */
	private static function error_response( array $tuple ): WP_REST_Response {
		// Don't count rate-limit + privacy soft-blocks as "errors" — those are
		// expected, not failures. Only count code/4xx/5xx that signal a broken
		// request the operator should know about.
		if ( $tuple[2] >= 400 && 429 !== $tuple[2] ) {
			update_option( 'statnive_failed_requests', (int) get_option( 'statnive_failed_requests', 0 ) + 1, false );
		}

		return new WP_REST_Response(
			[
				'code'    => $tuple[0],
				'message' => $tuple[1],
			],
			$tuple[2]
		);
	}
}
