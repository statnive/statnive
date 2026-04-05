<?php

declare(strict_types=1);

namespace Statnive\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Admin\AdminBarWidget;
use Statnive\Admin\AdminMenuManager;
use Statnive\Admin\ReactHandler;

/**
 * Admin Service Provider.
 *
 * Registers the WordPress admin menu page and enqueues the React SPA.
 * Only boots on admin pages (is_admin() check).
 */
final class AdminServiceProvider implements ServiceProvider {

	/**
	 * Register admin service factories.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function register( ServiceContainer $container ): void {
		// Admin services are static — no container registration needed.
	}

	/**
	 * Bootstrap admin services.
	 *
	 * Only runs in wp-admin context.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function boot( ServiceContainer $container ): void {
		if ( ! is_admin() ) {
			return;
		}

		AdminMenuManager::init();
		ReactHandler::init();
		AdminBarWidget::init();
	}
}
