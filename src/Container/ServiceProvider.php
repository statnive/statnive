<?php

declare(strict_types=1);

namespace Statnive\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service Provider interface.
 *
 * Service providers are responsible for registering and bootstrapping
 * a group of related services with the container.
 *
 * Two-phase initialization:
 *   1. register() — declare factories (no side effects)
 *   2. boot()     — hook into WordPress, start services
 */
interface ServiceProvider {

	/**
	 * Register service factories with the container.
	 *
	 * This method should only call $container->register() or ->singleton().
	 * It must NOT cause side effects (no WordPress hooks, no DB queries).
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function register( ServiceContainer $container ): void;

	/**
	 * Bootstrap services after all providers have been registered.
	 *
	 * This is the place to hook into WordPress, initialize services
	 * conditionally, and wire up event listeners.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function boot( ServiceContainer $container ): void;
}
