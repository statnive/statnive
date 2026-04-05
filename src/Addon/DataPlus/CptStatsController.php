<?php

declare(strict_types=1);

namespace Statnive\Addon\DataPlus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Database\TableRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for CPT analytics.
 *
 * Endpoint: GET /wp-json/statnive/v1/cpt-stats
 * Returns per-post-type visitor and view counts.
 */
final class CptStatsController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'cpt-stats';

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
	 * Permission check.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get CPT analytics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		global $wpdb;

		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$limit = min( (int) $request->get_param( 'limit' ), 100 );

		$views     = TableRegistry::get( 'views' );
		$sessions  = TableRegistry::get( 'sessions' );
		$resources = TableRegistry::get( 'resources' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.resource_type AS post_type,
					COUNT(DISTINCT s.visitor_id) AS visitors,
					COUNT(v.ID) AS views
				FROM `{$views}` v
				INNER JOIN `{$sessions}` s ON v.session_id = s.ID
				INNER JOIN `{$resources}` r ON v.resource_id = r.resource_id
				WHERE v.viewed_at BETWEEN %s AND %s
				GROUP BY r.resource_type
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
