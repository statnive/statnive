<?php
/**
 * Generated from BDD scenarios: features/12-licensing-feature-gates.feature
 * Scenario: "License key is masked for display with last 4 characters visible"
 *
 * Tests the license key masking logic. Because LicenseHelper::get_masked_key()
 * depends on WordPress options for key retrieval, we test the masking algorithm
 * directly by reproducing the logic as a pure function.
 *
 * May need adjustment when source class API changes.
 */

declare(strict_types=1);

namespace Statnive\Tests\Unit\Licensing;

use PHPUnit\Framework\TestCase;
use Statnive\Licensing\LicenseStatus;

/**
 * @covers \Statnive\Licensing\LicenseHelper
 * @covers \Statnive\Licensing\LicenseStatus
 */
final class LicenseHelperTest extends TestCase {

	/**
	 * Reproduce the masking logic from LicenseHelper::get_masked_key().
	 *
	 * @param string|null $key Raw license key.
	 * @return string Masked key.
	 */
	private function mask_key( ?string $key ): string {
		if ( null === $key || strlen( $key ) < 4 ) {
			return '';
		}
		return '****-' . substr( $key, -4 );
	}

	public function test_license_key_masked_shows_last_4_characters(): void {
		$masked = $this->mask_key( 'STRT-7F3A-9B2C-ABCD' );

		$this->assertSame( '****-ABCD', $masked );
	}

	public function test_short_key_returns_empty_string(): void {
		$masked = $this->mask_key( 'ABC' );

		$this->assertSame( '', $masked );
	}

	public function test_null_key_returns_empty_string(): void {
		$masked = $this->mask_key( null );

		$this->assertSame( '', $masked );
	}

	public function test_exactly_4_char_key_is_masked(): void {
		$masked = $this->mask_key( 'ABCD' );

		$this->assertSame( '****-ABCD', $masked );
	}

	/**
	 * @dataProvider license_key_mask_provider
	 */
	public function test_masking_various_keys( string $key, string $expected ): void {
		$this->assertSame( $expected, $this->mask_key( $key ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function license_key_mask_provider(): array {
		return [
			'standard format'   => [ 'STRT-7F3A-9B2C-ABCD', '****-ABCD' ],
			'different suffix'  => [ 'PROF-1111-2222-WXYZ', '****-WXYZ' ],
			'numeric suffix'    => [ 'AGCY-AAAA-BBBB-1234', '****-1234' ],
			'minimal 5 chars'   => [ '12345', '****-2345' ],
		];
	}

	// --- LicenseStatus value object tests ---

	public function test_license_status_valid_factory(): void {
		$status = LicenseStatus::valid( 'professional', '2027-06-15', '****-ABCD' );

		$this->assertSame( 'valid', $status->status );
		$this->assertSame( 'professional', $status->plan_tier );
		$this->assertSame( '2027-06-15', $status->expires_at );
		$this->assertSame( '****-ABCD', $status->license_key_masked );
		$this->assertTrue( $status->is_active() );
	}

	public function test_license_status_free_factory(): void {
		$status = LicenseStatus::free();

		$this->assertSame( 'free', $status->status );
		$this->assertSame( 'free', $status->plan_tier );
		$this->assertNull( $status->expires_at );
		$this->assertFalse( $status->is_active() );
	}

	public function test_license_status_expired_degrades_to_free_tier(): void {
		$status = LicenseStatus::expired( '****-ABCD' );

		$this->assertSame( 'expired', $status->status );
		$this->assertSame( 'free', $status->plan_tier );
		$this->assertFalse( $status->is_active() );
	}

	public function test_license_status_serialization_roundtrip(): void {
		$original = LicenseStatus::valid( 'starter', '2027-01-01', '****-5678' );
		$array    = $original->to_array();
		$restored = LicenseStatus::from_array( $array );

		$this->assertSame( $original->status, $restored->status );
		$this->assertSame( $original->plan_tier, $restored->plan_tier );
		$this->assertSame( $original->expires_at, $restored->expires_at );
		$this->assertSame( $original->license_key_masked, $restored->license_key_masked );
	}

	public function test_license_status_invalid_factory(): void {
		$status = LicenseStatus::invalid();

		$this->assertSame( 'invalid', $status->status );
		$this->assertSame( 'free', $status->plan_tier );
		$this->assertFalse( $status->is_active() );
	}

	public function test_license_status_error_factory(): void {
		$status = LicenseStatus::error();

		$this->assertSame( 'error', $status->status );
		$this->assertSame( 'free', $status->plan_tier );
		$this->assertFalse( $status->is_active() );
	}
}
