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
	// Table names come from SHOW TABLES — safe to use directly.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$statnive_table}`" );
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

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

// Delete scheduled cron events.
wp_clear_scheduled_hook( 'statnive_daily_salt_rotation' );
wp_clear_scheduled_hook( 'statnive_daily_aggregation' );
wp_clear_scheduled_hook( 'statnive_daily_data_purge' );
wp_clear_scheduled_hook( 'statnive_email_report' );
wp_clear_scheduled_hook( 'statnive_weekly_license_check' );
wp_clear_scheduled_hook( 'statnive_weekly_geoip_update' );
