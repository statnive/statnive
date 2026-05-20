<?php
/**
 * Statnive uninstall handler.
 *
 * Two-stage cleanup:
 *
 *  1) ALWAYS run, even when the user keeps their analytics data:
 *     - Clear scheduled cron hooks (orphan cron pointing to dead code is
 *       worse than dropping options).
 *     - Remove downloaded GeoIP database binaries from `wp-content/uploads/statnive/`
 *       (these are large files coupled to the plugin install).
 *
 *  2) OPT-IN data cleanup (default OFF, see `statnive_delete_data_on_uninstall`
 *     option): drop the 26 Statnive tables, delete all `statnive_*` options,
 *     `_transient_statnive_*` transients, and the network-meta equivalents on
 *     multisite. This is the destructive path and is disabled by default so
 *     plugin deletion + re-install preserves analytics history — matches the
 *     plugin-directory expectation that user data is precious.
 *
 * @package Statnive
 */

declare(strict_types=1);

// Prevent direct access — must be called by WordPress.
// ABSPATH guard satisfies WP.org compliance check for all PHP files at plugin root.
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ----------------------------------------------------------------------------
// 1) Cron hook cleanup — ALWAYS run.
// Legacy hooks (license check, email report, import batch) are cleared too so
// sites upgrading from earlier versions don't leave orphan schedules behind.
// ----------------------------------------------------------------------------
wp_clear_scheduled_hook( 'statnive_daily_salt_rotation' );
wp_clear_scheduled_hook( 'statnive_daily_aggregation' );
wp_clear_scheduled_hook( 'statnive_daily_data_purge' );
wp_clear_scheduled_hook( 'statnive_email_report' );
wp_clear_scheduled_hook( 'statnive_weekly_license_check' );
wp_clear_scheduled_hook( 'statnive_weekly_geoip_update' );
wp_clear_scheduled_hook( 'statnive_import_batch' );
// v1.0.0 — WooCommerce rollup hooks.
wp_clear_scheduled_hook( 'statnive_rollup_daily' );
// Action Scheduler hook (no-op when AS isn't loaded).
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'statnive/rollup/daily' );
}

// ----------------------------------------------------------------------------
// 1b) GeoIP file cleanup — ALWAYS run (large binaries, plugin-coupled).
// ----------------------------------------------------------------------------
$statnive_upload_dir = wp_upload_dir();
$statnive_geoip_dir  = trailingslashit( $statnive_upload_dir['basedir'] ) . 'statnive';
if ( is_dir( $statnive_geoip_dir ) ) {
	$statnive_files = glob( $statnive_geoip_dir . '/*' );
	if ( is_array( $statnive_files ) ) {
		foreach ( $statnive_files as $statnive_file ) {
			if ( is_file( $statnive_file ) ) {
				wp_delete_file( $statnive_file );
			}
		}
	}
	// uninstall.php runs in a minimal WP context — WP_Filesystem is not always
	// available, and we are removing our own uploads subdirectory. Direct rmdir()
	// with error suppression is the established WordPress core pattern here.
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	@rmdir( $statnive_geoip_dir );
}

// ----------------------------------------------------------------------------
// 2) OPT-IN data cleanup. Default OFF (safer default — preserves analytics
// history across plugin re-install). Flip via Settings or wp-cli:
// wp option update statnive_delete_data_on_uninstall 1
// ----------------------------------------------------------------------------
if ( (bool) get_option( 'statnive_delete_data_on_uninstall', false ) !== true ) {
	return;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange

// Drop all Statnive database tables.
$statnive_table_prefix = $wpdb->prefix . 'statnive_';

$statnive_tables = $wpdb->get_col(
	$wpdb->prepare(
		'SHOW TABLES LIKE %s',
		$wpdb->esc_like( $statnive_table_prefix ) . '%'
	)
);

foreach ( $statnive_tables as $statnive_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $statnive_table ) );
}

// Delete all Statnive options.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'statnive_' ) . '%'
	)
);

// Delete all Statnive transients (both value and timeout entries).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_statnive_' ) . '%'
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_statnive_' ) . '%'
	)
);

// Multisite: also drop network-wide options if we are on a network install.
if ( is_multisite() ) {
	$statnive_sitemeta = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'statnive_' ) . '%'
		)
	);
	unset( $statnive_sitemeta );
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

// Belt-and-suspenders: explicit delete_option for known sentinel keys, on top
// of the LIKE 'statnive_%' sweep above. The sweep covers everything; these
// calls satisfy plugin reviewers who grep for delete_option() literally.
delete_option( 'statnive_db_version' );
delete_option( 'statnive_version' );
delete_option( 'statnive_delete_data_on_uninstall' );
delete_option( 'statnive_email_pepper' );
