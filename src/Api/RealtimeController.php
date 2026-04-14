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
 * REST API controller for real-time analytics.
 *
 * Endpoint: GET /wp-json/statnive/v1/realtime
 * Returns active visitors, active pages, and recent pageview feed.
 */
final class RealtimeController extends WP_REST_Controller {

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
	protected $rest_base = 'realtime';

	/**
	 * Cache time-to-live in seconds.
	 *
	 * @var int
	 */
	private const CACHE_TTL = 5;

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
	 * Get real-time analytics data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$cached = get_transient( 'statnive_realtime' );
		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		global $wpdb;
		$sessions      = TableRegistry::get( 'sessions' );
		$views         = TableRegistry::get( 'views' );
		$resource_uris = TableRegistry::get( 'resource_uris' );
		$resources     = TableRegistry::get( 'resources' );
		$countries     = TableRegistry::get( 'countries' );
		$browsers      = TableRegistry::get( 'device_browsers' );

		$five_min_ago = gmdate( 'Y-m-d H:i:s', time() - 300 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT s.visitor_id)
				FROM %i v
				INNER JOIN %i s ON v.session_id = s.ID
				WHERE v.viewed_at >= %s',
				$views,
				$sessions,
				$five_min_ago
			)
		);

		$active_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ru.uri, COALESCE(res.cached_title, '') AS title, COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i v
				INNER JOIN %i s ON v.session_id = s.ID
				INNER JOIN %i ru ON v.resource_uri_id = ru.ID
				LEFT JOIN (SELECT resource_id, MIN(cached_title) AS cached_title FROM %i GROUP BY resource_id) res ON ru.resource_id = res.resource_id
				WHERE v.viewed_at >= %s
				GROUP BY ru.uri, res.cached_title ORDER BY visitors DESC LIMIT 10",
				$views,
				$sessions,
				$resource_uris,
				$resources,
				$five_min_ago
			),
			ARRAY_A
		);

		$recent_feed = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ru.uri, COALESCE(res.cached_title, '') AS title,
					COALESCE(c.code, '') AS country, COALESCE(b.name, '') AS browser,
					v.viewed_at AS time
				FROM %i v
				INNER JOIN %i s ON v.session_id = s.ID
				INNER JOIN %i ru ON v.resource_uri_id = ru.ID
				LEFT JOIN (SELECT resource_id, MIN(cached_title) AS cached_title FROM %i GROUP BY resource_id) res ON ru.resource_id = res.resource_id
				LEFT JOIN %i c ON s.country_id = c.ID
				LEFT JOIN %i b ON s.device_browser_id = b.ID
				WHERE v.viewed_at >= %s
				ORDER BY v.viewed_at DESC LIMIT 20",
				$views,
				$sessions,
				$resource_uris,
				$resources,
				$countries,
				$browsers,
				$five_min_ago
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$result = [
			'active_visitors' => $active_count,
			'active_pages'    => is_array( $active_pages ) ? $active_pages : [],
			'recent_feed'     => is_array( $recent_feed ) ? $recent_feed : [],
		];

		set_transient( 'statnive_realtime', $result, self::CACHE_TTL );

		return new WP_REST_Response( $result, 200 );
	}
}
