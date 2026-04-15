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
 * REST API controller for UTM campaign data.
 *
 * Endpoint: GET /wp-json/statnive/v1/utm
 */
final class UtmController extends WP_REST_Controller {

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
		$from   = $request->get_param( 'from' );
		$to     = $request->get_param( 'to' );
		$limit  = min( (int) $request->get_param( 'limit' ), 100 );
		$params = [
			'from'  => $from,
			'to'    => $to,
			'limit' => $limit,
		];

		$cached = $this->get_cached_response( 'utm', $params );
		if ( null !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		global $wpdb;

		$sessions   = TableRegistry::get( 'sessions' );
		$parameters = TableRegistry::get( 'parameters' );

		/*
		 * Two-stage aggregation:
		 * 1. Inner subquery pivots the per-key parameter rows into one row per
		 *    session with `campaign` / `source` / `medium` columns.
		 * 2. Outer query groups by the (campaign, source, medium) tuple and
		 *    counts distinct visitors and sessions across every session that
		 *    shares the tuple — i.e. one row per campaign, not per session.
		 *
		 * `utm_term` and `utm_content` are intentionally excluded — they are
		 * drill-down filters, not grouping dimensions for this report.
		 */
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					session_utm.campaign,
					session_utm.source,
					session_utm.medium,
					COUNT(DISTINCT session_utm.visitor_id) AS visitors,
					COUNT(session_utm.session_id) AS sessions
				FROM (
					SELECT
						s.ID         AS session_id,
						s.visitor_id AS visitor_id,
						MAX(CASE WHEN p.param_key = 'utm_campaign' THEN p.param_value END) AS campaign,
						MAX(CASE WHEN p.param_key = 'utm_source'   THEN p.param_value END) AS source,
						MAX(CASE WHEN p.param_key = 'utm_medium'   THEN p.param_value END) AS medium
					FROM %i p
					INNER JOIN %i s ON p.session_id = s.ID
					WHERE p.param_key IN ('utm_campaign', 'utm_source', 'utm_medium')
					  AND s.started_at BETWEEN %s AND %s
					GROUP BY s.ID, s.visitor_id
				) session_utm
				GROUP BY session_utm.campaign, session_utm.source, session_utm.medium
				ORDER BY visitors DESC
				LIMIT %d",
				$parameters,
				$sessions,
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Cast count columns to int — $wpdb returns numeric columns as strings,
		// which would otherwise leak through the REST contract to JS clients.
		$payload = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$row['visitors'] = (int) $row['visitors'];
			$row['sessions'] = (int) $row['sessions'];
			$payload[]       = $row;
		}

		$this->set_cached_response( 'utm', $params, $payload, $from, $to );

		return new WP_REST_Response( $payload, 200 );
	}
}
