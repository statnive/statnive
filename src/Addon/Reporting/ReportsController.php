<?php

declare(strict_types=1);

namespace Statnive\Addon\Reporting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Database\TableRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for saved reports.
 *
 * Endpoints:
 * - GET    /wp-json/statnive/v1/reports       — list saved reports
 * - POST   /wp-json/statnive/v1/reports       — create a new report
 * - DELETE  /wp-json/statnive/v1/reports/{id}  — delete a report
 */
final class ReportsController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'reports';

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
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
	 * List saved reports.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		global $wpdb;
		$table = TableRegistry::get( 'reports' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT ID, name, filters, created_at FROM `{$table}` ORDER BY created_at DESC LIMIT 50",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return new WP_REST_Response( is_array( $rows ) ? $rows : [], 200 );
	}

	/**
	 * Create a saved report.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ): WP_REST_Response {
		global $wpdb;
		$table = TableRegistry::get( 'reports' );

		$body = $request->get_json_params();
		$name = sanitize_text_field( $body['name'] ?? '' );

		if ( empty( $name ) ) {
			return new WP_REST_Response( [ 'message' => 'Report name is required.' ], 400 );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'name'       => $name,
				'filters'    => wp_json_encode( $body['filters'] ?? [] ),
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%s' ]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return new WP_REST_Response(
			[
				'id'   => (int) $wpdb->insert_id,
				'name' => $name,
			],
			201
		);
	}

	/**
	 * Delete a saved report.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ): WP_REST_Response {
		global $wpdb;
		$table = TableRegistry::get( 'reports' );
		$id    = absint( $request->get_param( 'id' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, [ 'ID' => $id ], [ '%d' ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return new WP_REST_Response( null, 204 );
	}
}
