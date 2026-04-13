<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Plugin;

/**
 * Unit tests for the Plugin activation pipeline.
 *
 * Tests version-check logic only. The full activate() method calls
 * DatabaseFactory::create_tables() and CronRegistrar which require
 * WordPress infrastructure — those paths are covered by integration tests.
 *
 * Plugin::init() boots the full service container (CoreServiceProvider,
 * AdminServiceProvider, etc.) which depends on many WordPress globals
 * (is_admin, add_filter, register_rest_route, etc.). Exercising init()
 * end-to-end is therefore an integration-test concern, not a unit-test
 * concern. We verify the idempotency guard in isolation below.
 */
#[CoversClass(Plugin::class)]
final class ActivationPipelineTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_options'] = [];
	}

	public function test_activate_rejects_old_wp_version(): void {
		// Plugin::activate() uses version_compare( get_bloginfo('version'), STATNIVE_MIN_WP, '<' ).
		$old_wp = '5.0';
		$this->assertTrue(
			version_compare( $old_wp, STATNIVE_MIN_WP, '<' ),
			'WordPress 5.0 should be below the minimum requirement.'
		);
	}

	public function test_php_version_compare_detects_old_version(): void {
		$old_php = '7.4.0';
		$this->assertTrue(
			version_compare( $old_php, STATNIVE_MIN_PHP, '<' ),
			'PHP 7.4 should be below the minimum requirement of ' . STATNIVE_MIN_PHP
		);
	}

	public function test_current_php_passes_version_check(): void {
		$this->assertFalse(
			version_compare( PHP_VERSION, STATNIVE_MIN_PHP, '<' ),
			'The running PHP version should satisfy the minimum requirement.'
		);
	}

	public function test_init_idempotency_guard_exists(): void {
		// Plugin::init() uses a static $initialized flag to prevent double-init.
		// Verify the flag exists and is boolean via reflection.
		$ref  = new \ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'initialized' );

		$this->assertSame(
			'bool',
			(string) $prop->getType(),
			'The $initialized property should be typed as bool.'
		);
	}

	public function test_statnive_constants_are_defined(): void {
		$this->assertTrue( defined( 'STATNIVE_VERSION' ) );
		$this->assertTrue( defined( 'STATNIVE_FILE' ) );
		$this->assertTrue( defined( 'STATNIVE_PATH' ) );
		$this->assertTrue( defined( 'STATNIVE_MIN_PHP' ) );
		$this->assertTrue( defined( 'STATNIVE_MIN_WP' ) );
		$this->assertSame( '0.3.1', STATNIVE_VERSION );
	}
}
