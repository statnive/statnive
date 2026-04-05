<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\PagesController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Service\AggregationService;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for the PagesController REST endpoint.
 *
 * Covers bug #4 (stale aggregated data in pages endpoint).
 *
 * @covers \Statnive\Api\PagesController
 */
final class PagesControllerTest extends WP_UnitTestCase {

	private PagesController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		delete_transient( 'statnive_realtime' );

		$this->controller = new PagesController();

		// Set admin user for permission checks.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Insert raw visitor/session/view data for a given date and URI.
	 *
	 * Each call creates $count distinct visitors, each with 1 session and 1 view.
	 *
	 * @param string $date  Date (Y-m-d).
	 * @param string $uri   Page URI.
	 * @param int    $count Number of visitors to create.
	 */
	private function insert_raw_data( string $date, string $uri, int $count ): void {
		global $wpdb;

		$visitors_table = TableRegistry::get( 'visitors' );
		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );
		$uris_table     = TableRegistry::get( 'resource_uris' );

		$datetime = $date . ' 14:00:00';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		// Insert URI.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$uris_table}` (uri, uri_hash) VALUES (%s, %d)",
				$uri,
				crc32( $uri )
			)
		);
		$uri_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$uris_table}` WHERE uri = %s", $uri )
		);

		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert( $visitors_table, [
				'hash'       => random_bytes( 8 ),
				'created_at' => $datetime,
			] );
			$visitor_id = (int) $wpdb->insert_id;

			$wpdb->insert( $sessions_table, [
				'visitor_id'  => $visitor_id,
				'started_at'  => $datetime,
				'total_views' => 1,
				'duration'    => 30,
			] );
			$session_id = (int) $wpdb->insert_id;

			$wpdb->insert( $views_table, [
				'session_id'      => $session_id,
				'resource_uri_id' => $uri_id,
				'viewed_at'       => $datetime,
			] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Build a pages GET request with date range.
	 *
	 * @param string $from Start date (Y-m-d).
	 * @param string $to   End date (Y-m-d).
	 * @return WP_REST_Request
	 */
	private function build_pages_request( string $from, string $to ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/statnive/v1/pages' );
		$request->set_param( 'from', $from );
		$request->set_param( 'to', $to );
		$request->set_param( 'limit', 20 );
		$request->set_param( 'offset', 0 );
		return $request;
	}

	/**
	 * @testdox Pages returns today's live data without aggregation
	 */
	public function test_pages_returns_today_live_data(): void {
		$today = gmdate( 'Y-m-d' );

		// Insert raw data for today — no aggregation.
		$this->insert_raw_data( $today, '/about', 2 );
		$this->insert_raw_data( $today, '/contact', 1 );

		$request  = $this->build_pages_request( $today, $today );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		$uris = array_column( $data, 'uri' );
		$this->assertContains( '/about', $uris, 'Pages should include /about from live data' );
		$this->assertContains( '/contact', $uris, 'Pages should include /contact from live data' );
	}

	/**
	 * @testdox Pages fresh data replaces stale aggregated data for today
	 */
	public function test_pages_fresh_data_replaces_aggregated(): void {
		$today = gmdate( 'Y-m-d' );

		// Insert initial data and aggregate.
		$this->insert_raw_data( $today, '/pricing', 2 );
		AggregationService::aggregate_day( $today );

		// Read aggregated view count from summary table.
		global $wpdb;
		$summary = TableRegistry::get( 'summary' );
		$uris    = TableRegistry::get( 'resource_uris' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$uri_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$uris}` WHERE uri = %s", '/pricing' )
		);
		$aggregated_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT views FROM `{$summary}` WHERE date = %s AND resource_uri_id = %d",
				$today,
				$uri_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Insert more views after aggregation.
		$this->insert_raw_data( $today, '/pricing', 3 );

		$request  = $this->build_pages_request( $today, $today );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		// Find the /pricing row.
		$pricing_rows = array_filter( $data, fn( $row ) => ( $row['uri'] ?? '' ) === '/pricing' );
		$this->assertNotEmpty( $pricing_rows, 'Pages should include /pricing' );

		$pricing = array_values( $pricing_rows )[0];
		$this->assertGreaterThan(
			$aggregated_views,
			(int) $pricing['views'],
			'Pages view count should reflect fresh data that arrived after aggregation'
		);
	}

	/**
	 * @testdox Pages are sorted by visitors descending
	 */
	public function test_pages_sorted_by_visitors_desc(): void {
		$today = gmdate( 'Y-m-d' );

		// Insert 3 visitors for /popular and 1 for /unpopular.
		$this->insert_raw_data( $today, '/popular', 3 );
		$this->insert_raw_data( $today, '/unpopular', 1 );

		$request  = $this->build_pages_request( $today, $today );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data, 'Pages response should not be empty' );
		$this->assertSame( '/popular', $data[0]['uri'], 'First page should be /popular (most visitors)' );

		// Verify descending order.
		for ( $i = 1; $i < count( $data ); $i++ ) {
			$this->assertGreaterThanOrEqual(
				(int) $data[ $i ]['visitors'],
				(int) $data[ $i - 1 ]['visitors'],
				'Pages should be sorted by visitors in descending order'
			);
		}
	}
}
