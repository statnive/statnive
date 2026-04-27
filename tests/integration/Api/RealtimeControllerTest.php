<?php
/**
 * Generated from BDD scenarios (11-realtime-email-reports.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\RealtimeController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use WP_REST_Request;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the real-time analytics REST endpoint.
 *
 * @covers \Statnive\Api\RealtimeController
 */
final class RealtimeControllerTest extends WP_UnitTestCase {

	private RealtimeController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		delete_transient( 'statnive_realtime' );

		$this->controller = new RealtimeController();

		// Set current user to admin for permission checks.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Insert test session and view data for real-time queries.
	 *
	 * @param string $uri          Page URI.
	 * @param string $country_code Country code.
	 * @param string $browser_name Browser name.
	 * @param int    $minutes_ago  How many minutes ago the view occurred.
	 * @return int Session ID.
	 */
	private function insert_active_visitor( string $uri = '/pricing', string $country_code = 'US', string $browser_name = 'Chrome', int $minutes_ago = 1 ): int {
		global $wpdb;

		$visitors  = TableRegistry::get( 'visitors' );
		$sessions  = TableRegistry::get( 'sessions' );
		$views     = TableRegistry::get( 'views' );
		$uris      = TableRegistry::get( 'resource_uris' );
		$countries = TableRegistry::get( 'countries' );
		$browsers  = TableRegistry::get( 'device_browsers' );

		$time = gmdate( 'Y-m-d H:i:s', time() - ( $minutes_ago * 60 ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $visitors, [
			'hash'       => random_bytes( 8 ),
			'created_at' => $time,
		] );
		$visitor_id = (int) $wpdb->insert_id;

		// Insert country dimension.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$countries}` (code, name) VALUES (%s, %s)",
				$country_code,
				$country_code
			)
		);
		$country_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$countries}` WHERE code = %s", $country_code )
		);

		// Insert browser dimension.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$browsers}` (name) VALUES (%s)",
				$browser_name
			)
		);
		$browser_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$browsers}` WHERE name = %s", $browser_name )
		);

		// Insert session.
		$wpdb->insert( $sessions, [
			'visitor_id'        => $visitor_id,
			'started_at'        => $time,
			'ended_at'          => null,
			'total_views'       => 1,
			'country_id'        => $country_id,
			'device_browser_id' => $browser_id,
		] );
		$session_id = (int) $wpdb->insert_id;

		// Insert resource URI.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$uris}` (uri, uri_hash) VALUES (%s, %d)",
				$uri,
				crc32( $uri )
			)
		);
		$uri_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$uris}` WHERE uri = %s", $uri )
		);

		// Insert view.
		$wpdb->insert( $views, [
			'session_id'      => $session_id,
			'resource_uri_id' => $uri_id,
			'viewed_at'       => $time,
		] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return $session_id;
	}

	/**
	 * @testdox Active visitor count endpoint returns correct count
	 */
	public function test_active_visitor_count_returns_correct_count(): void {
		for ( $i = 0; $i < 7; $i++ ) {
			$this->insert_active_visitor( '/page-' . $i, 'US', 'Chrome', 2 );
		}

		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $this->controller->get_items( $request );

		$this->assertSame( 200, $response->get_status(), 'Realtime endpoint should return 200 OK' );

		$data = $response->get_data();
		$this->assertSame( 7, $data['active_visitors'], 'Active visitor count should match the 7 inserted visitors' );
	}

	/**
	 * @testdox Active pages list shows current URLs with visitor counts
	 */
	public function test_active_pages_list_shows_current_urls(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->insert_active_visitor( '/pricing', 'US', 'Chrome', 1 );
		}
		for ( $i = 0; $i < 2; $i++ ) {
			$this->insert_active_visitor( '/blog/seo-tips', 'DE', 'Firefox', 2 );
		}

		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data['active_pages'], 'Active pages list should not be empty' );

		$first_page = $data['active_pages'][0];
		$this->assertSame( '/pricing', $first_page['uri'], 'First active page should be /pricing (most recently viewed)' );
		$this->assertEquals( 3, $first_page['visitors'], 'Pricing page should have 3 active visitors' );
	}

	/**
	 * @testdox Active pages list orders by most-recent view time, not visitor count
	 */
	public function test_active_pages_ordered_by_most_recent_view(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->insert_active_visitor( '/blog', 'DE', 'Firefox', 3 );
		}
		$this->insert_active_visitor( '/new', 'US', 'Chrome', 1 );

		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data['active_pages'], 'Active pages list should not be empty' );
		$this->assertSame(
			'/new',
			$data['active_pages'][0]['uri'],
			'Most recently viewed page must appear first even if it has fewer visitors'
		);
		$this->assertSame(
			'/blog',
			$data['active_pages'][1]['uri'],
			'Older page with more visitors must appear below the more recent one'
		);
	}

	/**
	 * @testdox Recent feed shows visitor country, browser, and time
	 */
	public function test_recent_feed_shows_country_browser_time(): void {
		$this->insert_active_visitor( '/about', 'DE', 'Firefox', 1 );

		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data['recent_feed'], 'Recent feed should contain at least one entry' );

		$entry = $data['recent_feed'][0];
		$this->assertSame( 'DE', $entry['country'], 'Feed entry country should be DE' );
		$this->assertSame( 'Firefox', $entry['browser'], 'Feed entry browser should be Firefox' );
		$this->assertSame( '/about', $entry['uri'], 'Feed entry URI should be /about' );
		$this->assertNotEmpty( $entry['time'], 'Feed entry time should not be empty' );
	}

	/**
	 * @testdox Tracking overhead within 25ms threshold
	 */
	public function test_tracking_overhead_within_threshold(): void {
		$this->insert_active_visitor( '/perf-test', 'US', 'Chrome', 1 );

		$request = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );

		$start    = microtime( true );
		$response = $this->controller->get_items( $request );
		$elapsed  = microtime( true ) - $start;

		$this->assertLessThan(
			0.025,
			$elapsed,
			sprintf( 'Realtime endpoint overhead %.1fms exceeds 25ms threshold', $elapsed * 1000 )
		);
	}
}
