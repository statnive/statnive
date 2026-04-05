<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\HitController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_UnitTestCase;

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
}
