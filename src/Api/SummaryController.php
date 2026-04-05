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
 * REST API controller for site-wide summary metrics.
 *
 * Endpoint: GET /wp-json/statnive/v1/summary
 * Returns visitors, sessions, views, bounces for a date range.
 */
final class SummaryController extends WP_REST_Controller {

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
	protected $rest_base = 'summary';

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
						'from' => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => [ $this, 'validate_date' ],
							'sanitize_callback' => 'sanitize_text_field',
						],
						'to'   => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => [ $this, 'validate_date' ],
							'sanitize_callback' => 'sanitize_text_field',
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
	 * Get summary metrics for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		global $wpdb;

		$from = $request->get_param( 'from' );
		$to   = $request->get_param( 'to' );

		$table = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date, visitors, sessions, views, total_duration, bounces FROM `{$table}` WHERE date BETWEEN %s AND %s ORDER BY date ASC",
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Real-time fallback: query raw tables for today's live data.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$today = gmdate( 'Y-m-d' );
		if ( $from <= $today && $to >= $today ) {
			$sessions_table = TableRegistry::get( 'sessions' );
			$views_table    = TableRegistry::get( 'views' );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$today_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(DISTINCT s.visitor_id) AS visitors,
						COUNT(DISTINCT s.ID) AS sessions,
						COUNT(v.ID) AS views,
						COALESCE(SUM(v.duration), 0) AS total_duration,
						SUM(CASE WHEN s.total_views = 1 THEN 1 ELSE 0 END) AS bounces
					FROM `{$sessions_table}` s
					LEFT JOIN `{$views_table}` v ON v.session_id = s.ID AND DATE(v.viewed_at) = %s
					WHERE DATE(s.started_at) = %s",
					$today,
					$today
				),
				ARRAY_A
			);

			if ( is_array( $today_row ) ) {
				$today_row['date'] = $today;
				if ( ! is_array( $rows ) ) {
					$rows = [];
				}
				// Remove any stale aggregated row for today to prevent duplication.
				$rows = array_values( array_filter( $rows, fn( $r ) => ( $r['date'] ?? '' ) !== $today ) );
				$rows[] = $today_row;
				// Re-sort by date.
				usort( $rows, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Calculate totals.
		$totals = [
			'visitors'       => 0,
			'sessions'       => 0,
			'views'          => 0,
			'total_duration' => 0,
			'bounces'        => 0,
		];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$totals['visitors']       += (int) $row['visitors'];
				$totals['sessions']       += (int) $row['sessions'];
				$totals['views']          += (int) $row['views'];
				$totals['total_duration'] += (int) $row['total_duration'];
				$totals['bounces']        += (int) $row['bounces'];
			}
		}

		return new WP_REST_Response(
			[
				'totals' => $totals,
				'daily'  => is_array( $rows ) ? $rows : [],
			],
			200
		);
	}

	/**
	 * Validate a date parameter.
	 *
	 * @param string $value Date string.
	 * @return bool
	 */
	public function validate_date( $value ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}
}
