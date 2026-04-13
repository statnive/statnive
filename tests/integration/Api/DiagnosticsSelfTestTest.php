<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\DiagnosticsController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for the self-test diagnostics endpoint.
 *
 * Covers production scenarios:
 *  - Self-test passes on a healthy installation
 *  - Self-test detects missing tables
 *
 * @covers \Statnive\Api\DiagnosticsController::run_self_test
 */
final class DiagnosticsSelfTestTest extends WP_UnitTestCase {

	private DiagnosticsController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->controller = new DiagnosticsController();

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * @testdox Self-test passes on a healthy installation with all tables present
	 */
	public function test_self_test_passes_on_healthy_install(): void {
		global $wpdb;

		// Insert a view row so read-back step has data.
		$views_table = TableRegistry::get( 'views' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $views_table, [
			'session_id' => 1,
			'resource_id' => 1,
			'viewed_at'  => current_time( 'mysql', true ),
		] );

		// Schedule the expected cron job so step 4 passes.
		if ( ! wp_next_scheduled( 'statnive_daily_data_purge' ) ) {
			wp_schedule_single_event( time() + 3600, 'statnive_daily_data_purge' );
		}

		$request  = new WP_REST_Request( 'POST', '/statnive/v1/self-test' );
		$response = $this->controller->run_self_test( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Healthy install should return 200' );
		$this->assertTrue( $data['ok'], 'Self-test should pass on healthy install' );
		$this->assertArrayHasKey( 'steps', $data );
		$this->assertTrue( $data['steps']['schema_view']['ok'], 'Schema view step should pass' );
		$this->assertTrue( $data['steps']['read_back']['ok'], 'Read-back step should pass' );
	}

	/**
	 * @testdox Self-test detects missing views table
	 */
	public function test_self_test_detects_missing_table(): void {
		global $wpdb;

		$views_table = TableRegistry::get( 'views' );

		// Drop the views table to simulate corruption.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$views_table}`" );

		$request  = new WP_REST_Request( 'POST', '/statnive/v1/self-test' );
		$response = $this->controller->run_self_test( $request );
		$data     = $response->get_data();

		// The self-test may return 200 or 207 depending on implementation.
		// Key assertion: read_back step should fail because table is gone.
		$this->assertFalse( $data['steps']['read_back']['ok'], 'Read-back should fail when views table is missing' );

		// Recreate the table to not break other tests.
		DatabaseFactory::create_tables();
	}
}
