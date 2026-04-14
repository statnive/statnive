<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Api\SettingsController;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Unit tests for SettingsController::sanitize_setting().
 *
 * The method is private, so we access it via reflection. This is acceptable
 * for pure-logic validation tests that have no WordPress side effects.
 */
#[CoversClass(SettingsController::class)]
final class SettingsSanitizationTest extends TestCase {

	private \ReflectionMethod $sanitize;
	private SettingsController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = new SettingsController();
		$ref = new \ReflectionClass(SettingsController::class);
		$this->sanitize = $ref->getMethod('sanitize_setting');
	}

	public function test_retention_days_clamps_below_30_to_30(): void {
		$result = $this->sanitize->invoke( $this->controller, 'retention_days', 5 );

		$this->assertSame( 30, $result );
	}

	public function test_retention_days_clamps_above_3650_to_3650(): void {
		$result = $this->sanitize->invoke( $this->controller, 'retention_days', 9999 );

		$this->assertSame( 3650, $result );
	}

	public function test_consent_mode_rejects_invalid_value(): void {
		$result = $this->sanitize->invoke( $this->controller, 'consent_mode', 'invalid-mode' );

		$this->assertSame( 'cookieless', $result, 'Invalid consent_mode should fall back to cookieless.' );
	}
}
