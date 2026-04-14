<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Database\DatabaseFactory;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Regression guard for the args-schema bug fixed in PR #11.
 *
 * PR #9 (commit c562ab4) added a REST args schema to HitController and
 * EventController as a Plugin Check / OpenAPI hint, but marked
 * resource_type / signature / event_name as required: true. The tracker
 * sends its JSON payload with Content-Type: text/plain to bypass the CORS
 * preflight, and WordPress's REST framework cannot parse text/plain bodies
 * into request args. The combination meant the framework rejected every
 * real tracker hit with HTTP 400 'rest_missing_callback_param' BEFORE
 * create_item() ever ran.
 *
 * The bug was invisible to the existing integration tests because they all
 * call $controller->create_item($request) directly, skipping the args
 * validation that only fires inside rest_get_server()->dispatch().
 *
 * This test ALWAYS goes through dispatch() so any future re-introduction
 * of `required: true` (or any other args constraint that text/plain
 * payloads can't satisfy) is caught by CI.
 *
 * @covers \Statnive\Api\HitController::register_routes
 * @covers \Statnive\Api\EventController::register_routes
 */
final class RestDispatchArgsRegressionTest extends WP_UnitTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		DatabaseFactory::create_tables();

		// Privacy mode must allow tracking for the success-path assertions.
		update_option( 'statnive_respect_dnt', false );
		update_option( 'statnive_respect_gpc', false );
		update_option( 'statnive_consent_mode', 'cookieless' );

		// Force REST routes to register against a fresh server instance —
		// this is the WP-core idiom used in the REST API test suite.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * Build a /hit request that mimics what the tracker actually sends:
	 * a JSON body delivered as Content-Type: text/plain.
	 *
	 * @param array<string, mixed> $overrides Payload overrides.
	 */
	private function build_hit_request( array $overrides = [] ): WP_REST_Request {
		$payload = array_merge(
			[
				'resource_type' => 'post',
				'resource_id'   => 1,
				'referrer'      => '',
				'screen_width'  => 1920,
				'screen_height' => 1080,
				'language'      => 'en-US',
				'timezone'      => 'UTC',
				'signature'     => HmacValidator::generate( 'post', 1 ),
			],
			$overrides
		);

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_header( 'Content-Type', 'text/plain' );
		$request->set_body( wp_json_encode( $payload ) );

		return $request;
	}

	/**
	 * Build an /event request mirroring the tracker's text/plain JSON.
	 *
	 * @param array<string, mixed> $overrides Payload overrides.
	 */
	private function build_event_request( array $overrides = [] ): WP_REST_Request {
		$payload = array_merge(
			[
				'event_name'    => 'cta_click',
				'resource_type' => 'post',
				'resource_id'   => 1,
				'signature'     => HmacValidator::generate( 'post', 1 ),
				'properties'    => [ 'label' => 'hero' ],
			],
			$overrides
		);

		$request = new WP_REST_Request( 'POST', '/statnive/v1/event' );
		$request->set_header( 'Content-Type', 'text/plain' );
		$request->set_body( wp_json_encode( $payload ) );

		return $request;
	}

	/**
	 * The route exists and the args schema is documented.
	 *
	 * If a future change drops the schema entirely, the documentation /
	 * OpenAPI hint goes with it — that's a separate failure mode worth
	 * catching alongside the required-flag regression.
	 */
	public function test_hit_route_is_registered_with_args_schema(): void {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/statnive/v1/hit', $routes );

		$route = $routes['/statnive/v1/hit'][0] ?? null;
		$this->assertNotNull( $route, '/statnive/v1/hit POST route handler missing' );
		$this->assertNotEmpty( $route['args'] ?? [], '/hit args schema must be present for documentation/OpenAPI' );
	}

	public function test_event_route_is_registered_with_args_schema(): void {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/statnive/v1/event', $routes );

		$route = $routes['/statnive/v1/event'][0] ?? null;
		$this->assertNotNull( $route, '/statnive/v1/event POST route handler missing' );
		$this->assertNotEmpty( $route['args'] ?? [], '/event args schema must be present for documentation/OpenAPI' );
	}

	/**
	 * No arg in the /hit schema may be marked required: true.
	 *
	 * The tracker delivers everything as text/plain, which the WP REST
	 * framework cannot parse into request args. Marking any field as
	 * required there means dispatch() rejects the request with
	 * `rest_missing_callback_param` BEFORE create_item() runs.
	 */
	public function test_hit_args_have_no_required_flags(): void {
		$routes = $this->server->get_routes();
		$args   = $routes['/statnive/v1/hit'][0]['args'] ?? [];

		$required = [];
		foreach ( $args as $name => $arg ) {
			if ( ! empty( $arg['required'] ) ) {
				$required[] = $name;
			}
		}

		$this->assertSame(
			[],
			$required,
			'No /hit arg may be marked required:true — text/plain payloads cannot be parsed by WP REST args validation, '
			. 'so this would reject every real tracker hit. Validate inside create_item() instead.'
		);
	}

	public function test_event_args_have_no_required_flags(): void {
		$routes = $this->server->get_routes();
		$args   = $routes['/statnive/v1/event'][0]['args'] ?? [];

		$required = [];
		foreach ( $args as $name => $arg ) {
			if ( ! empty( $arg['required'] ) ) {
				$required[] = $name;
			}
		}

		$this->assertSame(
			[],
			$required,
			'No /event arg may be marked required:true — see HitController test for the rationale.'
		);
	}

	/**
	 * The whole point: a valid text/plain tracker hit must reach
	 * create_item() through dispatch() and return 204, never 400.
	 *
	 * If `required: true` is ever re-introduced on resource_type or
	 * signature, this assertion fails first.
	 */
	public function test_dispatch_accepts_valid_text_plain_hit(): void {
		$response = $this->server->dispatch( $this->build_hit_request() );

		$this->assertNotSame(
			'rest_missing_callback_param',
			$response->get_data()['code'] ?? null,
			'Dispatch must not reject valid text/plain payload as missing required arg'
		);
		$this->assertSame( 204, $response->get_status(), 'Valid /hit must return 204 via dispatch()' );
	}

	public function test_dispatch_accepts_valid_text_plain_event(): void {
		$response = $this->server->dispatch( $this->build_event_request() );

		$this->assertNotSame(
			'rest_missing_callback_param',
			$response->get_data()['code'] ?? null,
			'Dispatch must not reject valid text/plain event payload as missing required arg'
		);
		$this->assertNotSame( 400, $response->get_status(), 'Valid /event must not return 400 via dispatch()' );
	}

	/**
	 * Bad-signature payload must still be rejected with 403 — the args
	 * fix should not weaken HMAC enforcement.
	 */
	public function test_dispatch_rejects_bad_signature_via_runtime_check(): void {
		$response = $this->server->dispatch(
			$this->build_hit_request( [ 'signature' => 'tampered-invalid-hmac' ] )
		);

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Empty body must still be rejected with 400 by the runtime
	 * PayloadValidator inside create_item().
	 */
	public function test_dispatch_rejects_empty_body_via_runtime_check(): void {
		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_header( 'Content-Type', 'text/plain' );
		$request->set_body( '{}' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}
}
