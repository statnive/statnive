<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\AjaxFallback;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Security\HmacValidator;
use WP_UnitTestCase;

/**
 * Integration tests for the AJAX fallback endpoint.
 *
 * @covers \Statnive\Api\AjaxFallback
 */
final class AjaxFallbackTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		update_option( 'statnive_consent_mode', 'cookieless' );
		AjaxFallback::init();
	}

	public function test_ajax_actions_are_registered(): void {
		$this->assertNotFalse( has_action( 'wp_ajax_statnive_hit' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_statnive_hit' ) );
	}

	public function test_init_registers_both_hooks(): void {
		// Remove any existing hooks first.
		remove_all_actions( 'wp_ajax_statnive_hit' );
		remove_all_actions( 'wp_ajax_nopriv_statnive_hit' );

		AjaxFallback::init();

		$this->assertSame( 10, has_action( 'wp_ajax_statnive_hit', [ AjaxFallback::class, 'handle' ] ) );
		$this->assertSame( 10, has_action( 'wp_ajax_nopriv_statnive_hit', [ AjaxFallback::class, 'handle' ] ) );
	}

	/**
	 * @testdox Tables are empty before AJAX processing
	 */
	public function test_tables_empty_before_ajax_processing(): void {
		global $wpdb;

		$visitors = TableRegistry::get( 'visitors' );
		$views    = TableRegistry::get( 'views' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$visitor_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$visitors}`" );
		$view_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$views}`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 0, $visitor_count, 'Visitors table should be empty before any AJAX processing' );
		$this->assertSame( 0, $view_count, 'Views table should be empty before any AJAX processing' );
	}

	/**
	 * @testdox AJAX handler callback is callable
	 */
	public function test_ajax_handler_is_callable(): void {
		$this->assertTrue( is_callable( [ AjaxFallback::class, 'handle' ] ), 'AjaxFallback::handle should be a callable method' );
	}

	/**
	 * @testdox Tracking overhead within 25ms threshold
	 *
	 * Note: AjaxFallback::handle() calls wp_send_json_* which invokes wp_die(),
	 * so we cannot call it directly in test. Instead, we measure the equivalent
	 * REST endpoint via HitController as a proxy for AJAX overhead.
	 */
	public function test_tracking_overhead_within_threshold(): void {
		$controller = new \Statnive\Api\HitController();

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

		$request = new \WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$start    = microtime( true );
		$response = $controller->create_item( $request );
		$elapsed  = microtime( true ) - $start;

		$this->assertLessThan(
			0.025,
			$elapsed,
			sprintf( 'AJAX fallback tracking overhead %.1fms exceeds 25ms threshold', $elapsed * 1000 )
		);
	}
}
