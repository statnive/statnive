<?php

declare(strict_types=1);

namespace Statnive\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Api\AjaxFallback;
use Statnive\Api\HitController;
use Statnive\Database\SchemaMaintainer;
use Statnive\Frontend\FrontendHandler;

/**
 * Core Service Provider.
 *
 * Registers foundational services needed on every request.
 * Additional providers (Admin, Tracking) will be added in later tasks.
 */
final class CoreServiceProvider implements ServiceProvider {

	/**
	 * Register core service factories.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function register( ServiceContainer $container ): void {
		// Configuration manager — reads from wp_options.
		$container->register(
			'config',
			static function (): object {
				return new class() {
					/**
					 * Get a plugin option with default fallback.
					 *
					 * @param string $key     Option key (without statnive_ prefix).
					 * @param mixed  $fallback Default value.
					 * @return mixed
					 */
					public function get( string $key, mixed $fallback = null ): mixed {
						return get_option( "statnive_{$key}", $fallback );
					}

					/**
					 * Set a plugin option.
					 *
					 * @param string $key   Option key (without statnive_ prefix).
					 * @param mixed  $value Option value.
					 */
					public function set( string $key, mixed $value ): bool {
						return update_option( "statnive_{$key}", $value );
					}
				};
			}
		);

		// Logger — writes to WordPress debug.log.
		$container->register(
			'logger',
			static function (): object {
				return new class() {
					/**
					 * Log a message if WP_DEBUG_LOG is enabled.
					 *
					 * @param string $message Log message.
					 * @param string $level   Log level (info, warning, error).
					 */
					public function log( string $message, string $level = 'info' ): void {
						if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log( sprintf( '[Statnive][%s] %s', strtoupper( $level ), $message ) );
						}
					}
				};
			}
		);

		// REST API hit controller.
		$container->register(
			'hit_controller',
			static function (): object {
				return new HitController();
			}
		);
	}

	/**
	 * Bootstrap core services.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function boot( ServiceContainer $container ): void {
		// Schema drift detection on admin_init.
		SchemaMaintainer::init();

		// Register REST API routes.
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$controller = $container->get( 'hit_controller' );
				if ( $controller instanceof HitController ) {
					$controller->register_routes();
				}
			}
		);

		// Register AJAX fallback endpoint.
		AjaxFallback::init();

		// Enqueue tracker script on frontend.
		if ( ! is_admin() ) {
			FrontendHandler::init();
		}
	}
}
