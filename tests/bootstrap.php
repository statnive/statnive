<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads the Composer autoloader for unit tests.
 * Integration tests require WordPress test framework — configured separately.
 */

declare(strict_types=1);

$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $autoloader ) ) {
	echo 'Composer autoloader not found. Run "composer install" first.' . PHP_EOL;
	exit( 1 );
}

require_once $autoloader;

/*
 * Define ABSPATH so source files pass the direct-access guard.
 * In real WordPress, ABSPATH is defined by wp-config.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/*
 * Stub WordPress functions used by production code under unit test.
 * Integration tests load real WordPress — these stubs are for unit tests only.
 */
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook_name, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook_name ): void {
	}
}
