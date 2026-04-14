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
 * REST API controller for entry and exit page analytics.
 *
 * Endpoints:
 * - GET /wp-json/statnive/v1/pages/entry
 * - GET /wp-json/statnive/v1/pages/exit
 */
final class PagesDetailController extends WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'statnive/v1';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		$args = [
			'from'  => [
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => [ $this, 'validate_date' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'to'    => [
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => [ $this, 'validate_date' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'limit' => [
				'default'           => 20,
				'sanitize_callback' => 'absint',
			],
		];

		register_rest_route(
			$this->namespace,
			'/pages/entry',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_entry_pages' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $args,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/pages/exit',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_exit_pages' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $args,
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
	public function permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get entry pages for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_entry_pages( WP_REST_Request $request ): WP_REST_Response {
		return $this->get_pages( $request, 'entry' );
	}

	/**
	 * Get exit pages for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_exit_pages( WP_REST_Request $request ): WP_REST_Response {
		return $this->get_pages( $request, 'exit' );
	}

	/**
	 * Query entry or exit pages for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param string          $type    Page type: 'entry' or 'exit'.
	 * @return WP_REST_Response
	 */
	private function get_pages( WP_REST_Request $request, string $type ): WP_REST_Response {
		global $wpdb;

		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$limit = min( (int) $request->get_param( 'limit' ), 100 );

		$views         = TableRegistry::get( 'views' );
		$sessions      = TableRegistry::get( 'sessions' );
		$resource_uris = TableRegistry::get( 'resource_uris' );
		$resources     = TableRegistry::get( 'resources' );

		// Entry pages: first view in each session; Exit pages: last view.
		$order_direction = 'entry' === $type ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order_direction is ASC/DESC from validated allowlist, not an identifier.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ru.uri, res.cached_title AS title,
					COUNT(*) AS count,
					COUNT(DISTINCT v.session_id) AS visitors
				FROM (
					SELECT session_id, resource_uri_id,
						ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY viewed_at {$order_direction}) AS rn
					FROM %i
					WHERE viewed_at BETWEEN %s AND %s
				) v
				INNER JOIN %i ru ON v.resource_uri_id = ru.ID
				LEFT JOIN %i res ON ru.resource_id = res.resource_id
				WHERE v.rn = 1
				GROUP BY ru.uri, res.cached_title
				ORDER BY count DESC
				LIMIT %d",
				$views,
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$resource_uris,
				$resources,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return new WP_REST_Response( is_array( $rows ) ? $rows : [], 200 );
	}

	/**
	 * Validate a date string (YYYY-MM-DD).
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public function validate_date( $value ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}
}
