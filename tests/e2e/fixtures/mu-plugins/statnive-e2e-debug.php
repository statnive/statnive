<?php
/**
 * E2E-only: debug REST endpoints the test harness uses for setup/teardown.
 *
 * Mounted under `/wp-json/statnive/v1/debug/*`. Every route is gated on:
 *   1. `WP_DEBUG` being true, AND
 *   2. `manage_options` capability on the caller (admin storageState
 *      carries the nonce), AND
 *   3. the env var `STATNIVE_E2E_DEBUG=1`.
 *
 * Endpoints:
 *   POST /debug/truncate              Truncate all Statnive analytics tables.
 *   POST /debug/backdate              Set date/timestamp columns for seeded rows.
 *   POST /debug/run-purge             Run DataPurgeJob immediately.
 *   GET  /debug/next-scheduled?hook=x Return wp_next_scheduled($hook).
 *   POST /debug/consent-stub          Flip the consent-API transient (see
 *                                     statnive-consent-stub.php).
 *   POST /debug/settings-snapshot     Save current options into a transient.
 *   POST /debug/settings-restore      Restore the snapshotted options.
 *
 * @package Statnive\Tests\E2E
 */

if ( '1' !== getenv( 'STATNIVE_E2E_DEBUG' ) ) {
	return;
}

if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
	return;
}

add_action(
	'rest_api_init',
	static function (): void {
		$perm = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		$tables = [
			'hits',
			'views',
			'sessions',
			'visitors',
			'events',
			'parameters',
			'engagement',
			'daily_summary',
		];

		$settings_keys = [
			'statnive_tracking_enabled',
			'statnive_respect_dnt',
			'statnive_respect_gpc',
			'statnive_consent_mode',
			'statnive_retention_days',
			'statnive_retention_mode',
			'statnive_excluded_ips',
			'statnive_excluded_roles',
		];

		register_rest_route(
			'statnive/v1',
			'/debug/query',
			[
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => static function ( \WP_REST_Request $r ) use ( $tables ): \WP_REST_Response {
					global $wpdb;
					$table = sanitize_key( (string) $r->get_param( 'table' ) );
					if ( ! in_array( $table, $tables, true ) ) {
						return new \WP_REST_Response( [], 200 );
					}
					$name  = $wpdb->prefix . 'statnive_' . $table;
					$where = [];
					foreach ( $r->get_query_params() as $k => $v ) {
						if ( 'table' === $k ) {
							continue;
						}
						$where[ sanitize_key( (string) $k ) ] = (string) $v;
					}
					if ( empty( $where ) ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$rows = $wpdb->get_results( "SELECT * FROM `{$name}` LIMIT 1000", ARRAY_A );
					} else {
						$where_sql = implode( ' AND ', array_map(
							static fn( $k ) => "`" . sanitize_key( (string) $k ) . "` = %s",
							array_keys( $where )
						) );
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$rows = $wpdb->get_results(
							$wpdb->prepare( "SELECT * FROM `{$name}` WHERE {$where_sql} LIMIT 1000", array_values( $where ) ),
							ARRAY_A
						);
					}
					return new \WP_REST_Response( $rows ?: [], 200 );
				},
			]
		);

		register_rest_route(
			'statnive/v1',
			'/debug/truncate',
			[
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => static function () use ( $tables ): \WP_REST_Response {
					global $wpdb;
					foreach ( $tables as $t ) {
						$name = $wpdb->prefix . 'statnive_' . $t;
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->query( "TRUNCATE TABLE `{$name}`" );
					}
					return new \WP_REST_Response( [ 'ok' => true ], 200 );
				},
			]
		);

		register_rest_route(
			'statnive/v1',
			'/debug/backdate',
			[
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => static function ( \WP_REST_Request $r ): \WP_REST_Response {
					global $wpdb;
					$body   = (array) $r->get_json_params();
					$table  = sanitize_key( (string) ( $body['table'] ?? '' ) );
					$column = sanitize_key( (string) ( $body['column'] ?? '' ) );
					$days   = (int) ( $body['days_ago'] ?? 0 );
					$where  = (array) ( $body['where'] ?? [] );
					if ( '' === $table || '' === $column || $days < 0 ) {
						return new \WP_REST_Response( [ 'ok' => false, 'error' => 'bad_params' ], 400 );
					}
					$name = $wpdb->prefix . 'statnive_' . $table;
					// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( empty( $where ) ) {
						$wpdb->query(
							$wpdb->prepare(
								"UPDATE `{$name}` SET `{$column}` = DATE_SUB(NOW(), INTERVAL %d DAY)",
								$days
							)
						);
					} else {
						$where_sql = implode( ' AND ', array_map(
							static fn( $k ) => "`" . sanitize_key( (string) $k ) . "` = %s",
							array_keys( $where )
						) );
						$wpdb->query(
							$wpdb->prepare(
								"UPDATE `{$name}` SET `{$column}` = DATE_SUB(NOW(), INTERVAL %d DAY) WHERE {$where_sql}",
								array_merge( [ $days ], array_values( $where ) )
							)
						);
					}
					// phpcs:enable
					return new \WP_REST_Response( [ 'ok' => true, 'affected' => $wpdb->rows_affected ], 200 );
				},
			]
		);

		register_rest_route(
			'statnive/v1',
			'/debug/run-purge',
			[
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => static function (): \WP_REST_Response {
					\Statnive\Cron\DataPurgeJob::run();
					return new \WP_REST_Response( [ 'ok' => true ], 200 );
				},
			]
		);

		register_rest_route(
			'statnive/v1',
			'/debug/next-scheduled',
			[
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => static function ( \WP_REST_Request $r ): \WP_REST_Response {
					$hook = sanitize_key( (string) $r->get_param( 'hook' ) );
					return new \WP_REST_Response(
						[ 'next' => (int) wp_next_scheduled( $hook ) ],
						200
					);
				},
			]
		);

		register_rest_route(
			'statnive/v1',
			'/debug/consent-stub',
			[
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => static function ( \WP_REST_Request $r ): \WP_REST_Response {
					$body     = (array) $r->get_json_params();
					$category = sanitize_key( (string) ( $body['category'] ?? 'statistics' ) );
					$value    = ! empty( $body['granted'] ) ? '1' : '0';
					set_transient( '_statnive_e2e_consent_' . $category, $value, HOUR_IN_SECONDS );
					return new \WP_REST_Response( [ 'ok' => true ], 200 );
				},
			]
		);

		register_rest_route(
			'statnive/v1',
			'/debug/settings-snapshot',
			[
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => static function () use ( $settings_keys ): \WP_REST_Response {
					$snap = [];
					foreach ( $settings_keys as $k ) {
						$snap[ $k ] = get_option( $k, null );
					}
					set_transient( '_statnive_e2e_settings_snapshot', $snap, DAY_IN_SECONDS );
					return new \WP_REST_Response( [ 'ok' => true ], 200 );
				},
			]
		);

		register_rest_route(
			'statnive/v1',
			'/debug/settings-restore',
			[
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => static function () use ( $settings_keys ): \WP_REST_Response {
					$snap = get_transient( '_statnive_e2e_settings_snapshot' );
					if ( ! is_array( $snap ) ) {
						return new \WP_REST_Response( [ 'ok' => false, 'error' => 'no_snapshot' ], 404 );
					}
					foreach ( $settings_keys as $k ) {
						if ( array_key_exists( $k, $snap ) ) {
							if ( null === $snap[ $k ] ) {
								delete_option( $k );
							} else {
								update_option( $k, $snap[ $k ] );
							}
						}
					}
					return new \WP_REST_Response( [ 'ok' => true ], 200 );
				},
			]
		);
	}
);
