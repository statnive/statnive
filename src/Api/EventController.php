<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Http\PayloadValidator;
use Statnive\Http\PayloadValidatorException;
use Statnive\Privacy\PrivacyManager;
use Statnive\Security\HmacValidator;
use Statnive\Service\EventService;
use Statnive\Service\IpExtractor;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for custom event tracking.
 *
 * Endpoint: POST /wp-json/statnive/v1/event
 * Accepts text/plain JSON body (same CORS bypass as HitController).
 */
final class EventController extends WP_REST_Controller {

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
	protected $rest_base = 'event';

	/**
	 * Allowed top-level payload keys.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_KEYS = [
		'event_name',
		'resource_type',
		'resource_id',
		'signature',
		'properties',
		'consent_granted',
	];

	/**
	 * Register the /event route.
	 *
	 * Schema-driven validation, same model as HitController. Public endpoint
	 * protected by HMAC signature + transient rate limiting + DNT/GPC.
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
	 * Argument schema for the /event route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_route_args(): array {
		return [
			'event_name'      => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resource_type'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resource_id'     => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'signature'       => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'properties'      => [
				'type' => 'object',
			],
			'consent_granted' => [
				'type' => 'boolean',
			],
		];
	}

	/**
	 * Handle incoming custom event request.
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

		$event_name    = sanitize_text_field( $data['event_name'] ?? '' );
		$resource_type = sanitize_text_field( $data['resource_type'] ?? '' );
		$resource_id   = absint( $data['resource_id'] ?? 0 );
		$signature     = sanitize_text_field( $data['signature'] ?? '' );
		$properties    = is_array( $data['properties'] ?? null ) ? $data['properties'] : [];

		if ( empty( $event_name ) || empty( $signature ) ) {
			return self::error_response( [ 'missing_fields', 'Required fields missing.', 400 ] );
		}

		// Validate HMAC signature.
		if ( ! HmacValidator::verify( $signature, $resource_type, $resource_id ) ) {
			return self::error_response( [ 'invalid_signature', 'Request signature is invalid.', 403 ] );
		}

		// Privacy enforcement.
		$consent_granted = ! empty( $data['consent_granted'] );
		$privacy_check   = PrivacyManager::check_request_privacy(
			[
				'HTTP_DNT'     => sanitize_text_field( wp_unslash( $_SERVER['HTTP_DNT'] ?? '' ) ),
				'HTTP_SEC_GPC' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_GPC'] ?? '' ) ),
			],
			$consent_granted
		);

		if ( ! $privacy_check->allowed ) {
			return new WP_REST_Response( null, 204 );
		}

		// Rate limiting (60 req/min per IP).
		// Key is salted SHA-256 of the raw IP — raw IP is never persisted.
		$ip_key = 'statnive_rate_' . hash( 'sha256', IpExtractor::extract() . wp_salt( 'auth' ) );
		$count  = (int) get_transient( $ip_key );
		if ( $count >= 60 ) {
			return self::error_response( [ 'rate_limited', 'Too many requests.', 429 ] );
		}
		set_transient( $ip_key, $count + 1, MINUTE_IN_SECONDS );

		// Record the event.
		EventService::record(
			$event_name,
			$properties,
			0, // session_id resolved later via enrichment.
			0, // resource_uri_id.
			get_current_user_id()
		);

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
