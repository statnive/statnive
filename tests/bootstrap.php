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
		return trim( strip_tags( $str ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit test stub, WP not loaded.
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

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! function_exists( 'wp_hash' ) ) {
	function wp_hash( string $data, string $scheme = 'auth' ): string {
		// Stub: use hash_hmac with a fixed salt for deterministic unit tests.
		return hash_hmac( 'md5', $data, 'statnive-unit-test-salt' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data
	 */
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param mixed $value
	 */
	function update_option( string $option, $value, $autoload = null ): bool {
		$GLOBALS['statnive_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @return mixed
	 */
	function get_transient( string $transient ) {
		return $GLOBALS['statnive_test_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param mixed $value
	 */
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		$GLOBALS['statnive_test_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['statnive_test_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub with a test override: if a callable is registered at
	 * $GLOBALS['statnive_test_filters'][$hook_name], it is invoked with
	 * the default value. Otherwise the default passes through.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value ) {
		if ( isset( $GLOBALS['statnive_test_filters'][ $hook_name ] ) && is_callable( $GLOBALS['statnive_test_filters'][ $hook_name ] ) ) {
			return $GLOBALS['statnive_test_filters'][ $hook_name ]( $value );
		}
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
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing
	class WP_REST_Controller {
		/** @var string */
		protected $namespace = '';

		/** @var string */
		protected $rest_base = '';
	}
	// phpcs:enable
}

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
		throw new WpDieException( true, $data, $status_code ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test stub, throws not outputs.
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	/**
	 * @param mixed $data
	 */
	function wp_send_json_error( $data = null, int $status_code = 200 ): void {
		throw new WpDieException( false, $data, $status_code ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test stub, throws not outputs.
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * @param mixed $message
	 */
	function wp_die( $message = '', string $title = '', array $args = [] ): void {
		$status = isset( $args['response'] ) ? (int) $args['response'] : 500;
		throw new WpDieException( false, $message, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test stub, throws not outputs.
	}
}

/*
 * Additional WordPress function stubs for unit tests.
 */

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return false;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = [] ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = [] ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * @return false
	 */
	function wp_next_scheduled( string $hook, array $args = [] ) {
		return false;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = [] ): int {
		return 0;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * @param mixed $value
	 */
	function add_option( string $option, $value = '', string $deprecated = '', $autoload = 'yes' ): bool {
		return update_option( $option, $value );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, ...$args ): bool {
		return true;
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $file, $callback ): void {
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, $callback ): void {
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * @return string
	 */
	function get_bloginfo( string $show = '' ) {
		if ( 'version' === $show ) {
			return '6.9';
		}
		return '';
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( bool $hard = true ): void {
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( string $domain, $deprecated = false, string $plugin_rel_path = '' ): bool {
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( $file );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( strip_tags( $str ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit test stub, WP not loaded.
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $length = 24, bool $special_chars = true, bool $extra_special_chars = false ): string {
		return 'test_password_' . $length;
	}
}

if ( ! function_exists( 'deactivate_plugins' ) ) {
	function deactivate_plugins( $plugins, bool $silent = false, $network_wide = null ): void {
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/**
	 * @return array{basedir: string, baseurl: string, path: string, url: string, subdir: string, error: false}
	 */
	function wp_upload_dir(): array {
		$base = sys_get_temp_dir() . '/statnive-unit-uploads';
		return [
			'basedir' => $base,
			'baseurl' => 'http://example.test/uploads',
			'path'    => $base,
			'url'     => 'http://example.test/uploads',
			'subdir'  => '',
			'error'   => false,
		];
	}
}

/*
 * Statnive constants for unit tests.
 */

if ( ! defined( 'STATNIVE_VERSION' ) ) {
	define( 'STATNIVE_VERSION', '0.3.1' );
}

if ( ! defined( 'STATNIVE_FILE' ) ) {
	define( 'STATNIVE_FILE', __DIR__ . '/../statnive.php' );
}

if ( ! defined( 'STATNIVE_PATH' ) ) {
	define( 'STATNIVE_PATH', __DIR__ . '/../' );
}

if ( ! defined( 'STATNIVE_MIN_PHP' ) ) {
	define( 'STATNIVE_MIN_PHP', '8.0' );
}

if ( ! defined( 'STATNIVE_MIN_WP' ) ) {
	define( 'STATNIVE_MIN_WP', '6.2' );
}
