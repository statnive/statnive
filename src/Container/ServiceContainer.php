<?php

declare(strict_types=1);

namespace Statnive\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service Container for Statnive.
 *
 * Provides lazy-loaded singleton services for optimal performance.
 * Services are only instantiated when first accessed via get().
 */
final class ServiceContainer {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registered service factories.
	 *
	 * @var array<string, callable(self): object>
	 */
	private array $factories = [];

	/**
	 * Cached service instances.
	 *
	 * @var array<string, object>
	 */
	private array $instances = [];

	/**
	 * Service name aliases.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = [];

	/**
	 * Private constructor — use getInstance().
	 */
	private function __construct() {
	}

	/**
	 * Get the singleton container instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register a lazy-loaded service factory.
	 *
	 * The factory receives the container as its argument and should return
	 * the service instance. The factory is called only once — the result
	 * is cached for subsequent get() calls.
	 *
	 * @param string                 $id      Service identifier.
	 * @param callable(self): object $factory Factory function.
	 */
	public function register( string $id, callable $factory ): self {
		$this->factories[ $id ] = $factory;
		return $this;
	}

	/**
	 * Register a pre-instantiated singleton service.
	 *
	 * @param string $id       Service identifier.
	 * @param object $instance The service instance.
	 */
	public function singleton( string $id, object $instance ): self {
		$this->instances[ $id ] = $instance;
		return $this;
	}

	/**
	 * Register an alias for a service.
	 *
	 * @param string $alias  Alias name.
	 * @param string $target Target service ID.
	 */
	public function alias( string $alias, string $target ): self {
		$this->aliases[ $alias ] = $target;
		return $this;
	}

	/**
	 * Get a service by ID.
	 *
	 * Resolves aliases, returns cached instances, or creates from factory.
	 *
	 * @param string $id Service identifier.
	 * @return object|null The service instance, or null if not registered.
	 */
	public function get( string $id ): ?object {
		// Resolve alias chain.
		$id = $this->resolve_alias( $id );

		// Return cached instance.
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Create from factory and cache.
		if ( isset( $this->factories[ $id ] ) ) {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
			return $this->instances[ $id ];
		}

		return null;
	}

	/**
	 * Check if a service is registered.
	 *
	 * @param string $id Service identifier.
	 */
	public function has( string $id ): bool {
		$id = $this->resolve_alias( $id );

		return isset( $this->instances[ $id ] ) || isset( $this->factories[ $id ] );
	}

	/**
	 * Magic property access for services.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function __get( string $name ): ?object {
		return $this->get( $name );
	}

	/**
	 * Reset all cached instances.
	 *
	 * Useful for test isolation. Factories remain registered.
	 */
	public function reset(): void {
		$this->instances = [];
	}

	/**
	 * Fully clear the container — factories, instances, and aliases.
	 *
	 * Useful for test teardown.
	 */
	public function flush(): void {
		$this->factories = [];
		$this->instances = [];
		$this->aliases   = [];
	}

	/**
	 * Reset the singleton instance.
	 *
	 * Only for testing — allows a fresh container in each test.
	 */
	public static function reset_instance(): void {
		if ( null !== self::$instance ) {
			self::$instance->flush();
			self::$instance = null;
		}
	}

	/**
	 * Resolve an alias to its target service ID.
	 *
	 * Handles alias chains (alias → alias → service).
	 *
	 * @param string $id Service identifier or alias.
	 * @return string Resolved service ID.
	 */
	private function resolve_alias( string $id ): string {
		$seen = [];
		while ( isset( $this->aliases[ $id ] ) ) {
			if ( isset( $seen[ $id ] ) ) {
				break; // Prevent circular alias loops.
			}
			$seen[ $id ] = true;
			$id          = $this->aliases[ $id ];
		}

		return $id;
	}
}
