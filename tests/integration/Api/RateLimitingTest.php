<?php
/**
 * Generated from BDD scenarios (01-tracking-pipeline.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\HitController;
use Statnive\Database\DatabaseFactory;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for rate limiting on the tracking endpoint.
 *
 * @covers \Statnive\Api\HitController
 */
final class RateLimitingTest extends WP_UnitTestCase {

	private HitController $controller;

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		update_option( 'statnive_consent_mode', 'cookieless' );
		$this->controller = new HitController();
	}

	/**
	 * Build a valid tracking request.
	 *
	 * @param int $resource_id Resource ID.
	 * @return WP_REST_Request
	 */
	private function build_request( int $resource_id = 42 ): WP_REST_Request {
		$payload = [
			'resource_type'  => 'post',
			'resource_id'    => $resource_id,
			'referrer'       => '',
			'screen_width'   => 1920,
			'screen_height'  => 1080,
			'language'       => 'en-US',
			'timezone'       => 'UTC',
			'correlation_id' => $this->correlation_id,
			'signature'      => HmacValidator::generate( 'post', $resource_id ),
		];

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		return $request;
	}

	/**
	 * @testdox 429 after 60 requests per minute from same IP
	 */
	public function test_rate_limit_returns_429_after_60_requests(): void {
		$last_response = null;

		for ( $i = 1; $i <= 61; $i++ ) {
			$last_response = $this->controller->create_item( $this->build_request( $i ) );
		}

		$this->assertNotNull( $last_response, 'Last response should not be null after 61 requests' );
		$this->assertSame( 429, $last_response->get_status(), '61st request should return 429 Too Many Requests' );

		$data = $last_response->get_data();
		$this->assertSame( 'rate_limited', $data['code'], 'Error code should be "rate_limited"' );
	}

	/**
	 * @testdox Requests within rate limit succeed
	 */
	public function test_requests_within_rate_limit_succeed(): void {
		$response = $this->controller->create_item( $this->build_request() );

		$this->assertSame( 204, $response->get_status(), 'Single request within rate limit should return 204' );
	}
}
