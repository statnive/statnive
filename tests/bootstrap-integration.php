<?php
/**
 * PHPUnit bootstrap for integration tests.
 *
 * Loads WordPress test suite and the Statnive plugin.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Locate WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at {$_tests_dir}" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI bootstrap, not web output.
	echo 'Install with: svn co https://develop.svn.wordpress.org/tags/6.7/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes/' . PHP_EOL;
	exit( 1 );
}

// Set config path for WP test suite.
putenv( 'WP_TESTS_CONFIG_FILE_PATH=' . __DIR__ . '/wp-tests-config.php' );

// Load test functions (provides tests_add_filter).
require_once $_tests_dir . '/includes/functions.php';

// Load the plugin before WordPress initialises.
tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/statnive.php';
	}
);

// Bootstrap WordPress test suite.
require $_tests_dir . '/includes/bootstrap.php';
