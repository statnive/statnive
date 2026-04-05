<?php
/**
 * Generated from BDD scenarios (07-privacy-compliance.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Privacy;

use Statnive\Api\HitController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Privacy\ConsentMode;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for consent-denied invariants.
 *
 * @covers \Statnive\Privacy\PrivacyManager
 * @covers \Statnive\Api\HitController
 */
final class ConsentInvariantsTest extends WP_UnitTestCase {

	private HitController $controller;

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		update_option( 'statnive_consent_mode', ConsentMode::DISABLED_UNTIL_CONSENT );
		$this->controller = new HitController();
	}

	/**
	 * @testdox Consent denied produces zero cookies, zero raw IPs, zero fingerprints
	 */
	public function test_consent_denied_zero_cookies_zero_raw_ips_zero_fingerprints(): void {
		global $wpdb;

		$payload = [
			'resource_type'  => 'post',
			'resource_id'    => 42,
			'referrer'       => '',
			'screen_width'   => 1920,
			'screen_height'  => 1080,
			'language'       => 'en-US',
			'timezone'       => 'UTC',
			'correlation_id' => $this->correlation_id,
			'signature'      => HmacValidator::generate( 'post', 42 ),
		];

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$response = $this->controller->create_item( $request );

		// Response should have no Set-Cookie headers.
		$headers = $response->get_headers();
		$this->assertArrayNotHasKey( 'Set-Cookie', $headers, 'Consent-denied response should not set any cookies' );

		// No visitor/session rows should be created.
		$visitors = TableRegistry::get( 'visitors' );
		$sessions = TableRegistry::get( 'sessions' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$visitor_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$visitors}` WHERE correlation_id = %s",
				$this->correlation_id
			)
		);
		$session_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$sessions}` WHERE correlation_id = %s",
				$this->correlation_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 0, $visitor_count, 'No visitor rows should be created when consent is denied' );
		$this->assertSame( 0, $session_count, 'No session rows should be created when consent is denied' );

		// Sessions table should have no raw IP addresses (ip_hash should remain empty).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$raw_ips = $wpdb->get_col(
			"SELECT ip_hash FROM `{$sessions}` WHERE ip_hash IS NOT NULL AND ip_hash != ''"
		);
		$this->assertEmpty( $raw_ips, 'No raw IP hashes should exist in sessions table when consent is denied' );
	}
}
