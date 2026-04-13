<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Api\Concerns\CachesResponses;
use Statnive\Database\TableRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for traffic source attribution.
 *
 * Endpoint: GET /wp-json/statnive/v1/sources
 * Returns channel-grouped traffic data with visitor and session counts.
 */
final class SourcesController extends WP_REST_Controller {

	use CachesResponses;

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
	protected $rest_base = 'sources';

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
						'group_by'    => [
							'default'           => '',
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'enum'              => [ '', 'channel' ],
						],
						'per_channel' => [
							'default'           => 10,
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
	 * Get traffic sources for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$from      = $request->get_param( 'from' );
		$to        = $request->get_param( 'to' );
		$limit     = min( (int) $request->get_param( 'limit' ), 100 );
		$group_by  = $request->get_param( 'group_by' );

		if ( 'channel' === $group_by ) {
			return $this->get_items_grouped_by_channel( $request );
		}

		$params = [
			'from'  => $from,
			'to'    => $to,
			'limit' => $limit,
		];

		$cached = $this->get_cached_response( 'sources', $params );
		if ( null !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		global $wpdb;

		$sessions  = TableRegistry::get( 'sessions' );
		$referrers = TableRegistry::get( 'referrers' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(r.channel, 'Direct') AS channel, COALESCE(r.name, '') AS name, COALESCE(r.domain, '') AS domain,
					COUNT(DISTINCT s.visitor_id) AS visitors,
					COUNT(DISTINCT s.ID) AS sessions,
					SUM(s.total_views) AS views
				FROM `{$sessions}` s
				LEFT JOIN `{$referrers}` r ON s.referrer_id = r.ID
				WHERE s.started_at BETWEEN %s AND %s
				GROUP BY r.channel, r.name, r.domain
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

		$result = is_array( $rows ) ? $rows : [];
		$this->set_cached_response( 'sources', $params, $result, $from, $to );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get sources grouped by channel with accurate per-channel totals.
	 *
	 * Runs two queries:
	 * 1. Channel-level aggregates (accurate COUNT(DISTINCT) totals).
	 * 2. Per-source detail rows, sliced to top N per channel in PHP
	 *    (MySQL 5.7 compatible — no window functions).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	private function get_items_grouped_by_channel( WP_REST_Request $request ): WP_REST_Response {
		$from        = $request->get_param( 'from' );
		$to          = $request->get_param( 'to' );
		$per_channel = min( (int) $request->get_param( 'per_channel' ), 50 );
		$params      = [
			'from'        => $from,
			'to'          => $to,
			'group_by'    => 'channel',
			'per_channel' => $per_channel,
		];

		$cached = $this->get_cached_response( 'sources', $params );
		if ( null !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		global $wpdb;

		$sessions  = TableRegistry::get( 'sessions' );
		$referrers = TableRegistry::get( 'referrers' );
		$start     = $from . ' 00:00:00';
		$end       = $to . ' 23:59:59';

		// Query 1: Channel-level aggregates with accurate COUNT(DISTINCT).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$channel_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(r.channel, 'Direct') AS channel,
					COUNT(DISTINCT s.visitor_id) AS visitors,
					COUNT(DISTINCT s.ID) AS sessions,
					SUM(s.total_views) AS views
				FROM `{$sessions}` s
				LEFT JOIN `{$referrers}` r ON s.referrer_id = r.ID
				WHERE s.started_at BETWEEN %s AND %s
				GROUP BY r.channel
				ORDER BY visitors DESC",
				$start,
				$end
			),
			ARRAY_A
		);

		// Query 2: Per-source detail rows ordered by channel then visitors.
		$source_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(r.channel, 'Direct') AS channel,
					COALESCE(r.name, '') AS name, COALESCE(r.domain, '') AS domain,
					COUNT(DISTINCT s.visitor_id) AS visitors,
					COUNT(DISTINCT s.ID) AS sessions,
					SUM(s.total_views) AS views
				FROM `{$sessions}` s
				LEFT JOIN `{$referrers}` r ON s.referrer_id = r.ID
				WHERE s.started_at BETWEEN %s AND %s
				GROUP BY r.channel, r.name, r.domain
				ORDER BY r.channel, visitors DESC
				LIMIT 500",
				$start,
				$end
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$result = self::merge_channel_groups(
			is_array( $channel_rows ) ? $channel_rows : [],
			is_array( $source_rows ) ? $source_rows : [],
			$per_channel
		);

		$this->set_cached_response( 'sources', $params, $result, $from, $to );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Merge channel summaries with per-channel top-N source rows.
	 *
	 * @param array<int, array<string, mixed>> $channel_rows Channel aggregates.
	 * @param array<int, array<string, mixed>> $source_rows  Per-source detail rows.
	 * @param int                              $per_channel  Max sources per channel.
	 * @return array<int, array<string, mixed>> Grouped result.
	 */
	private static function merge_channel_groups( array $channel_rows, array $source_rows, int $per_channel ): array {
		// Index sources by channel, take top N per group.
		$sources_by_channel = [];
		foreach ( $source_rows as $row ) {
			$ch = $row['channel'];
			if ( ! isset( $sources_by_channel[ $ch ] ) ) {
				$sources_by_channel[ $ch ] = [];
			}
			if ( count( $sources_by_channel[ $ch ] ) < $per_channel ) {
				$sources_by_channel[ $ch ][] = [
					'name'     => $row['name'],
					'domain'   => $row['domain'],
					'visitors' => (int) $row['visitors'],
					'sessions' => (int) $row['sessions'],
					'views'    => (int) $row['views'],
				];
			}
		}

		$result = [];
		foreach ( $channel_rows as $ch_row ) {
			$channel = $ch_row['channel'];
			$result[] = [
				'channel'  => $channel,
				'visitors' => (int) $ch_row['visitors'],
				'sessions' => (int) $ch_row['sessions'],
				'views'    => (int) $ch_row['views'],
				'sources'  => $sources_by_channel[ $channel ] ?? [],
			];
		}

		return $result;
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
