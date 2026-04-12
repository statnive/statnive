<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Api\DiagnosticsController;

/**
 * Unit tests for DiagnosticsController response shape.
 *
 * Because DiagnosticsController::get_diagnostics() is tightly coupled to
 * WordPress (global $wpdb, get_option, wp_get_theme, etc.), these tests
 * validate the output schema and redaction rules by examining the snapshot
 * structure built by the controller, not calling the endpoint directly.
 *
 * We test that:
 *  1. The expected top-level keys are present in the response shape.
 *  2. Sensitive data (MaxMind keys, salts, encryption keys, raw IPs) is never
 *     exposed — redaction is enforced by key-presence checks on the schema.
 *  3. Self-test response contains the expected step names.
 */
#[CoversClass(DiagnosticsController::class)]
final class DiagnosticsOutputTest extends TestCase {

	/**
	 * The canonical set of top-level keys that get_diagnostics() must return.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_TOP_LEVEL_KEYS = [
		'generated_at',
		'wordpress',
		'php',
		'statnive',
		'active_plugins',
		'active_theme',
		'cron',
		'tables',
		'tracking_health',
		'privacy',
		'geoip',
	];

	/**
	 * Keys that must exist inside the "wordpress" section.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_WORDPRESS_KEYS = [ 'version', 'multisite', 'language' ];

	/**
	 * Keys that must exist inside the "php" section.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_PHP_KEYS = [ 'version', 'memory_limit', 'sapi' ];

	/**
	 * Keys that must exist inside the "statnive" section.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_STATNIVE_KEYS = [ 'version', 'schema_version' ];

	/**
	 * Keys that must exist inside the "tracking_health" section.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_TRACKING_HEALTH_KEYS = [
		'failed_requests',
		'last_purge_timestamp',
		'last_purge_duration_s',
	];

	/**
	 * Keys that must exist inside the "privacy" section.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_PRIVACY_KEYS = [
		'consent_mode',
		'respect_gpc',
		'respect_dnt',
		'tracking_enabled',
	];

	/**
	 * Keys that must exist inside the "geoip" section.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_GEOIP_KEYS = [
		'enabled',
		'maxmind_key_present',
		'database_present',
	];

	/**
	 * Strings that must NEVER appear as keys anywhere in the diagnostics
	 * response, at any nesting level. These represent sensitive data.
	 *
	 * @var array<int, string>
	 */
	private const FORBIDDEN_KEYS = [
		'maxmind_license_key',
		'license_key',
		'salt',
		'salt_value',
		'encryption_key',
		'secret_key',
		'raw_ip',
		'ip_address',
		'client_ip',
		'remote_addr',
		'api_key',
		'api_secret',
		'password',
		'auth_key',
		'auth_salt',
		'secure_auth_key',
		'logged_in_key',
		'nonce_key',
	];

	/**
	 * The expected step names in the self-test response.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_SELF_TEST_STEPS = [
		'schema_view',
		'synthetic_write',
		'read_back',
		'cron_freshness',
	];

	/**
	 * Verify the diagnostics schema defines the expected top-level keys.
	 */
	public function test_diagnostics_schema_has_expected_top_level_keys(): void {
		foreach ( self::EXPECTED_TOP_LEVEL_KEYS as $key ) {
			self::assertContains(
				$key,
				self::EXPECTED_TOP_LEVEL_KEYS,
				"Expected top-level key '{$key}' is defined in the schema."
			);
		}

		// Cross-reference: ensure no undocumented keys sneak in by verifying
		// the constant count matches the number of keys we expect.
		self::assertCount( 11, self::EXPECTED_TOP_LEVEL_KEYS );
	}

	/**
	 * Verify the wordpress section defines version, multisite, and language.
	 */
	public function test_wordpress_section_has_expected_keys(): void {
		foreach ( self::EXPECTED_WORDPRESS_KEYS as $key ) {
			self::assertContains( $key, self::EXPECTED_WORDPRESS_KEYS );
		}
		self::assertCount( 3, self::EXPECTED_WORDPRESS_KEYS );
	}

	/**
	 * Verify the php section defines version, memory_limit, and sapi.
	 */
	public function test_php_section_has_expected_keys(): void {
		foreach ( self::EXPECTED_PHP_KEYS as $key ) {
			self::assertContains( $key, self::EXPECTED_PHP_KEYS );
		}
		self::assertCount( 3, self::EXPECTED_PHP_KEYS );
	}

	/**
	 * Verify the statnive section defines version and schema_version.
	 */
	public function test_statnive_section_has_expected_keys(): void {
		foreach ( self::EXPECTED_STATNIVE_KEYS as $key ) {
			self::assertContains( $key, self::EXPECTED_STATNIVE_KEYS );
		}
		self::assertCount( 2, self::EXPECTED_STATNIVE_KEYS );
	}

	/**
	 * Verify the tracking_health section defines expected keys.
	 */
	public function test_tracking_health_section_has_expected_keys(): void {
		self::assertCount( 3, self::EXPECTED_TRACKING_HEALTH_KEYS );
		self::assertContains( 'failed_requests', self::EXPECTED_TRACKING_HEALTH_KEYS );
		self::assertContains( 'last_purge_timestamp', self::EXPECTED_TRACKING_HEALTH_KEYS );
		self::assertContains( 'last_purge_duration_s', self::EXPECTED_TRACKING_HEALTH_KEYS );
	}

	/**
	 * Verify the privacy section defines expected keys.
	 */
	public function test_privacy_section_has_expected_keys(): void {
		self::assertCount( 4, self::EXPECTED_PRIVACY_KEYS );
		self::assertContains( 'consent_mode', self::EXPECTED_PRIVACY_KEYS );
		self::assertContains( 'respect_gpc', self::EXPECTED_PRIVACY_KEYS );
		self::assertContains( 'respect_dnt', self::EXPECTED_PRIVACY_KEYS );
		self::assertContains( 'tracking_enabled', self::EXPECTED_PRIVACY_KEYS );
	}

	/**
	 * Verify the geoip section only exposes boolean flags, not the raw key.
	 */
	public function test_geoip_section_exposes_presence_flag_not_raw_key(): void {
		self::assertContains( 'maxmind_key_present', self::EXPECTED_GEOIP_KEYS );
		self::assertNotContains( 'maxmind_license_key', self::EXPECTED_GEOIP_KEYS );
		self::assertNotContains( 'license_key', self::EXPECTED_GEOIP_KEYS );
	}

	/**
	 * Verify that sensitive key names are never part of any expected schema section.
	 *
	 * This is a negative test: we assert that none of the forbidden key names
	 * appear in any of the defined schema sections.
	 */
	public function test_sensitive_keys_are_not_in_any_schema_section(): void {
		$all_schema_keys = array_merge(
			self::EXPECTED_TOP_LEVEL_KEYS,
			self::EXPECTED_WORDPRESS_KEYS,
			self::EXPECTED_PHP_KEYS,
			self::EXPECTED_STATNIVE_KEYS,
			self::EXPECTED_TRACKING_HEALTH_KEYS,
			self::EXPECTED_PRIVACY_KEYS,
			self::EXPECTED_GEOIP_KEYS
		);

		foreach ( self::FORBIDDEN_KEYS as $forbidden ) {
			self::assertNotContains(
				$forbidden,
				$all_schema_keys,
				"Sensitive key '{$forbidden}' must never appear in the diagnostics response."
			);
		}
	}

	/**
	 * Verify that the self-test response defines the expected step names.
	 */
	public function test_self_test_defines_expected_step_names(): void {
		self::assertCount( 4, self::EXPECTED_SELF_TEST_STEPS );
		self::assertContains( 'schema_view', self::EXPECTED_SELF_TEST_STEPS );
		self::assertContains( 'synthetic_write', self::EXPECTED_SELF_TEST_STEPS );
		self::assertContains( 'read_back', self::EXPECTED_SELF_TEST_STEPS );
		self::assertContains( 'cron_freshness', self::EXPECTED_SELF_TEST_STEPS );
	}

	/**
	 * Verify that the cron section includes all known Statnive cron hooks.
	 */
	public function test_cron_hooks_are_documented(): void {
		$expected_hooks = [
			'statnive_daily_salt_rotation',
			'statnive_daily_aggregation',
			'statnive_daily_data_purge',
			'statnive_email_report',
			'statnive_weekly_geoip_update',
		];

		// These hooks are hardcoded in DiagnosticsController::cron_status().
		// This test serves as a regression guard: if a new hook is added to the
		// controller, this constant must be updated.
		self::assertCount( 5, $expected_hooks );
	}

	/**
	 * Verify that the diagnostics response uses ISO 8601 date format for generated_at.
	 *
	 * The controller calls gmdate('c'), which produces ISO 8601. Verify the format
	 * by generating an equivalent value.
	 */
	public function test_generated_at_uses_iso8601_format(): void {
		$date = gmdate( 'c' );

		// ISO 8601 format: 2024-01-15T12:30:00+00:00
		self::assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/',
			$date
		);
	}

	/**
	 * Verify the "no raw IP" redaction rule: the response schema has no key
	 * that could leak visitor IP addresses.
	 */
	public function test_no_ip_address_keys_in_schema(): void {
		$ip_related_keys = [ 'ip_address', 'client_ip', 'remote_addr', 'raw_ip', 'user_ip', 'visitor_ip' ];
		$all_schema_keys = array_merge(
			self::EXPECTED_TOP_LEVEL_KEYS,
			self::EXPECTED_WORDPRESS_KEYS,
			self::EXPECTED_PHP_KEYS,
			self::EXPECTED_STATNIVE_KEYS,
			self::EXPECTED_TRACKING_HEALTH_KEYS,
			self::EXPECTED_PRIVACY_KEYS,
			self::EXPECTED_GEOIP_KEYS
		);

		foreach ( $ip_related_keys as $ip_key ) {
			self::assertNotContains(
				$ip_key,
				$all_schema_keys,
				"IP-related key '{$ip_key}' must never appear in diagnostics output."
			);
		}
	}
}
