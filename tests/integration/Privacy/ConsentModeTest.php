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
use Statnive\Privacy\PrivacyManager;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for consent mode behaviors.
 *
 * @covers \Statnive\Privacy\ConsentMode
 * @covers \Statnive\Privacy\PrivacyManager
 */
final class ConsentModeTest extends WP_UnitTestCase {

	private HitController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		update_option( 'statnive_respect_dnt', false );
		update_option( 'statnive_respect_gpc', false );
		$this->controller = new HitController();
	}

	/**
	 * Build a standard tracking request.
	 *
	 * @param array<string, mixed> $overrides Payload overrides.
	 * @return WP_REST_Request
	 */
	private function build_request( array $overrides = [] ): WP_REST_Request {
		$defaults = [
			'resource_type'  => 'post',
			'resource_id'    => 42,
			'referrer'       => '',
			'screen_width'   => 1920,
			'screen_height'  => 1080,
			'language'       => 'en-US',
			'timezone'       => 'UTC',
			'signature'      => HmacValidator::generate( 'post', 42 ),
		];

		$payload = array_merge( $defaults, $overrides );

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		return $request;
	}

	/**
	 * @testdox Disabled-until-consent blocks tracking without consent signal
	 */
	public function test_disabled_until_consent_blocks_without_signal(): void {
		global $wpdb;

		update_option( 'statnive_consent_mode', ConsentMode::DISABLED_UNTIL_CONSENT );

		$visitors = TableRegistry::get( 'visitors' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );

		$response = $this->controller->create_item( $this->build_request() );

		$this->assertSame( 204, $response->get_status(), 'Endpoint should return 204 even when tracking is blocked by consent' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );

		$this->assertSame( $before, $after, 'No visitor rows should be created without consent signal' );
	}

	/**
	 * @testdox Cookieless mode records tracking without consent signal
	 */
	public function test_cookieless_mode_records_without_signal(): void {
		global $wpdb;

		update_option( 'statnive_consent_mode', ConsentMode::COOKIELESS );

		$visitors = TableRegistry::get( 'visitors' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );

		$response = $this->controller->create_item( $this->build_request() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );

		$this->assertSame( $before + 1, $after, 'Cookieless mode should create 1 visitor row without consent signal' );
	}

	/**
	 * @testdox Consent granted mid-session resumes tracking
	 */
	public function test_consent_granted_mid_session_resumes_tracking(): void {
		global $wpdb;

		update_option( 'statnive_consent_mode', ConsentMode::DISABLED_UNTIL_CONSENT );

		$visitors = TableRegistry::get( 'visitors' );
		$views    = TableRegistry::get( 'views' );

		// First request without consent -- blocked.
		$this->controller->create_item( $this->build_request() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$visitors_after_blocked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );
		$this->assertSame( 0, $visitors_after_blocked, 'First request without consent should not create any visitor rows' );

		// Second request with consent_granted flag.
		$response = $this->controller->create_item(
			$this->build_request( [ 'consent_granted' => true ] )
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$visitor_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );
		$view_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$views}`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 1, $visitor_count, 'Consent granted mid-session should create 1 visitor row' );
		$this->assertSame( 1, $view_count, 'Consent granted mid-session should create 1 view row' );
	}

	/**
	 * @testdox Consent revoked stops tracking immediately
	 */
	public function test_consent_revoked_stops_tracking_immediately(): void {
		global $wpdb;
		$views = TableRegistry::get( 'views' );

		// First, grant consent and track.
		update_option( 'statnive_consent_mode', ConsentMode::COOKIELESS );
		$request  = $this->build_request();
		$response = $this->controller->create_item( $request );
		$this->assertSame( 204, $response->get_status(), 'Tracking should work in cookieless mode' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$views_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$views}`" );

		// Now revoke consent.
		update_option( 'statnive_consent_mode', ConsentMode::DISABLED_UNTIL_CONSENT );
		$request2  = $this->build_request();
		$response2 = $this->controller->create_item( $request2 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$views_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$views}`" );

		$this->assertSame( $views_before, $views_after, 'No views should be recorded after consent is revoked' );
	}
}
