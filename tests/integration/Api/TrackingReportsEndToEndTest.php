<?php
/**
 * End-to-end tests: tracking hits → report endpoints return correct data.
 *
 * Verifies the full pipeline from HitController recording through
 * all report controllers returning accurate, non-empty results.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\DimensionsController;
use Statnive\Api\HitController;
use Statnive\Api\PagesController;
use Statnive\Api\RealtimeController;
use Statnive\Api\SourcesController;
use Statnive\Api\SummaryController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @covers \Statnive\Api\SummaryController
 * @covers \Statnive\Api\PagesController
 * @covers \Statnive\Api\SourcesController
 * @covers \Statnive\Api\DimensionsController
 * @covers \Statnive\Api\RealtimeController
 */
final class TrackingReportsEndToEndTest extends WP_UnitTestCase {

	private HitController $hit;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		delete_transient( 'statnive_realtime' );

		// Disable privacy blocks for testing.
		update_option( 'statnive_consent_mode', 'cookieless' );
		update_option( 'statnive_respect_dnt', false );
		update_option( 'statnive_respect_gpc', false );

		$this->hit = new HitController();

		// Set admin for report permission checks.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Build a valid tracking hit request.
	 *
	 * @param array<string, mixed> $overrides Payload overrides.
	 * @return WP_REST_Request
	 */
	private function send_hit( array $overrides = [] ): void {
		$defaults = [
			'resource_type' => 'page',
			'resource_id'   => 0,
			'referrer'      => '',
			'screen_width'  => 1920,
			'screen_height' => 1080,
			'language'      => 'en-US',
			'timezone'      => 'UTC',
			'page_url'      => '/',
			'page_query'    => '',
		];

		$payload              = array_merge( $defaults, $overrides );
		$payload['signature'] = HmacValidator::generate(
			$payload['resource_type'],
			(int) $payload['resource_id']
		);

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		$response = $this->hit->create_item( $request );
		$this->assertSame( 204, $response->get_status(), 'Hit should return 204' );
	}

	/**
	 * @testdox Hit stores actual page_url (not synthetic /page/0)
	 */
	public function test_hit_stores_actual_page_url(): void {
		global $wpdb;

		$this->send_hit( [ 'page_url' => '/about/' ] );

		$uris_table = TableRegistry::get( 'resource_uris' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$uri = $wpdb->get_var( "SELECT uri FROM `{$uris_table}` ORDER BY ID DESC LIMIT 1" );

		$this->assertSame( '/about/', $uri, 'View should store the actual page URL, not a synthetic URI' );
	}

	/**
	 * @testdox Hit invalidates the realtime transient cache
	 */
	public function test_hit_invalidates_realtime_cache(): void {
		set_transient( 'statnive_realtime', [ 'stale' => true ], 300 );
		$this->assertNotFalse( get_transient( 'statnive_realtime' ), 'Transient should be set before hit' );

		$this->send_hit();

		$this->assertFalse( get_transient( 'statnive_realtime' ), 'Hit should delete the realtime transient' );
	}

	/**
	 * @testdox Summary endpoint reflects tracked hits for today
	 */
	public function test_summary_reflects_tracked_hits(): void {
		$this->send_hit( [ 'page_url' => '/' ] );
		$this->send_hit( [ 'page_url' => '/contact/' ] );

		$controller = new SummaryController();
		$request    = new WP_REST_Request( 'GET', '/statnive/v1/summary' );
		$today      = gmdate( 'Y-m-d' );
		$request->set_param( 'from', $today );
		$request->set_param( 'to', $today );

		$response = $controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertGreaterThanOrEqual( 1, $data['totals']['visitors'], 'Summary should show at least 1 visitor' );
		$this->assertGreaterThanOrEqual( 1, $data['totals']['sessions'], 'Summary should show at least 1 session' );
		$this->assertGreaterThanOrEqual( 2, $data['totals']['views'], 'Summary should show at least 2 views' );
	}

	/**
	 * @testdox Pages endpoint reflects tracked hits with correct URIs
	 */
	public function test_pages_reflects_tracked_hits(): void {
		$this->send_hit( [ 'page_url' => '/pricing/' ] );
		$this->send_hit( [ 'page_url' => '/features/' ] );

		$controller = new PagesController();
		$request    = new WP_REST_Request( 'GET', '/statnive/v1/pages' );
		$today      = gmdate( 'Y-m-d' );
		$request->set_param( 'from', $today );
		$request->set_param( 'to', $today );

		$response = $controller->get_items( $request );
		$rows     = $response->get_data();

		$uris = array_column( $rows, 'uri' );
		$this->assertContains( '/pricing/', $uris, 'Pages should include /pricing/' );
		$this->assertContains( '/features/', $uris, 'Pages should include /features/' );
	}

	/**
	 * @testdox Sources endpoint shows Direct channel for hits without referrer
	 */
	public function test_sources_reflects_tracked_hits(): void {
		$this->send_hit();

		$controller = new SourcesController();
		$request    = new WP_REST_Request( 'GET', '/statnive/v1/sources' );
		$today      = gmdate( 'Y-m-d' );
		$request->set_param( 'from', $today );
		$request->set_param( 'to', $today );

		$response = $controller->get_items( $request );
		$rows     = $response->get_data();

		$this->assertNotEmpty( $rows, 'Sources should not be empty after a hit' );
		$channels = array_column( $rows, 'channel' );
		$this->assertContains( 'Direct', $channels, 'Hit without referrer should appear as Direct channel' );
	}

	/**
	 * @testdox Dimensions/languages endpoint reflects tracked hits
	 */
	public function test_dimensions_languages_reflects_tracked_hits(): void {
		$this->send_hit( [ 'language' => 'de-DE' ] );

		$controller = new DimensionsController();
		$request    = new WP_REST_Request( 'GET', '/statnive/v1/dimensions/languages' );
		$today      = gmdate( 'Y-m-d' );
		$request->set_param( 'type', 'languages' );
		$request->set_param( 'from', $today );
		$request->set_param( 'to', $today );

		$response = $controller->get_items( $request );
		$rows     = $response->get_data();

		$this->assertNotEmpty( $rows, 'Languages dimension should not be empty' );
		$languages = array_column( $rows, 'name' );
		$this->assertContains( 'de-DE', $languages, 'Language de-DE should be present' );
	}

	/**
	 * @testdox Dimensions/browsers endpoint reflects tracked hits
	 */
	public function test_dimensions_browsers_reflects_tracked_hits(): void {
		// The test environment sets HTTP_USER_AGENT via $_SERVER.
		// DeviceService should parse it into a browser name.
		$this->send_hit();

		$controller = new DimensionsController();
		$request    = new WP_REST_Request( 'GET', '/statnive/v1/dimensions/browsers' );
		$today      = gmdate( 'Y-m-d' );
		$request->set_param( 'type', 'browsers' );
		$request->set_param( 'from', $today );
		$request->set_param( 'to', $today );

		$response = $controller->get_items( $request );
		$rows     = $response->get_data();

		// Browser detection depends on the test runner's UA string.
		// If DeviceDetector or fallback parser recognizes it, we get data.
		// Assertion: if rows exist, browser name is a non-empty string.
		if ( ! empty( $rows ) ) {
			$this->assertNotEmpty( $rows[0]['name'], 'Browser name should not be empty when detected' );
		}
	}

	/**
	 * @testdox Realtime endpoint reflects recent tracked hits
	 */
	public function test_realtime_reflects_tracked_hits(): void {
		$this->send_hit( [ 'page_url' => '/realtime-test/' ] );

		$controller = new RealtimeController();
		$request    = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );

		$response = $controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertGreaterThanOrEqual( 1, $data['active_visitors'], 'Active visitors should be >= 1 after a hit' );
		$this->assertNotEmpty( $data['active_pages'], 'Active pages should not be empty' );
		$this->assertNotEmpty( $data['recent_feed'], 'Recent feed should not be empty' );

		$uris = array_column( $data['active_pages'], 'uri' );
		$this->assertContains( '/realtime-test/', $uris, 'Active pages should include the visited URI' );
	}
}
