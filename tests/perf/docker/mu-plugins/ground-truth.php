<?php
/**
 * Plugin Name: Perf Ground Truth Recorder
 * Description: Standalone mu-plugin that records expected analytics hits for validation testing. Drop into wp-content/mu-plugins/. No dependency on any analytics plugin.
 * Version: 1.0.0
 * Requires PHP: 8.1
 *
 * REST Endpoints (namespace: ground-truth/v1):
 *   POST   /record          — Record an expected hit
 *   GET    /summary          — Aggregated totals for date range
 *   GET    /by-channel       — Breakdown by expected channel
 *   GET    /by-device        — Breakdown by device type
 *   GET    /by-page          — Breakdown by resource
 *   DELETE /clear            — Clear test data
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ground Truth Recorder — self-contained, plugin-agnostic.
 */
final class Perf_Ground_Truth {

	private const TABLE_SUFFIX = 'perf_ground_truth';
	private const API_NAMESPACE = 'ground-truth/v1';

	/**
	 * Boot the plugin.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		self::maybe_create_table();
	}

	/**
	 * Get the full table name.
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the ground truth table if it doesn't exist.
	 */
	private static function maybe_create_table(): void {
		global $wpdb;

		$table = self::table_name();

		// Quick check — avoid running dbDelta on every request.
		if ( get_option( 'perf_ground_truth_db_version' ) === '1.0.0' ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			test_run_id varchar(64) NOT NULL,
			profile_id varchar(32) NOT NULL DEFAULT '',
			resource_type varchar(20) NOT NULL,
			resource_id bigint(20) unsigned NOT NULL,
			page_url varchar(500) NOT NULL DEFAULT '',
			referrer_url text NOT NULL,
			expected_channel varchar(50) NOT NULL DEFAULT '',
			utm_source varchar(100) NOT NULL DEFAULT '',
			utm_medium varchar(100) NOT NULL DEFAULT '',
			utm_campaign varchar(100) NOT NULL DEFAULT '',
			device_type varchar(20) NOT NULL DEFAULT '',
			is_bot tinyint(1) NOT NULL DEFAULT 0,
			is_logged_in tinyint(1) NOT NULL DEFAULT 0,
			user_agent text NOT NULL,
			recorded_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_test_run (test_run_id),
			KEY idx_recorded_at (recorded_at),
			KEY idx_channel (expected_channel, recorded_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'perf_ground_truth_db_version', '1.0.0' );
	}

	/**
	 * Register all REST routes.
	 */
	public static function register_routes(): void {
		$ns = self::API_NAMESPACE;

		register_rest_route( $ns, '/record', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_record' ],
			'permission_callback' => [ self::class, 'check_admin' ],
		] );

		register_rest_route( $ns, '/summary', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'handle_summary' ],
			'permission_callback' => [ self::class, 'check_admin' ],
			'args'                => self::date_range_args(),
		] );

		register_rest_route( $ns, '/by-channel', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'handle_by_channel' ],
			'permission_callback' => [ self::class, 'check_admin' ],
			'args'                => self::date_range_args(),
		] );

		register_rest_route( $ns, '/by-device', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'handle_by_device' ],
			'permission_callback' => [ self::class, 'check_admin' ],
			'args'                => self::date_range_args(),
		] );

		register_rest_route( $ns, '/by-page', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'handle_by_page' ],
			'permission_callback' => [ self::class, 'check_admin' ],
			'args'                => self::date_range_args(),
		] );

		register_rest_route( $ns, '/clear', [
			'methods'             => 'DELETE',
			'callback'            => [ self::class, 'handle_clear' ],
			'permission_callback' => [ self::class, 'check_admin' ],
		] );

		register_rest_route( $ns, '/compare-db', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'handle_compare_db' ],
			'permission_callback' => [ self::class, 'check_admin' ],
			'args'                => self::date_range_args(),
		] );

		register_rest_route( $ns, '/site-pages', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'handle_site_pages' ],
			'permission_callback' => [ self::class, 'check_admin' ],
		] );
	}

	/**
	 * Permission check — require manage_options.
	 */
	public static function check_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Common date range args for GET endpoints.
	 */
	private static function date_range_args(): array {
		return [
			'from' => [
				'required'          => false,
				'default'           => gmdate( 'Y-m-d' ),
				'validate_callback' => function ( $value ) {
					return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
				},
			],
			'to' => [
				'required'          => false,
				'default'           => gmdate( 'Y-m-d' ),
				'validate_callback' => function ( $value ) {
					return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
				},
			],
			'test_run_id' => [
				'required' => false,
				'default'  => '',
			],
		];
	}

	/**
	 * POST /record — Insert a ground truth record.
	 */
	public static function handle_record( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		$result = $wpdb->insert(
			self::table_name(),
			[
				'test_run_id'      => sanitize_text_field( $data['test_run_id'] ?? '' ),
				'profile_id'       => sanitize_text_field( $data['profile_id'] ?? '' ),
				'resource_type'    => sanitize_text_field( $data['resource_type'] ?? '' ),
				'resource_id'      => absint( $data['resource_id'] ?? 0 ),
				'page_url'         => esc_url_raw( $data['page_url'] ?? '' ),
				'referrer_url'     => esc_url_raw( $data['referrer_url'] ?? '' ),
				'expected_channel' => sanitize_text_field( $data['expected_channel'] ?? '' ),
				'utm_source'       => sanitize_text_field( $data['utm_source'] ?? '' ),
				'utm_medium'       => sanitize_text_field( $data['utm_medium'] ?? '' ),
				'utm_campaign'     => sanitize_text_field( $data['utm_campaign'] ?? '' ),
				'device_type'      => sanitize_text_field( $data['device_type'] ?? '' ),
				'is_bot'           => ! empty( $data['is_bot'] ) ? 1 : 0,
				'is_logged_in'     => ! empty( $data['is_logged_in'] ) ? 1 : 0,
				'user_agent'       => sanitize_text_field( $data['user_agent'] ?? '' ),
				'recorded_at'      => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		if ( false === $result ) {
			return new \WP_REST_Response(
				[ 'error' => 'Failed to insert record', 'db_error' => $wpdb->last_error ],
				500
			);
		}

		return new \WP_REST_Response( [ 'id' => $wpdb->insert_id ], 201 );
	}

	/**
	 * GET /summary — Aggregated totals for a date range.
	 */
	public static function handle_summary( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$table = self::table_name();
		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$run   = $request->get_param( 'test_run_id' );

		$where = $wpdb->prepare(
			'WHERE recorded_at >= %s AND recorded_at < %s',
			$from . ' 00:00:00',
			$to . ' 23:59:59'
		);

		if ( $run ) {
			$where .= $wpdb->prepare( ' AND test_run_id = %s', $run );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_hits,
				COUNT(DISTINCT profile_id) as unique_profiles,
				SUM(is_bot) as bot_hits,
				SUM(is_logged_in) as logged_in_hits,
				COUNT(DISTINCT CASE WHEN is_bot = 0 THEN profile_id END) as human_profiles
			FROM {$table}
			{$where}",
			ARRAY_A
		);

		return new \WP_REST_Response( [
			'total_hits'      => (int) ( $row['total_hits'] ?? 0 ),
			'unique_profiles' => (int) ( $row['unique_profiles'] ?? 0 ),
			'bot_hits'        => (int) ( $row['bot_hits'] ?? 0 ),
			'logged_in_hits'  => (int) ( $row['logged_in_hits'] ?? 0 ),
			'human_profiles'  => (int) ( $row['human_profiles'] ?? 0 ),
			'from'            => $from,
			'to'              => $to,
		] );
	}

	/**
	 * GET /by-channel — Breakdown by expected channel.
	 */
	public static function handle_by_channel( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$table = self::table_name();
		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$run   = $request->get_param( 'test_run_id' );

		$where = $wpdb->prepare(
			'WHERE recorded_at >= %s AND recorded_at < %s',
			$from . ' 00:00:00',
			$to . ' 23:59:59'
		);

		if ( $run ) {
			$where .= $wpdb->prepare( ' AND test_run_id = %s', $run );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT
				expected_channel,
				COUNT(*) as hits,
				COUNT(DISTINCT profile_id) as unique_profiles
			FROM {$table}
			{$where}
			GROUP BY expected_channel
			ORDER BY hits DESC",
			ARRAY_A
		);

		return new \WP_REST_Response( $rows ?: [] );
	}

	/**
	 * GET /by-device — Breakdown by device type.
	 */
	public static function handle_by_device( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$table = self::table_name();
		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$run   = $request->get_param( 'test_run_id' );

		$where = $wpdb->prepare(
			'WHERE recorded_at >= %s AND recorded_at < %s',
			$from . ' 00:00:00',
			$to . ' 23:59:59'
		);

		if ( $run ) {
			$where .= $wpdb->prepare( ' AND test_run_id = %s', $run );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT
				device_type,
				COUNT(*) as hits,
				COUNT(DISTINCT profile_id) as unique_profiles
			FROM {$table}
			{$where}
			GROUP BY device_type
			ORDER BY hits DESC",
			ARRAY_A
		);

		return new \WP_REST_Response( $rows ?: [] );
	}

	/**
	 * GET /by-page — Breakdown by resource.
	 */
	public static function handle_by_page( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$table = self::table_name();
		$from  = $request->get_param( 'from' );
		$to    = $request->get_param( 'to' );
		$run   = $request->get_param( 'test_run_id' );

		$where = $wpdb->prepare(
			'WHERE recorded_at >= %s AND recorded_at < %s',
			$from . ' 00:00:00',
			$to . ' 23:59:59'
		);

		if ( $run ) {
			$where .= $wpdb->prepare( ' AND test_run_id = %s', $run );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT
				resource_type,
				resource_id,
				page_url,
				COUNT(*) as hits,
				COUNT(DISTINCT profile_id) as unique_profiles
			FROM {$table}
			{$where}
			GROUP BY resource_type, resource_id, page_url
			ORDER BY hits DESC",
			ARRAY_A
		);

		return new \WP_REST_Response( $rows ?: [] );
	}

	/**
	 * DELETE /clear — Remove ground truth records.
	 */
	public static function handle_clear( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$table = self::table_name();
		$run   = sanitize_text_field( $request->get_param( 'test_run_id' ) ?? '' );

		if ( $run ) {
			$deleted = $wpdb->delete( $table, [ 'test_run_id' => $run ], [ '%s' ] );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		return new \WP_REST_Response( [ 'deleted' => $deleted ] );
	}
	/**
	 * GET /compare-db — Query all installed analytics plugin tables directly.
	 *
	 * Returns a unified comparison of what each plugin recorded for a date range.
	 */
	public static function handle_compare_db( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$from = $request->get_param( 'from' ) . ' 00:00:00';
		$to   = $request->get_param( 'to' ) . ' 23:59:59';

		$results = [];

		// --- Statnive ---
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}statnive_views'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					COUNT(v.id) as views,
					COUNT(DISTINCT s.id) as sessions,
					COUNT(DISTINCT s.visitor_id) as visitors
				FROM {$wpdb->prefix}statnive_views v
				JOIN {$wpdb->prefix}statnive_sessions s ON v.session_id = s.id
				WHERE s.started_at BETWEEN %s AND %s",
				$from, $to
			), ARRAY_A );
			$results['statnive'] = [
				'views'    => (int) ( $row['views'] ?? 0 ),
				'sessions' => (int) ( $row['sessions'] ?? 0 ),
				'visitors' => (int) ( $row['visitors'] ?? 0 ),
			];
		}

		// --- WP Statistics ---
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}statistics_visitor'" ) ) {
			$visitors = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}statistics_visitor WHERE last_counter BETWEEN %s AND %s",
				substr( $from, 0, 10 ), substr( $to, 0, 10 )
			) );
			$pages = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(count), 0) FROM {$wpdb->prefix}statistics_pages WHERE date BETWEEN %s AND %s",
				substr( $from, 0, 10 ), substr( $to, 0, 10 )
			) );
			$results['wp-statistics'] = [
				'views'    => $pages,
				'sessions' => 0,
				'visitors' => $visitors,
			];
		}

		// --- Koko Analytics ---
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}koko_analytics_site_stats'" ) ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT COALESCE(SUM(pageviews), 0) as views, COALESCE(SUM(visitors), 0) as visitors
				FROM {$wpdb->prefix}koko_analytics_site_stats
				WHERE date BETWEEN %s AND %s",
				substr( $from, 0, 10 ), substr( $to, 0, 10 )
			), ARRAY_A );
			$results['koko-analytics'] = [
				'views'    => (int) ( $row['views'] ?? 0 ),
				'sessions' => 0,
				'visitors' => (int) ( $row['visitors'] ?? 0 ),
			];
		}

		// --- Burst Statistics ---
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}burst_statistics'" ) ) {
			$views = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}burst_statistics WHERE FROM_UNIXTIME(time) BETWEEN %s AND %s",
				$from, $to
			) );
			$sessions = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}burst_sessions WHERE FROM_UNIXTIME(time) BETWEEN %s AND %s",
				$from, $to
			) );
			$results['burst-statistics'] = [
				'views'    => $views,
				'sessions' => $sessions,
				'visitors' => 0,
			];
		}

		// --- Independent Analytics ---
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}independent_analytics_views'" ) ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					COUNT(v.id) as views,
					COUNT(DISTINCT v.session_id) as sessions,
					(SELECT COUNT(*) FROM {$wpdb->prefix}independent_analytics_visitors WHERE first_visit BETWEEN %s AND %s) as visitors
				FROM {$wpdb->prefix}independent_analytics_views v
				WHERE v.viewed_at BETWEEN %s AND %s",
				$from, $to, $from, $to
			), ARRAY_A );
			$results['independent-analytics'] = [
				'views'    => (int) ( $row['views'] ?? 0 ),
				'sessions' => (int) ( $row['sessions'] ?? 0 ),
				'visitors' => (int) ( $row['visitors'] ?? 0 ),
			];
		}

		// --- WP Slimstat ---
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}slim_stats'" ) ) {
			$views = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}slim_stats WHERE FROM_UNIXTIME(dt) BETWEEN %s AND %s",
				$from, $to
			) );
			$results['wp-slimstat'] = [
				'views'    => $views,
				'sessions' => 0,
				'visitors' => 0,
			];
		}

		return new \WP_REST_Response( $results );
	}

	/**
	 * GET /site-pages — Return all public URLs on the site for test scripts.
	 *
	 * Caches the result so test scripts can call this once in setup().
	 */
	public static function handle_site_pages( \WP_REST_Request $request ): \WP_REST_Response {
		$pages = [];

		// Homepage.
		$pages[] = [
			'url'   => home_url( '/' ),
			'type'  => 'home',
			'id'    => 0,
			'title' => 'Homepage',
		];

		// Published pages.
		$wp_pages = get_posts( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => 50,
		] );
		foreach ( $wp_pages as $p ) {
			$pages[] = [
				'url'   => get_permalink( $p ),
				'type'  => 'page',
				'id'    => $p->ID,
				'title' => $p->post_title,
			];
		}

		// Published posts.
		$wp_posts = get_posts( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'numberposts' => 50,
		] );
		foreach ( $wp_posts as $p ) {
			$pages[] = [
				'url'   => get_permalink( $p ),
				'type'  => 'post',
				'id'    => $p->ID,
				'title' => $p->post_title,
			];
		}

		// WooCommerce products (if active).
		if ( post_type_exists( 'product' ) ) {
			$products = get_posts( [
				'post_type'   => 'product',
				'post_status' => 'publish',
				'numberposts' => 20,
			] );
			foreach ( $products as $p ) {
				$pages[] = [
					'url'   => get_permalink( $p ),
					'type'  => 'product',
					'id'    => $p->ID,
					'title' => $p->post_title,
				];
			}
		}

		return new \WP_REST_Response( $pages );
	}
}

// Boot.
Perf_Ground_Truth::init();
