<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Database\TableRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for UTM campaign data.
 *
 * Endpoint: GET /wp-json/statnive/v1/utm
 */
final class UtmController extends WP_REST_Controller {

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
	protected $rest_base = 'utm';

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
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => [
						'from'  => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'to'    => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'limit' => [
							'default'           => 20,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check — requires manage_options.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get UTM campaign data for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		global $wpdb;

		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$limit = min( (int) $request->get_param( 'limit' ), 100 );

		$sessions   = TableRegistry::get( 'sessions' );
		$parameters = TableRegistry::get( 'parameters' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					MAX(CASE WHEN p.param_key = 'utm_campaign' THEN p.param_value END) AS campaign,
					MAX(CASE WHEN p.param_key = 'utm_source' THEN p.param_value END) AS source,
					MAX(CASE WHEN p.param_key = 'utm_medium' THEN p.param_value END) AS medium,
					COUNT(DISTINCT s.visitor_id) AS visitors,
					COUNT(DISTINCT s.ID) AS sessions
				FROM `{$parameters}` p
				INNER JOIN `{$sessions}` s ON p.session_id = s.ID
				WHERE p.param_key IN ('utm_campaign', 'utm_source', 'utm_medium')
				AND s.started_at BETWEEN %s AND %s
				GROUP BY p.session_id
				ORDER BY visitors DESC
				LIMIT %d",
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return new WP_REST_Response( is_array( $rows ) ? $rows : [], 200 );
	}
}
