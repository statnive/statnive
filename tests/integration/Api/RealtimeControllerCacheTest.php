<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\HitController;
use Statnive\Api\RealtimeController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Security\HmacValidator;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for realtime cache invalidation and deduplication.
 *
 * Covers bugs #1 (stale cache) and #6 (duplicate pageviews).
 *
 * @covers \Statnive\Api\RealtimeController
 * @covers \Statnive\Api\HitController
 */
final class RealtimeControllerCacheTest extends WP_UnitTestCase {

	private RealtimeController $realtime;
	private HitController $hit;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		delete_transient( 'statnive_realtime' );

		$this->realtime = new RealtimeController();
		$this->hit      = new HitController();

		// Set admin user for permission checks.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Insert test session and view data for real-time queries.
	 *
	 * @param string $uri         Page URI.
	 * @param string $country_code Country code.
	 * @param string $browser_name Browser name.
	 * @param int    $minutes_ago How many minutes ago the view occurred.
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
	 * Build a tracking hit request with HMAC signature.
	 *
	 * @param array<string, mixed> $data Override fields.
	 * @return WP_REST_Request
	 */
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
			'page_url'      => '/',
			'page_query'    => '',
		];

		$payload = array_merge( $defaults, $data );

		$request = new WP_REST_Request( 'POST', '/statnive/v1/hit' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'text/plain' );

		return $request;
	}

	/**
	 * @testdox Realtime cache is invalidated after a new hit is recorded
	 */
	public function test_realtime_cache_invalidated_on_new_hit(): void {
		// Seed a stale transient.
		set_transient( 'statnive_realtime', [
			'active_visitors' => 999,
			'active_pages'    => [],
			'recent_feed'     => [],
		], 60 );

		// Confirm the stale cache is set.
		$this->assertNotFalse( get_transient( 'statnive_realtime' ), 'Stale transient should be set before hit' );

		// Fire a hit — this should delete the transient.
		$request  = $this->build_request();
		$this->hit->create_item( $request );

		// Assert the transient was cleared.
		$this->assertFalse( get_transient( 'statnive_realtime' ), 'Realtime transient must be invalidated after a new hit' );
	}

	/**
	 * @testdox Realtime shows data for visitors within the last 5 minutes
	 */
	public function test_realtime_shows_data_within_five_minutes(): void {
		$this->insert_active_visitor( '/about', 'US', 'Chrome', 1 );

		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $this->realtime->get_items( $request );
		$data     = $response->get_data();

		$this->assertGreaterThanOrEqual( 1, $data['active_visitors'], 'Active visitors should include visitor from 1 minute ago' );
	}

	/**
	 * @testdox Recent feed does not contain duplicate entries from duplicate resource rows
	 */
	public function test_recent_feed_no_duplicate_entries(): void {
		global $wpdb;

		$resources = TableRegistry::get( 'resources' );
		$uris      = TableRegistry::get( 'resource_uris' );

		// Insert 2 duplicate rows in resources table for the same resource_id.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $resources, [
			'resource_type' => 'post',
			'resource_id'   => 42,
			'cached_title'  => 'Test Page',
		] );
		$wpdb->insert( $resources, [
			'resource_type' => 'post',
			'resource_id'   => 42,
			'cached_title'  => 'Test Page',
		] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Insert a single visitor/view for this resource.
		$this->insert_active_visitor( '/test-page', 'US', 'Chrome', 1 );

		// Link the resource_uri to resource_id 42.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$uri_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$uris}` WHERE uri = %s", '/test-page' )
		);
		$wpdb->update( $uris, [ 'resource_id' => 42 ], [ 'ID' => $uri_id ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Clear cache so realtime re-queries.
		delete_transient( 'statnive_realtime' );

		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $this->realtime->get_items( $request );
		$data     = $response->get_data();

		// Count entries in recent_feed for /test-page.
		$test_page_entries = array_filter(
			$data['recent_feed'],
			fn( $entry ) => $entry['uri'] === '/test-page'
		);

		$this->assertCount( 1, $test_page_entries, 'Recent feed should have exactly 1 entry for the view, not duplicated by resource rows' );
	}

	/**
	 * @testdox Active visitors count is consistent with unique visitors across active pages
	 */
	public function test_active_visitors_consistent_with_active_pages(): void {
		// Insert 2 visitors for different URIs.
		$this->insert_active_visitor( '/pricing', 'US', 'Chrome', 1 );
		$this->insert_active_visitor( '/blog', 'DE', 'Firefox', 2 );

		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $this->realtime->get_items( $request );
		$data     = $response->get_data();

		// Sum unique visitors across active pages.
		$page_visitor_sum = 0;
		foreach ( $data['active_pages'] as $page ) {
			$page_visitor_sum += (int) $page['visitors'];
		}

		$this->assertGreaterThanOrEqual(
			count( $data['active_pages'] ),
			$data['active_visitors'],
			'Active visitors should be >= number of active pages (each page has at least 1 visitor)'
		);

		// Since each visitor visits a unique URI, active_visitors == page_visitor_sum.
		$this->assertSame(
			$data['active_visitors'],
			$page_visitor_sum,
			'Active visitors count should equal sum of unique visitors across active pages when visitors have unique URIs'
		);
	}
}
