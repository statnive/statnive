<?php
/**
 * Generated from BDD scenarios (01-tracking-pipeline.feature) — adjust when source classes are implemented.
 */

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
 * Integration tests for hit endpoint input validation.
 *
 * @covers \Statnive\Api\HitController
 */
final class HitValidationTest extends WP_UnitTestCase {

	private HitController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		update_option( 'statnive_respect_dnt', false );
		update_option( 'statnive_respect_gpc', false );
		update_option( 'statnive_consent_mode', 'cookieless' );
		$this->controller = new HitController();
	}

	/**
	 * Build a valid tracking request with HMAC signature.
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

		// Re-sign if resource fields changed.
		if ( isset( $overrides['resource_type'] ) || isset( $overrides['resource_id'] ) ) {
			$payload['signature'] = HmacValidator::generate(
				$payload['resource_type'],
				(int) $payload['resource_id']
			);
		}

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		return $request;
	}

	/**
	 * @testdox Invalid HMAC signature returns 403 and records zero rows
	 */
	public function test_invalid_hmac_returns_403_zero_rows(): void {
		global $wpdb;

		$payload = [
			'resource_type'  => 'post',
			'resource_id'    => 42,
			'referrer'       => '',
			'screen_width'   => 1920,
			'screen_height'  => 1080,
			'language'       => 'en-US',
			'timezone'       => 'UTC',
			'signature'      => 'deadbeef0000000000000000000000000000000000000000000000000000abcd',
		];

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$visitors = TableRegistry::get( 'visitors' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );

		$response = $this->controller->create_item( $request );

		$this->assertSame( 403, $response->get_status(), 'Invalid HMAC signature should return 403 Forbidden' );
		$data = $response->get_data();
		$this->assertSame( 'invalid_signature', $data['code'], 'Error code should be "invalid_signature"' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );
		$this->assertSame( $before, $after, 'No visitor rows should be created for invalid HMAC requests' );
	}

	/**
	 * @testdox Missing required fields return 400
	 * @dataProvider missing_fields_provider
	 *
	 * @param array<string, mixed> $payload Incomplete payload.
	 */
	public function test_missing_required_fields_return_400( array $payload ): void {
		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$response = $this->controller->create_item( $request );

		$this->assertSame( 400, $response->get_status(), 'Missing required fields should return 400 Bad Request' );
	}

	/**
	 * Data provider for missing field variants.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function missing_fields_provider(): array {
		return [
			'missing resource_type' => [
				[
					'resource_id' => 42,
					'signature'   => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
				],
			],
			'missing signature' => [
				[
					'resource_type' => 'post',
					'resource_id'   => 42,
				],
			],
			'missing resource_type and signature' => [
				[
					'resource_id' => 42,
				],
			],
		];
	}

	/**
	 * @testdox Missing resource_id is accepted (defaults to 0 via absint)
	 *
	 * HitController uses absint($data['resource_id'] ?? 0) for resource_id,
	 * meaning a missing resource_id defaults to 0 and is not validated as required.
	 * The endpoint will attempt to process the request (may return 204 or 403
	 * depending on HMAC validation of resource_type + 0).
	 */
	public function test_missing_resource_id_accepted(): void {
		$payload = [
			'resource_type' => 'post',
			'signature'     => HmacValidator::generate( 'post', 0 ),
		];

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$response = $this->controller->create_item( $request );

		// resource_id defaults to 0 via absint, and the HMAC is signed for post|0,
		// so the signature matches and request proceeds to tracking (returns 204).
		$this->assertSame( 204, $response->get_status(), 'Missing resource_id should default to 0 and proceed if HMAC matches' );
	}

	/**
	 * @testdox Missing signature returns 400
	 */
	public function test_missing_signature_returns_400(): void {
		$payload = [
			'resource_type' => 'post',
			'resource_id'   => 42,
		];

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$response = $this->controller->create_item( $request );

		$this->assertSame( 400, $response->get_status(), 'Missing signature should return 400 Bad Request' );
	}

	/**
	 * @testdox Tracking overhead within 25ms threshold
	 */
	public function test_tracking_overhead_within_threshold(): void {
		$request = $this->build_request();

		$start    = microtime( true );
		$response = $this->controller->create_item( $request );
		$elapsed  = microtime( true ) - $start;

		$this->assertLessThan(
			0.025,
			$elapsed,
			sprintf( 'Tracking endpoint overhead %.1fms exceeds 25ms threshold', $elapsed * 1000 )
		);
	}
}
