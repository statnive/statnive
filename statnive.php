<?php
/**
 * Plugin Name: Statnive
 * Plugin URI:  https://statnive.com
 * Description: Simple stats, clear decisions. Privacy-first analytics for WordPress.
 * Version:     0.3.1
 * Requires PHP: 8.0
 * Requires at least: 5.6
 * Author:      Statnive
 * Author URI:  https://statnive.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: statnive
 * Domain Path: /languages
 *
 * @package Statnive
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 */
define( 'STATNIVE_VERSION', '0.3.1' );

/**
 * Plugin root directory path.
 */
define( 'STATNIVE_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin main file path.
 */
define( 'STATNIVE_FILE', __FILE__ );

/**
 * Minimum required PHP version.
 */
define( 'STATNIVE_MIN_PHP', '8.0' );

/**
 * Minimum required WordPress version.
 */
define( 'STATNIVE_MIN_WP', '5.6' );

/**
 * Check PHP version before proceeding.
 */
if ( version_compare( PHP_VERSION, STATNIVE_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', 'statnive_php_version_notice' );
	return;
}

/**
 * Display admin notice for unsupported PHP version.
 */
function statnive_php_version_notice(): void {
	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				__( 'Statnive requires PHP %1$s or higher. You are running PHP %2$s.', 'statnive' ),
				STATNIVE_MIN_PHP,
				PHP_VERSION
			)
		)
	);
}

/**
 * Check WordPress version before proceeding.
 */
if ( version_compare( get_bloginfo( 'version' ), STATNIVE_MIN_WP, '<' ) ) {
	add_action( 'admin_notices', 'statnive_wp_version_notice' );
	return;
}

/**
 * Display admin notice for unsupported WordPress version.
 */
function statnive_wp_version_notice(): void {
	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: Required WordPress version, 2: Current WordPress version */
				__( 'Statnive requires WordPress %1$s or higher. You are running WordPress %2$s.', 'statnive' ),
				STATNIVE_MIN_WP,
				get_bloginfo( 'version' )
			)
		)
	);
}

/**
 * Load Composer autoloader.
 */
$statnive_autoloader = STATNIVE_PATH . 'vendor/autoload.php';
if ( ! file_exists( $statnive_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Statnive: Composer autoloader not found. Please run "composer install" in the plugin directory.', 'statnive' )
			);
		}
	);
	return;
}
require_once $statnive_autoloader;

/**
 * Boot the plugin.
 */
\Statnive\Plugin::init();
