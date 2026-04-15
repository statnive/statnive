<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Api\Concerns\CachesResponses;
use Statnive\Api\Concerns\ValidatesDateRange;
use Statnive\Database\TableRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for page-level analytics.
 *
 * Endpoint: GET /wp-json/statnive/v1/pages
 * Returns per-page metrics sorted by visitors (not pageviews — vanity metric rejection).
 */
final class PagesController extends WP_REST_Controller {

	use CachesResponses;
	use ValidatesDateRange;

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
	protected $rest_base = 'pages';

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
						'from'   => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => [ $this, 'validate_date' ],
							'sanitize_callback' => 'sanitize_text_field',
						],
						'to'     => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => [ $this, 'validate_date' ],
							'sanitize_callback' => 'sanitize_text_field',
						],
						'limit'  => [
							'default'           => 20,
							'sanitize_callback' => 'absint',
						],
						'offset' => [
							'default'           => 0,
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
	public function get_items_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get page analytics for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$from   = $request->get_param( 'from' );
		$to     = $request->get_param( 'to' );
		$limit  = min( (int) $request->get_param( 'limit' ), 100 );
		$offset = (int) $request->get_param( 'offset' );
		$params = [
			'from'   => $from,
			'to'     => $to,
			'limit'  => $limit,
			'offset' => $offset,
		];

		$cached = $this->get_cached_response( 'pages', $params );
		if ( null !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		global $wpdb;

		$summary       = TableRegistry::get( 'summary' );
		$resource_uris = TableRegistry::get( 'resource_uris' );
		$resources     = TableRegistry::get( 'resources' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ru.uri, res.cached_title AS title,
					SUM(sm.visitors) AS visitors,
					SUM(sm.views) AS views,
					SUM(sm.total_duration) AS total_duration,
					SUM(sm.bounces) AS bounces
				FROM %i sm
				INNER JOIN %i ru ON sm.resource_uri_id = ru.ID
				LEFT JOIN %i res ON ru.resource_id = res.resource_id
				WHERE sm.date BETWEEN %s AND %s
				GROUP BY ru.uri, res.cached_title
				ORDER BY visitors DESC
				LIMIT %d OFFSET %d',
				$summary,
				$resource_uris,
				$resources,
				$from,
				$to,
				$limit,
				$offset
			),
			ARRAY_A
		);
		// Real-time fallback: query raw tables for today's page data.
		$today = gmdate( 'Y-m-d' );
		if ( $from <= $today && $to >= $today ) {
			$views_table    = TableRegistry::get( 'views' );
			$sessions_table = TableRegistry::get( 'sessions' );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$today_rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ru.uri, res.cached_title AS title,
						COUNT(DISTINCT s.visitor_id) AS visitors,
						COUNT(v.ID) AS views,
						COALESCE(SUM(s.duration), 0) AS total_duration,
						SUM(CASE WHEN s.total_views = 1 THEN 1 ELSE 0 END) AS bounces
					FROM %i v
					INNER JOIN %i s ON v.session_id = s.ID
					INNER JOIN %i ru ON v.resource_uri_id = ru.ID
					LEFT JOIN %i res ON ru.resource_id = res.resource_id
					WHERE DATE(v.viewed_at) = %s
					GROUP BY ru.uri, res.cached_title
					ORDER BY visitors DESC
					LIMIT %d',
					$views_table,
					$sessions_table,
					$resource_uris,
					$resources,
					$today,
					$limit
				),
				ARRAY_A
			);

			if ( is_array( $today_rows ) && ! empty( $today_rows ) ) {
				if ( ! is_array( $rows ) ) {
					$rows = [];
				}
				// Replace stale aggregated rows with fresh real-time data for today's URIs.
				$today_uris = array_column( $today_rows, 'uri' );
				$rows       = array_filter( $rows, fn( $r ) => ! in_array( $r['uri'], $today_uris, true ) );
				$rows       = array_merge( array_values( $rows ), $today_rows );
				// Re-sort by visitors DESC.
				usort( $rows, fn( $a, $b ) => (int) $b['visitors'] - (int) $a['visitors'] );
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$result = is_array( $rows ) ? $rows : [];
		$this->set_cached_response( 'pages', $params, $result, $from, $to );

		return new WP_REST_Response( $result, 200 );
	}
}
