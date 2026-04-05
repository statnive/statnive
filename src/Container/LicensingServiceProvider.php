<?php

declare(strict_types=1);

namespace Statnive\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Cron\LicenseCheckJob;
use Statnive\Feature\FeatureGate;

/**
 * Licensing Service Provider.
 *
 * Registers licensing services, feature gates, and REST endpoints
 * for license management and capabilities.
 */
final class LicensingServiceProvider implements ServiceProvider {

	/**
	 * Register licensing service factories.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function register( ServiceContainer $container ): void {
		// All services are static-method-based; no container registration needed.
	}

	/**
	 * Bootstrap licensing services.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function boot( ServiceContainer $container ): void {
		// Register license check cron.
		LicenseCheckJob::init();

		// Register REST API endpoints.
		add_action(
			'rest_api_init',
			static function (): void {
				$controllers = [
					new \Statnive\Api\LicenseController(),
					new \Statnive\Api\CapabilitiesController(),
				];

				foreach ( $controllers as $controller ) {
					$controller->register_routes();
				}
			}
		);

		// Inject capabilities into React config on admin pages.
		add_filter(
			'statnive_dashboard_config',
			static function ( array $config ): array {
				$config['capabilities'] = FeatureGate::get_capabilities();
				return $config;
			}
		);
	}
}
