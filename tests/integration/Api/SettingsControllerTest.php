<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\SettingsController;
use Statnive\Service\GeoIPDownloader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the SettingsController REST endpoint.
 *
 * Goes through rest_get_server()->dispatch() so framework-layer
 * validation (enum, min/max, sanitize_callback) runs alongside the
 * handler's own defence-in-depth checks. Pinning this end-to-end
 * keeps the masked-license-key roundtrip, the missing_license_key
 * 400, the GeoIP enable/disable cron transitions, and the
 * permissions gate from regressing without a test failure.
 *
 * @covers \Statnive\Api\SettingsController
 */
final class SettingsControllerTest extends WP_UnitTestCase {

	private const ROUTE = '/statnive/v1/settings';

	private const OPTION_KEYS = [
		'statnive_tracking_enabled',
		'statnive_respect_dnt',
		'statnive_respect_gpc',
		'statnive_consent_mode',
		'statnive_retention_days',
		'statnive_retention_mode',
		'statnive_excluded_ips',
		'statnive_excluded_roles',
		'statnive_geoip_enabled',
		'statnive_maxmind_license_key',
	];

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		// Block any outbound HTTP so GeoIPDownloader::enable()'s download()
		// call cannot hit the real MaxMind endpoint during tests.
		add_filter( 'pre_http_request', [ $this, 'block_http' ], 10, 3 );

		// Force REST routes to register against a fresh server instance.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		// Default to admin so most tests don't need to repeat the auth setup.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Reset every option this controller touches so tests start neutral.
		foreach ( self::OPTION_KEYS as $key ) {
			delete_option( $key );
		}
		wp_clear_scheduled_hook( GeoIPDownloader::CRON_HOOK );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ], 10 );
		wp_clear_scheduled_hook( GeoIPDownloader::CRON_HOOK );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * pre_http_request short-circuit for the test environment.
	 *
	 * @param mixed                $preempt Filter preempt flag.
	 * @param array<string, mixed> $args    Request args.
	 * @param string               $url     Target URL.
	 * @return WP_Error
	 */
	public function block_http( $preempt, $args, $url ): WP_Error {
		return new WP_Error( 'http_blocked_in_tests', 'Outbound HTTP blocked in tests: ' . $url );
	}

	private function build_put( array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return $request;
	}

	private function build_get(): WP_REST_Request {
		return new WP_REST_Request( 'GET', self::ROUTE );
	}

	public function test_get_settings_requires_manage_options(): void {
		// Logged-out → 401.
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( $this->build_get() );
		$this->assertSame( 401, $response->get_status(), 'Logged-out users must get 401.' );

		// Subscriber → 403.
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$response = $this->server->dispatch( $this->build_get() );
		$this->assertSame( 403, $response->get_status(), 'Subscribers must get 403.' );

		// Admin → 200.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$response = $this->server->dispatch( $this->build_get() );
		$this->assertSame( 200, $response->get_status(), 'Admins must get 200.' );
	}

	public function test_put_rejects_non_array_body(): void {
		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		// JSON `null` decodes to null, which is not an array. Empty body
		// has the same effect — get_json_params() returns null either way.
		$request->set_body( 'null' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Invalid body.', $data['message'] ?? null );
	}

	public function test_put_geoip_enabled_without_license_key_returns_missing_license_key_400(): void {
		$response = $this->server->dispatch( $this->build_put( [ 'geoip_enabled' => true ] ) );

		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'missing_license_key', $data['code'] ?? null );
	}

	public function test_put_geoip_enabled_with_license_key_schedules_weekly_cron(): void {
		update_option( 'statnive_maxmind_license_key', 'test-license-abcdef' );

		$response = $this->server->dispatch( $this->build_put( [ 'geoip_enabled' => true ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( (bool) get_option( 'statnive_geoip_enabled' ) );
		$this->assertNotFalse(
			wp_next_scheduled( GeoIPDownloader::CRON_HOOK ),
			'Enabling GeoIP must schedule the weekly cron.'
		);
	}

	public function test_get_returns_masked_placeholder_when_license_key_set(): void {
		update_option( 'statnive_maxmind_license_key', 'real-key-abc' );

		$response = $this->server->dispatch( $this->build_get() );
		$data     = $response->get_data();

		$this->assertSame( SettingsController::MASKED_PLACEHOLDER, $data['maxmind_license_key'] );
	}

	public function test_get_returns_empty_string_when_license_key_unset(): void {
		$response = $this->server->dispatch( $this->build_get() );
		$data     = $response->get_data();

		$this->assertSame( '', $data['maxmind_license_key'] );
	}

	public function test_put_with_masked_placeholder_does_not_overwrite_stored_key(): void {
		update_option( 'statnive_maxmind_license_key', 'real-key-abc' );

		$response = $this->server->dispatch(
			$this->build_put( [ 'maxmind_license_key' => SettingsController::MASKED_PLACEHOLDER ] )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'real-key-abc', get_option( 'statnive_maxmind_license_key' ) );
	}

	public function test_put_with_plaintext_key_overwrites_stored_key(): void {
		update_option( 'statnive_maxmind_license_key', 'real-key-abc' );

		$response = $this->server->dispatch(
			$this->build_put( [ 'maxmind_license_key' => 'new-key-xyz' ] )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'new-key-xyz', get_option( 'statnive_maxmind_license_key' ) );
	}

	public function test_geoip_disable_unschedules_cron(): void {
		// Start from the enabled+scheduled state.
		update_option( 'statnive_maxmind_license_key', 'test-license-abcdef' );
		update_option( 'statnive_geoip_enabled', true );
		wp_schedule_event( time(), 'weekly', GeoIPDownloader::CRON_HOOK );

		$this->assertNotFalse(
			wp_next_scheduled( GeoIPDownloader::CRON_HOOK ),
			'Cron must be scheduled before the disable transition.'
		);

		$response = $this->server->dispatch( $this->build_put( [ 'geoip_enabled' => false ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( (bool) get_option( 'statnive_geoip_enabled' ) );
		$this->assertFalse(
			wp_next_scheduled( GeoIPDownloader::CRON_HOOK ),
			'Disabling GeoIP must clear the weekly cron.'
		);
	}

	public function test_unknown_keys_in_body_are_silently_dropped(): void {
		$response = $this->server->dispatch(
			$this->build_put(
				[
					'tracking_enabled' => false,
					'evil_key'         => 'pwn',
				]
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( (bool) get_option( 'statnive_tracking_enabled' ) );
		// `evil_key` must not be persisted under any name. The map only
		// translates whitelisted keys, so this is the closest assertion
		// to "no surprise option appeared" without enumerating all keys.
		$this->assertFalse( get_option( 'evil_key', false ) );
		$this->assertFalse( get_option( 'statnive_evil_key', false ) );
	}

	/**
	 * @dataProvider rest_framework_rejection_provider
	 *
	 * @param array<string, mixed> $body Invalid PUT body.
	 */
	public function test_rest_framework_rejects_invalid_input( array $body ): void {
		$response = $this->server->dispatch( $this->build_put( $body ) );

		// rest_validate_request_arg returns rest_invalid_param for both
		// enum mismatch (consent_mode) and out-of-range numeric (retention_days),
		// so this single assertion shape covers every framework-layer reject.
		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_invalid_param', $data['code'] ?? null );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function rest_framework_rejection_provider(): array {
		return [
			'invalid consent_mode enum'  => [ [ 'consent_mode' => 'bogus' ] ],
			'retention_days below 30'    => [ [ 'retention_days' => 5 ] ],
		];
	}

	public function test_partial_put_preserves_unmodified_settings(): void {
		// Pre-seed every persisted option so we can prove the others survive.
		update_option( 'statnive_tracking_enabled', true );
		update_option( 'statnive_respect_dnt', true );
		update_option( 'statnive_respect_gpc', true );
		update_option( 'statnive_consent_mode', 'cookieless' );
		update_option( 'statnive_retention_days', 365 );
		update_option( 'statnive_retention_mode', 'delete' );
		update_option( 'statnive_excluded_ips', "10.0.0.0/8\n192.168.1.1" );
		update_option( 'statnive_excluded_roles', [ 'administrator' ] );
		update_option( 'statnive_geoip_enabled', false );
		update_option( 'statnive_maxmind_license_key', 'preserved-key' );

		$response = $this->server->dispatch( $this->build_put( [ 'respect_dnt' => false ] ) );

		$this->assertSame( 200, $response->get_status() );

		// Only the targeted option flipped.
		$this->assertFalse( (bool) get_option( 'statnive_respect_dnt' ) );

		// Everything else is byte-for-byte intact.
		$this->assertTrue( (bool) get_option( 'statnive_tracking_enabled' ) );
		$this->assertTrue( (bool) get_option( 'statnive_respect_gpc' ) );
		$this->assertSame( 'cookieless', get_option( 'statnive_consent_mode' ) );
		$this->assertSame( 365, (int) get_option( 'statnive_retention_days' ) );
		$this->assertSame( 'delete', get_option( 'statnive_retention_mode' ) );
		$this->assertSame( "10.0.0.0/8\n192.168.1.1", get_option( 'statnive_excluded_ips' ) );
		$this->assertSame( [ 'administrator' ], get_option( 'statnive_excluded_roles' ) );
		$this->assertFalse( (bool) get_option( 'statnive_geoip_enabled' ) );
		$this->assertSame( 'preserved-key', get_option( 'statnive_maxmind_license_key' ) );
	}
}
