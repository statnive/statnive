<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Entity\VisitorProfile;
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
	 * Register the /hit route.
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
	 * Handle incoming hit request.
	 *
	 * @param WP_REST_Request $request Full request object.
	 * @return WP_REST_Response Response object.
	 */
	public function create_item( $request ): WP_REST_Response {
		// Parse the body — may come as text/plain JSON.
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

		// Validate required fields.
		$resource_type = sanitize_text_field( $data['resource_type'] ?? '' );
		$resource_id   = absint( $data['resource_id'] ?? 0 );
		$signature     = sanitize_text_field( $data['signature'] ?? '' );

		if ( empty( $resource_type ) || empty( $signature ) ) {
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
			// Silent drop — return 204 so tracker doesn't retry.
			return new WP_REST_Response( null, 204 );
		}

		// Basic rate limiting via transient (60 req/min per IP).
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

		// Create VisitorProfile, enrich with services, and persist.
		$profile = VisitorProfile::from_request( $data );
		$profile->enrich();

		// Invalidate realtime cache so new data appears immediately.
		delete_transient( 'statnive_realtime' );

		return new WP_REST_Response( null, 204 );
	}
}
