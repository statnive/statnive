<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Container;

use PHPUnit\Framework\TestCase;
use Statnive\Container\ServiceContainer;
use stdClass;

/**
 * Unit tests for ServiceContainer.
 *
 * @covers \Statnive\Container\ServiceContainer
 */
final class ServiceContainerTest extends TestCase {

	protected function setUp(): void {
		ServiceContainer::reset_instance();
	}

	protected function tearDown(): void {
		ServiceContainer::reset_instance();
	}

	public function test_get_instance_returns_singleton(): void {
		$c1 = ServiceContainer::get_instance();
		$c2 = ServiceContainer::get_instance();

		$this->assertSame( $c1, $c2 );
	}

	public function test_register_and_get_returns_service(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'test', fn() => new stdClass() );

		$service = $container->get( 'test' );

		$this->assertInstanceOf( stdClass::class, $service );
	}

	public function test_get_returns_same_instance_on_repeated_calls(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'test', fn() => new stdClass() );

		$first  = $container->get( 'test' );
		$second = $container->get( 'test' );

		$this->assertSame( $first, $second );
	}

	public function test_get_returns_null_for_unregistered_service(): void {
		$container = ServiceContainer::get_instance();

		$this->assertNull( $container->get( 'nonexistent' ) );
	}

	public function test_alias_resolves_to_target(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'original', fn() => new stdClass() );
		$container->alias( 'shortcut', 'original' );

		$original = $container->get( 'original' );
		$aliased  = $container->get( 'shortcut' );

		$this->assertSame( $original, $aliased );
	}

	public function test_has_returns_true_for_registered_service(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'test', fn() => new stdClass() );

		$this->assertTrue( $container->has( 'test' ) );
		$this->assertFalse( $container->has( 'missing' ) );
	}

	public function test_has_resolves_aliases(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'real', fn() => new stdClass() );
		$container->alias( 'fake', 'real' );

		$this->assertTrue( $container->has( 'fake' ) );
	}

	public function test_factory_receives_container(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'ctx', fn( ServiceContainer $c ) => (object) [ 'container' => $c ] );

		$service = $container->get( 'ctx' );

		$this->assertSame( $container, $service->container );
	}

	public function test_singleton_registers_preinstantiated_service(): void {
		$container = ServiceContainer::get_instance();
		$instance  = new stdClass();
		$container->singleton( 'pre', $instance );

		$this->assertSame( $instance, $container->get( 'pre' ) );
	}

	public function test_reset_clears_instances_keeps_factories(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'test', fn() => new stdClass() );

		$before = $container->get( 'test' );
		$container->reset();
		$after = $container->get( 'test' );

		$this->assertNotSame( $before, $after );
		$this->assertInstanceOf( stdClass::class, $after );
	}

	public function test_reset_instance_creates_fresh_container(): void {
		$c1 = ServiceContainer::get_instance();
		$c1->register( 'test', fn() => new stdClass() );

		ServiceContainer::reset_instance();
		$c2 = ServiceContainer::get_instance();

		$this->assertNotSame( $c1, $c2 );
		$this->assertFalse( $c2->has( 'test' ) );
	}

	public function test_magic_get_accesses_services(): void {
		$container = ServiceContainer::get_instance();
		$container->register( 'magic', fn() => new stdClass() );

		$service = $container->magic; // @phpstan-ignore-line

		$this->assertInstanceOf( stdClass::class, $service );
	}
}
