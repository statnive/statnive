<?php

declare(strict_types=1);

namespace Statnive\Addon\Marketing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for campaign management.
 *
 * Endpoints:
 * - GET   /wp-json/statnive/v1/campaigns   — list campaigns
 * - POST  /wp-json/statnive/v1/campaigns   — create a campaign with UTM URL
 */
final class CampaignsController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'campaigns';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Permission check.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List campaigns.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$campaigns = get_option( 'statnive_campaigns', [] );
		return new WP_REST_Response( is_array( $campaigns ) ? $campaigns : [], 200 );
	}

	/**
	 * Create a campaign with UTM URL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ): WP_REST_Response {
		$body = $request->get_json_params();

		$campaign = [
			'id'           => wp_generate_uuid4(),
			'name'         => sanitize_text_field( $body['name'] ?? '' ),
			'utm_source'   => sanitize_text_field( $body['utm_source'] ?? '' ),
			'utm_medium'   => sanitize_text_field( $body['utm_medium'] ?? '' ),
			'utm_campaign' => sanitize_text_field( $body['utm_campaign'] ?? '' ),
			'target_url'   => esc_url_raw( $body['target_url'] ?? home_url() ),
			'created_at'   => current_time( 'mysql', true ),
		];

		if ( empty( $campaign['name'] ) ) {
			return new WP_REST_Response( [ 'message' => 'Campaign name is required.' ], 400 );
		}

		// Build UTM URL.
		$campaign['utm_url'] = add_query_arg(
			array_filter(
				[
					'utm_source'   => $campaign['utm_source'],
					'utm_medium'   => $campaign['utm_medium'],
					'utm_campaign' => $campaign['utm_campaign'],
				]
			),
			$campaign['target_url']
		);

		$campaigns   = get_option( 'statnive_campaigns', [] );
		$campaigns[] = $campaign;
		update_option( 'statnive_campaigns', $campaigns, false );

		return new WP_REST_Response( $campaign, 201 );
	}
}
