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

	public function test_consent_mode_full_is_rejected_and_coerced_to_cookieless(): void {
		// 'full' was removed as a valid mode — behaviorally it was identical to
		// cookieless, so legacy installs get silently migrated rather than broken.
		$result = $this->sanitize->invoke( $this->controller, 'consent_mode', 'full' );

		$this->assertSame( 'cookieless', $result );
	}

	public function test_retention_mode_accepts_forever(): void {
		$result = $this->sanitize->invoke( $this->controller, 'retention_mode', 'forever' );

		$this->assertSame( 'forever', $result );
	}

	public function test_retention_mode_accepts_delete(): void {
		$result = $this->sanitize->invoke( $this->controller, 'retention_mode', 'delete' );

		$this->assertSame( 'delete', $result );
	}

	public function test_retention_mode_rejects_invalid_value_falls_back_to_forever(): void {
		$result = $this->sanitize->invoke( $this->controller, 'retention_mode', 'whatever' );

		$this->assertSame( 'forever', $result );
	}

	public function test_retention_mode_accepts_archive(): void {
		// 'archive' is a valid enum value — currently aliased to 'delete' in
		// DataPurger but enumerated separately so a future archival
		// implementation can ship without a settings migration.
		$result = $this->sanitize->invoke( $this->controller, 'retention_mode', 'archive' );

		$this->assertSame( 'archive', $result );
	}

	public function test_excluded_ips_preserves_multiline_input(): void {
		$input = "10.0.0.0/8\n192.168.1.1\n2001:db8::/32";

		$result = $this->sanitize->invoke( $this->controller, 'excluded_ips', $input );

		// sanitize_textarea_field preserves newlines but normalises CRLF and
		// strips control characters — three lines must round-trip intact.
		$this->assertSame( $input, $result );
	}

	public function test_excluded_roles_non_array_coerced_to_empty_array(): void {
		// A scalar slipping past the REST framework's `type: array` check
		// must be neutralised at the sanitiser layer (defence-in-depth) so
		// downstream `array_intersect()` callers never see a string.
		$result = $this->sanitize->invoke( $this->controller, 'excluded_roles', 'administrator' );

		$this->assertSame( [], $result );
	}

	public function test_tracking_enabled_coerces_truthy_and_falsy_to_bool(): void {
		$true_result  = $this->sanitize->invoke( $this->controller, 'tracking_enabled', '1' );
		$false_result = $this->sanitize->invoke( $this->controller, 'tracking_enabled', '' );

		$this->assertTrue( $true_result );
		$this->assertFalse( $false_result );
	}
}
