<?php

declare(strict_types=1);

namespace Statnive\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Privacy\ConsentApiIntegration;
use Statnive\Privacy\PrivacyEraser;
use Statnive\Privacy\PrivacyExporter;
use Statnive\Privacy\PrivacyPolicyGenerator;
use Statnive\Privacy\SiteHealthIntegration;

/**
 * Privacy Service Provider.
 *
 * Registers WordPress Privacy API hooks (exporter, eraser, policy),
 * consent enforcement, Site Health integration, and Consent API bridge.
 */
final class PrivacyServiceProvider implements ServiceProvider {

	/**
	 * Register privacy service factories.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function register( ServiceContainer $container ): void {
		// Services are static-method-based; no container registration needed.
	}

	/**
	 * Bootstrap privacy services.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function boot( ServiceContainer $container ): void {
		// WordPress Privacy API hooks.
		PrivacyExporter::register();
		PrivacyEraser::register();
		PrivacyPolicyGenerator::register();

		// WP Site Health integration.
		SiteHealthIntegration::register();

		// WP Consent API integration.
		ConsentApiIntegration::register();

		// Register Privacy Audit REST endpoint.
		add_action(
			'rest_api_init',
			static function (): void {
				$controller = new \Statnive\Api\PrivacyAuditController();
				$controller->register_routes();
			}
		);
	}
}
