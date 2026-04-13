<?php
/**
 * WordPress test configuration.
 *
 * Used by the WordPress PHPUnit test suite bootstrap.
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', getenv( 'WP_TESTS_ABSPATH' ) ?: '/tmp/wordpress/' );

define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'WP_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Statnive Test' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
define( 'ABSPATH', getenv( 'WP_TESTS_ABSPATH' ) ?: '/tmp/wordpress/' );
