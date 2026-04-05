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
 * REST API controller for events dashboard data.
 *
 * Endpoints:
 * - GET /statnive/v1/events — list event names with counts
 * - GET /statnive/v1/events/(?P<name>[a-zA-Z0-9_]+) — single event detail
 */
final class EventsStatsController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'events';

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<name>[a-zA-Z0-9_]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'from' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'to'   => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
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
	public function permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get event names with counts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		global $wpdb;

		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$limit = min( (int) $request->get_param( 'limit' ), 100 );
		$table = TableRegistry::get( 'events' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_name, COUNT(*) AS total, COUNT(DISTINCT session_id) AS sessions
				FROM `{$table}`
				WHERE created_at BETWEEN %s AND %s
				GROUP BY event_name
				ORDER BY total DESC
				LIMIT %d",
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		return new WP_REST_Response( is_array( $rows ) ? $rows : [], 200 );
	}

	/**
	 * Get detail for a single event name.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ): WP_REST_Response {
		global $wpdb;

		$name  = sanitize_text_field( $request->get_param( 'name' ) );
		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$table = TableRegistry::get( 'events' );

		// Daily trend.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS date, COUNT(*) AS total
				FROM `{$table}`
				WHERE event_name = %s AND created_at BETWEEN %s AND %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$name,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);

		// Property breakdown (sample last 1000 events).
		$props = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT event_data FROM `{$table}`
				WHERE event_name = %s AND event_data IS NOT NULL
				AND created_at BETWEEN %s AND %s
				ORDER BY created_at DESC LIMIT 1000",
				$name,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			)
		);
		// phpcs:enable

		// Aggregate property values.
		$prop_counts = [];
		if ( is_array( $props ) ) {
			foreach ( $props as $json ) {
				$decoded = json_decode( $json, true );
				if ( ! is_array( $decoded ) ) {
					continue;
				}
				foreach ( $decoded as $key => $value ) {
					$prop_key                 = $key . '=' . (string) $value;
					$prop_counts[ $prop_key ] = ( $prop_counts[ $prop_key ] ?? 0 ) + 1;
				}
			}
		}

		arsort( $prop_counts );

		return new WP_REST_Response(
			[
				'event_name' => $name,
				'daily'      => is_array( $daily ) ? $daily : [],
				'properties' => array_slice( $prop_counts, 0, 50, true ),
			],
			200
		);
	}
}
