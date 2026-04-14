<?php
/**
 * PHPStan bootstrap stub.
 *
 * Defines plugin constants that are normally set in statnive.php at runtime
 * so PHPStan's static analysis can resolve them without booting WordPress.
 *
 * @package Statnive
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! defined( 'STATNIVE_VERSION' ) ) {
	define( 'STATNIVE_VERSION', '0.0.0' );
}
if ( ! defined( 'STATNIVE_PATH' ) ) {
	define( 'STATNIVE_PATH', __DIR__ . '/../' );
}
if ( ! defined( 'STATNIVE_FILE' ) ) {
	define( 'STATNIVE_FILE', __DIR__ . '/../statnive.php' );
}
if ( ! defined( 'STATNIVE_MIN_PHP' ) ) {
	define( 'STATNIVE_MIN_PHP', '8.0' );
}
if ( ! defined( 'STATNIVE_MIN_WP' ) ) {
	define( 'STATNIVE_MIN_WP', '6.2' );
}
