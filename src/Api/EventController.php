<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * Register the /event route.
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
				],
			]
		);
	}

	/**
	 * Handle incoming custom event request.
	 *
	 * @param WP_REST_Request $request Full request object.
	 * @return WP_REST_Response Response object.
	 */
	public function create_item( $request ): WP_REST_Response {
		$body = $request->get_body();
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_payload',
					'message' => 'Invalid request body.',
				],
				400
			);
		}

		$event_name    = sanitize_text_field( $data['event_name'] ?? '' );
		$resource_type = sanitize_text_field( $data['resource_type'] ?? '' );
		$resource_id   = absint( $data['resource_id'] ?? 0 );
		$signature     = sanitize_text_field( $data['signature'] ?? '' );
		$properties    = is_array( $data['properties'] ?? null ) ? $data['properties'] : [];

		if ( empty( $event_name ) || empty( $signature ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'missing_fields',
					'message' => 'Required fields missing.',
				],
				400
			);
		}

		// Validate HMAC signature.
		if ( ! HmacValidator::verify( $signature, $resource_type, $resource_id ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'invalid_signature',
					'message' => 'Request signature is invalid.',
				],
				403
			);
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
		$ip_key = 'statnive_rate_' . md5( IpExtractor::extract() );
		$count  = (int) get_transient( $ip_key );
		if ( $count >= 60 ) {
			return new WP_REST_Response(
				[
					'code'    => 'rate_limited',
					'message' => 'Too many requests.',
				],
				429
			);
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
}
