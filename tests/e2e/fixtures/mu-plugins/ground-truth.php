<?php
/**
 * Ground-truth recorder mu-plugin for Statnive E2E tests.
 *
 * Records every frontend page load into a separate table for
 * correlation testing against Statnive's analytics data.
 *
 * Only active when WP_DEBUG is true (test environments only).
 *
 * @package Statnive\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 7 ) . '/' );
}

// Only run in debug/test mode.
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
	return;
}

/**
 * Create the ground-truth table on plugin load.
 */
add_action(
	'init',
	static function (): void {
		global $wpdb;

		$table = $wpdb->prefix . 'statnive_test_ground_truth';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$table}` (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url varchar(255) NOT NULL,
				timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				user_id bigint(20) unsigned DEFAULT 0,
				ip varchar(45) DEFAULT '',
				PRIMARY KEY (ID)
			) {$wpdb->get_charset_collate()}"
		);
	}
);

/**
 * Record every frontend page load as ground truth.
 */
add_action(
	'template_redirect',
	static function (): void {
		global $wpdb;

		// Skip admin, AJAX, REST, and cron requests.
		if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) || wp_doing_cron() ) {
			return;
		}

		$table = $wpdb->prefix . 'statnive_test_ground_truth';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			[
				'url'       => home_url( $_SERVER['REQUEST_URI'] ?? '/' ),
				'timestamp' => current_time( 'mysql', true ),
				'user_id'   => get_current_user_id(),
				'ip'        => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
			],
			[ '%s', '%s', '%d', '%s' ]
		);
	}
);

/**
 * Register a debug REST endpoint to query ground-truth data.
 */
add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'statnive/v1',
			'/debug/ground-truth',
			[
				'methods'             => 'GET',
				'callback'            => static function (): array {
					global $wpdb;
					$table = $wpdb->prefix . 'statnive_test_ground_truth';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					return $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY ID DESC LIMIT 100", ARRAY_A ) ?: [];
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}
);
