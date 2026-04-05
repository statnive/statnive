<?php
/**
 * Generated from BDD scenarios (12-licensing-feature-gates.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Licensing;

use Statnive\Cron\LicenseCheckJob;
use Statnive\Database\DatabaseFactory;
use Statnive\Licensing\ApiCommunicator;
use Statnive\Licensing\LicenseHelper;
use Statnive\Licensing\LicenseStatus;
use WP_UnitTestCase;

/**
 * Integration tests for the license activation and validation flow.
 *
 * @covers \Statnive\Licensing\LicenseHelper
 * @covers \Statnive\Licensing\LicenseStatus
 * @covers \Statnive\Cron\LicenseCheckJob
 */
final class LicenseFlowTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		LicenseHelper::remove_license();
		delete_transient( 'statnive_license_check_failures' );
	}

	public function tear_down(): void {
		LicenseHelper::remove_license();
		delete_transient( 'statnive_license_check_failures' );

		// Clean up options created during test.
		delete_option( 'statnive_license_key' );
		delete_option( 'statnive_license_key_enc' );
		delete_option( 'statnive_license_status' );
		delete_transient( 'statnive_license_check' );

		parent::tear_down();
	}

	/**
	 * @testdox Valid key activates and stores encrypted
	 */
	public function test_valid_key_activates_and_stores_encrypted(): void {
		$key = 'STRT-7F3A-9B2C-ABCD';

		LicenseHelper::store_license( $key );

		$this->assertTrue( LicenseHelper::has_license(), 'License should be present after storing a valid key' );

		// Verify the key can be decrypted.
		$decrypted = LicenseHelper::get_license_key();
		$this->assertSame( $key, $decrypted, 'Decrypted key should match the original key' );

		// Verify it's stored encrypted (raw option should not equal the key).
		$enc_b64 = get_option( 'statnive_license_key_enc' );
		$this->assertNotSame( $key, $enc_b64, 'Stored option should be encrypted (not plaintext)' );
	}

	/**
	 * @testdox Invalid key rejected with error (LicenseStatus::invalid)
	 */
	public function test_invalid_key_rejected(): void {
		$status = LicenseStatus::invalid();

		$this->assertSame( 'invalid', $status->status, 'Invalid license status should be "invalid"' );
		$this->assertSame( 'free', $status->plan_tier, 'Invalid license should fall back to "free" plan tier' );
		$this->assertFalse( $status->is_active(), 'Invalid license should not be active' );

		// Ensure no license data is persisted.
		$this->assertFalse( LicenseHelper::has_license(), 'No license data should be persisted for invalid key' );
	}

	/**
	 * @testdox Expired license triggers graceful degradation to free
	 */
	public function test_expired_license_degrades_to_free(): void {
		// Store a license and cache a valid professional status.
		LicenseHelper::store_license( 'STRT-7F3A-9B2C-ABCD' );
		LicenseHelper::cache_status(
			LicenseStatus::valid( 'professional', '2025-01-01', '****-ABCD' )
		);

		// Simulate expired status from API.
		$expired_status = LicenseStatus::expired( '****-ABCD' );
		LicenseHelper::cache_status( $expired_status );

		$cached = LicenseHelper::get_cached_status();

		$this->assertSame( 'expired', $cached->status, 'Cached status should be "expired"' );
		$this->assertSame( 'free', $cached->plan_tier, 'Expired license should degrade to "free" plan tier' );
	}

	/**
	 * @testdox 3 consecutive API failures trigger feature lock
	 */
	public function test_three_consecutive_failures_trigger_feature_lock(): void {
		// Store a license.
		LicenseHelper::store_license( 'STRT-1234-5678-WXYZ' );
		LicenseHelper::cache_status(
			LicenseStatus::valid( 'starter', '2027-06-15', '****-WXYZ' )
		);

		// Simulate 2 prior failures.
		set_transient( 'statnive_license_check_failures', 2, WEEK_IN_SECONDS );

		// Mock the API to return an error by intercepting the HTTP request.
		add_filter( 'pre_http_request', function () {
			return new \WP_Error( 'http_request_failed', 'Connection refused' );
		} );

		// Run the license check job — this should be the 3rd failure.
		LicenseCheckJob::run();

		$cached = LicenseHelper::get_cached_status();

		$this->assertSame( 'free', $cached->plan_tier, 'After 3 consecutive API failures plan should degrade to "free"' );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * @testdox Masked key shows last 4 characters
	 */
	public function test_masked_key_shows_last_4_characters(): void {
		LicenseHelper::store_license( 'STRT-7F3A-9B2C-ABCD' );

		$masked = LicenseHelper::get_masked_key();

		$this->assertSame( '****-ABCD', $masked, 'Masked key should show only last 4 characters' );
	}

	/**
	 * @testdox Free tier when no license present
	 */
	public function test_free_tier_when_no_license(): void {
		$status = LicenseHelper::get_cached_status();

		$this->assertSame( 'free', $status->status, 'Status should be "free" when no license is present' );
		$this->assertSame( 'free', $status->plan_tier, 'Plan tier should be "free" when no license is present' );
	}

	/**
	 * @testdox License status serialization round-trip
	 */
	public function test_license_status_serialization(): void {
		$original = LicenseStatus::valid( 'professional', '2027-06-15', '****-ABCD' );
		$array    = $original->to_array();
		$restored = LicenseStatus::from_array( $array );

		$this->assertSame( $original->status, $restored->status, 'Status should survive serialization round-trip' );
		$this->assertSame( $original->plan_tier, $restored->plan_tier, 'Plan tier should survive serialization round-trip' );
		$this->assertSame( $original->expires_at, $restored->expires_at, 'Expiry date should survive serialization round-trip' );
	}
}
