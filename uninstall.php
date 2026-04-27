<?php
/**
 * Statnive uninstall handler.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin.
 * This file is called by WordPress core during plugin deletion.
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

// Delete scheduled cron events.
// Legacy hooks (statnive_weekly_license_check, statnive_email_report) are
// cleared unconditionally so sites upgrading from earlier versions don't
// leave orphan schedules behind.
wp_clear_scheduled_hook( 'statnive_daily_salt_rotation' );
wp_clear_scheduled_hook( 'statnive_daily_aggregation' );
wp_clear_scheduled_hook( 'statnive_daily_data_purge' );
wp_clear_scheduled_hook( 'statnive_email_report' );
wp_clear_scheduled_hook( 'statnive_weekly_license_check' );
wp_clear_scheduled_hook( 'statnive_weekly_geoip_update' );

// Remove downloaded GeoIP database files and the upload directory.
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
