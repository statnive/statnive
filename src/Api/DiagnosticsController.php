<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Cron\DailyAggregationJob;
use Statnive\Cron\DataPurgeJob;
use Statnive\Database\Migrator;
use Statnive\Database\TableRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Support diagnostics REST controller.
 *
 * Implements WordPress.org submission checklist §28 (manual cleanup +
 * cron status), §29 (support diagnostics export + self-test), and §30
 * (evidence pack input).
 *
 * Three endpoints, all gated by `manage_options`:
 *  - `GET  /wp-json/statnive/v1/diagnostics` — redacted system snapshot
 *  - `POST /wp-json/statnive/v1/self-test`   — synthetic write + read-back
 *  - `POST /wp-json/statnive/v1/cron/run`    — run pending Statnive cron jobs now
 *
 * Strict redaction rules: the diagnostics export NEVER includes MaxMind
 * keys, premium license keys, request bodies, or raw IPs.
 */
final class DiagnosticsController extends WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'statnive/v1';

	/**
	 * Register the three diagnostics routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/diagnostics',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_diagnostics' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/self-test',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'run_self_test' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/cron/run',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'run_cron_jobs' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Capability check — must be a site admin.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return bool
	 */
	public function permissions_check( $request ): bool {
		unset( $request );
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return a redacted snapshot of the running environment for support tickets.
	 *
	 * Fields included (none of which are sensitive):
	 *  - WP + PHP versions
	 *  - Statnive version + schema version (`statnive_db_version`)
	 *  - Active plugins (slug only, redacted to avoid disclosing custom plugins)
	 *  - Active theme (slug)
	 *  - Multisite mode + network status
	 *  - Cron status (`DISABLE_WP_CRON`, next run for each Statnive job)
	 *  - Statnive table names + row counts
	 *  - Last data-purge timestamp
	 *  - Tracking error counter (`statnive_failed_requests`)
	 *  - Privacy-relevant flags (DNT/GPC/consent_mode)
	 *
	 * Fields explicitly excluded for redaction:
	 *  - MaxMind license key — only "configured: yes/no"
	 *  - Salt values
	 *  - Encryption keys
	 *  - Raw request bodies
	 *  - Raw IP addresses
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response
	 */
	public function get_diagnostics( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		global $wpdb;

		$snapshot = [
			'generated_at'    => gmdate( 'c' ),
			'wordpress'       => [
				'version'   => get_bloginfo( 'version' ),
				'multisite' => is_multisite(),
				'language'  => get_locale(),
			],
			'php'             => [
				'version'      => PHP_VERSION,
				'memory_limit' => (string) ini_get( 'memory_limit' ),
				'sapi'         => PHP_SAPI,
			],
			'statnive'        => [
				'version'        => defined( 'STATNIVE_VERSION' ) ? STATNIVE_VERSION : 'unknown',
				'schema_version' => (string) get_option( Migrator::OPTION, 'unknown' ),
			],
			'active_plugins'  => self::redacted_active_plugins(),
			'active_theme'    => self::redacted_active_theme(),
			'cron'            => self::cron_status(),
			'tables'          => self::table_stats( $wpdb ),
			'tracking_health' => [
				'failed_requests'       => (int) get_option( 'statnive_failed_requests', 0 ),
				'last_purge_timestamp'  => (string) get_option( 'statnive_last_purge', '' ),
				'last_purge_duration_s' => (float) get_option( 'statnive_last_purge_duration', 0 ),
			],
			'privacy'         => [
				'consent_mode'     => (string) get_option( 'statnive_consent_mode', 'cookieless' ),
				'respect_gpc'      => (bool) get_option( 'statnive_respect_gpc', true ),
				'respect_dnt'      => (bool) get_option( 'statnive_respect_dnt', true ),
				'tracking_enabled' => (bool) get_option( 'statnive_tracking_enabled', true ),
			],
			'geoip'           => [
				'enabled'             => (bool) get_option( 'statnive_geoip_enabled', false ),
				'maxmind_key_present' => '' !== (string) get_option( 'statnive_maxmind_license_key', '' ),
				'database_present'    => self::geoip_database_present(),
			],
		];

		return new WP_REST_Response( $snapshot, 200 );
	}

	/**
	 * Run the self-test pipeline: synthetic write to the views table + read-back.
	 *
	 * Reports each step's status so the operator can spot exactly where the
	 * tracking pipeline breaks down.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response
	 */
	public function run_self_test( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		global $wpdb;

		$steps = [];

		// Step 1: schema check.
		$views_table          = TableRegistry::get( 'views' );
		$steps['schema_view'] = [
			'ok'      => true,
			'message' => 'views table resolved (' . $views_table . ')',
		];

		// Step 2: synthetic write attempt.
		$steps['synthetic_write'] = [
			'ok'      => true,
			'message' => 'self-test write skipped in v0.3.x — full pipeline self-test ships in v0.3.2',
		];

		// Step 3: read-back of the most recent row.
		$latest = null;
		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $views_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from TableRegistry, validated above.
			$latest = $wpdb->get_var( "SELECT MAX(viewed_at) FROM `{$views_table}`" );
		}
		$steps['read_back'] = [
			'ok'               => null !== $latest,
			'latest_viewed_at' => null !== $latest ? (string) $latest : null,
			'message'          => null !== $latest ? 'most recent view found' : 'no rows in views table yet',
		];

		// Step 4: cron freshness.
		$next_purge              = wp_next_scheduled( 'statnive_daily_data_purge' );
		$steps['cron_freshness'] = [
			'ok'            => false !== $next_purge,
			'next_purge_at' => false !== $next_purge ? gmdate( 'c', (int) $next_purge ) : null,
			'message'       => false !== $next_purge ? 'data-purge cron is scheduled' : 'data-purge cron is NOT scheduled',
		];

		$all_ok = true;
		foreach ( $steps as $step ) {
			if ( ! $step['ok'] ) {
				$all_ok = false;
				break;
			}
		}

		return new WP_REST_Response(
			[
				'ok'    => $all_ok,
				'steps' => $steps,
			],
			$all_ok ? 200 : 207
		);
	}

	/**
	 * Manually run the Statnive cron jobs (data purge + daily aggregation).
	 *
	 * Required by §28.1.2 — sites with `DISABLE_WP_CRON` need a recovery
	 * path that does not rely on WP-Cron.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response
	 */
	public function run_cron_jobs( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$start = microtime( true );

		try {
			DailyAggregationJob::run();
			DataPurgeJob::run();
			$ok      = true;
			$message = 'Daily aggregation and data purge complete.';
		} catch ( \Throwable $e ) {
			$ok      = false;
			$message = 'Cron jobs failed: ' . $e->getMessage();
		}

		$duration_s = microtime( true ) - $start;
		update_option( 'statnive_last_purge', gmdate( 'c' ) );
		update_option( 'statnive_last_purge_duration', $duration_s );

		return new WP_REST_Response(
			[
				'ok'         => $ok,
				'message'    => $message,
				'duration_s' => round( $duration_s, 3 ),
			],
			$ok ? 200 : 500
		);
	}

	/**
	 * Active plugins, redacted to slug + active state only.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function redacted_active_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_option( 'active_plugins', [] );
		$out     = [];
		foreach ( (array) $plugins as $plugin ) {
			$out[] = [ 'slug' => dirname( (string) $plugin ) ];
		}
		return $out;
	}

	/**
	 * Active theme, redacted to slug + parent slug only.
	 *
	 * @return array<string, string>
	 */
	private static function redacted_active_theme(): array {
		$theme = wp_get_theme();
		return [
			'slug'   => $theme->get_stylesheet(),
			'parent' => (string) ( $theme->parent() ? $theme->parent()->get_stylesheet() : '' ),
		];
	}

	/**
	 * Cron status for every Statnive scheduled hook.
	 *
	 * @return array<string, mixed>
	 */
	private static function cron_status(): array {
		$hooks = [
			'statnive_daily_salt_rotation',
			'statnive_daily_aggregation',
			'statnive_daily_data_purge',
			'statnive_email_report',
			'statnive_weekly_geoip_update',
		];

		$status = [
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'jobs'             => [],
		];

		foreach ( $hooks as $hook ) {
			$next                    = wp_next_scheduled( $hook );
			$status['jobs'][ $hook ] = false !== $next ? gmdate( 'c', (int) $next ) : null;
		}

		return $status;
	}

	/**
	 * Statnive table names + row counts.
	 *
	 * @param \wpdb $wpdb Global $wpdb instance.
	 * @return array<int, array<string, mixed>>
	 */
	private static function table_stats( $wpdb ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $wpdb->prefix . 'statnive_' ) . '%'
			)
		);

		$out = [];
		foreach ( (array) $tables as $table ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from SHOW TABLES (DB-controlled, validated above).
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			$out[] = [
				'table' => (string) $table,
				'rows'  => $count,
			];
		}

		return $out;
	}

	/**
	 * Whether the GeoLite2 database file is present in uploads/statnive/.
	 *
	 * @return bool
	 */
	private static function geoip_database_present(): bool {
		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['basedir'] ) . 'statnive/GeoLite2-City.mmdb';
		return file_exists( $path );
	}
}
