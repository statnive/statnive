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

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub that records call counts into $GLOBALS['statnive_test_get_option_calls']
	 * so tests can assert memoisation behaviour without loading WordPress.
	 *
	 * @param mixed $default
	 * @return mixed
	 */
	function get_option( string $option, $default = false ) {
		if ( isset( $GLOBALS['statnive_test_get_option_calls'] ) && is_array( $GLOBALS['statnive_test_get_option_calls'] ) ) {
			$GLOBALS['statnive_test_get_option_calls'][] = $option;
		}

		if ( isset( $GLOBALS['statnive_test_options'][ $option ] ) ) {
			return $GLOBALS['statnive_test_options'][ $option ];
		}

		return $default;
	}
}

/**
 * Minimal WP_REST_Request stub for unit tests that touch REST helpers
 * without loading the full WordPress REST stack.
 *
 * Only implements get_content_type() which is all PayloadValidator needs.
 * Integration tests load real WordPress and this stub is never used there.
 */
if ( ! class_exists( 'WP_REST_Request' ) ) {
	// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing
	class WP_REST_Request {
		/** @var mixed */
		private $content_type;

		/**
		 * @param mixed $content_type
		 */
		public function __construct( $content_type = null ) {
			$this->content_type = $content_type;
		}

		/**
		 * @return mixed
		 */
		public function get_content_type() {
			return $this->content_type;
		}
	}
	// phpcs:enable
}

/**
 * Exception thrown by stubbed wp_die / wp_send_json_* under unit tests.
 *
 * In real WordPress these helpers call wp_die() which exits the script;
 * under PHPUnit we throw this instead so tests can assert that execution
 * short-circuited without killing the test runner.
 */
if ( ! class_exists( 'Statnive\\Tests\\Support\\WpDieException' ) ) {
	// phpcs:disable Squiz.Commenting.ClassComment.Missing
	final class WpDieException extends \RuntimeException {
		/** @var mixed */
		public $data;

		/** @var int */
		public $status_code;

		/** @var bool */
		public $success;

		/**
		 * @param mixed $data
		 */
		public function __construct( bool $success, $data, int $status_code ) {
			parent::__construct( 'wp_die called under unit tests' );
			$this->success     = $success;
			$this->data        = $data;
			$this->status_code = $status_code;
		}
	}
	// phpcs:enable Squiz.Commenting.ClassComment.Missing
	class_alias( 'WpDieException', 'Statnive\\Tests\\Support\\WpDieException' );
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	/**
	 * @param mixed $data
	 */
	function wp_send_json_success( $data = null, int $status_code = 200 ): void {
		throw new WpDieException( true, $data, $status_code );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	/**
	 * @param mixed $data
	 */
	function wp_send_json_error( $data = null, int $status_code = 200 ): void {
		throw new WpDieException( false, $data, $status_code );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * @param mixed $message
	 */
	function wp_die( $message = '', string $title = '', array $args = [] ): void {
		$status = isset( $args['response'] ) ? (int) $args['response'] : 500;
		throw new WpDieException( false, $message, $status );
	}
}
