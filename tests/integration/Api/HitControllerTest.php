<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\HitController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the HitController REST endpoint.
 *
 * @covers \Statnive\Api\HitController
 */
final class HitControllerTest extends WP_UnitTestCase {

	private HitController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->controller = new HitController();
	}

	private function build_request( array $data = [] ): WP_REST_Request {
		$defaults = [
			'resource_type' => 'post',
			'resource_id'   => 1,
			'referrer'      => '',
			'screen_width'  => 1920,
			'screen_height' => 1080,
			'language'      => 'en-US',
			'timezone'      => 'UTC',
			'signature'     => HmacValidator::generate( 'post', 1 ),
		];

		$payload = array_merge( $defaults, $data );

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		return $request;
	}

	public function test_valid_hit_returns_204(): void {
		$request  = $this->build_request();
		$response = $this->controller->create_item( $request );

		$this->assertSame( 204, $response->get_status() );
	}

	public function test_valid_hit_creates_database_records(): void {
		global $wpdb;

		$request = $this->build_request();
		$this->controller->create_item( $request );

		$visitors_table = TableRegistry::get( 'visitors' );
		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$visitor_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors_table}`" );
		$session_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$sessions_table}`" );
		$view_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$views_table}`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 1, $visitor_count );
		$this->assertSame( 1, $session_count );
		$this->assertSame( 1, $view_count );
	}

	public function test_invalid_signature_returns_403(): void {
		$request  = $this->build_request( [ 'signature' => 'tampered-invalid-signature' ] );
		$response = $this->controller->create_item( $request );

		$this->assertSame( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'invalid_signature', $data['code'] );
	}

	public function test_missing_fields_returns_400(): void {
		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( [ 'resource_id' => 1 ] ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$response = $this->controller->create_item( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_invalid_json_returns_400(): void {
		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( 'not-valid-json{{{' );
		$request->set_header( 'Content-Type', 'text/plain' );

		$response = $this->controller->create_item( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_cdn_header_fallback_populates_country_on_fresh_install(): void {
		global $wpdb;

		$_SERVER['REMOTE_ADDR']          = '203.0.113.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5';
		$_SERVER['HTTP_CF_IPCOUNTRY']    = 'DE';

		try {
			$this->controller->create_item( $this->build_request() );

			$sessions_table  = TableRegistry::get( 'sessions' );
			$countries_table = TableRegistry::get( 'countries' );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$country_id = $wpdb->get_var( "SELECT country_id FROM `{$sessions_table}` LIMIT 1" );
			$this->assertNotNull( $country_id, 'CDN header fallback must populate sessions.country_id' );
			$this->assertGreaterThan( 0, (int) $country_id );

			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT code, name FROM `{$countries_table}` WHERE id = %d", (int) $country_id ),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery

			$this->assertIsArray( $row );
			$this->assertSame( 'DE', $row['code'] );
			$this->assertSame( 'Germany', $row['name'] );
		} finally {
			unset(
				$_SERVER['REMOTE_ADDR'],
				$_SERVER['HTTP_X_FORWARDED_FOR'],
				$_SERVER['HTTP_CF_IPCOUNTRY']
			);
		}
	}

	public function test_no_cdn_header_leaves_country_null(): void {
		global $wpdb;

		$_SERVER['REMOTE_ADDR']          = '203.0.113.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5';

		try {
			$this->controller->create_item( $this->build_request() );

			$sessions_table = TableRegistry::get( 'sessions' );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$country_id = $wpdb->get_var( "SELECT country_id FROM `{$sessions_table}` LIMIT 1" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery

			$this->assertNull( $country_id, 'Without a CDN header and without MaxMind, country_id must stay NULL' );
		} finally {
			unset(
				$_SERVER['REMOTE_ADDR'],
				$_SERVER['HTTP_X_FORWARDED_FOR']
			);
		}
	}
}
