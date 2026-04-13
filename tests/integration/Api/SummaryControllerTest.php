<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\SummaryController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Service\AggregationService;
use WP_REST_Request;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the SummaryController REST endpoint.
 *
 * Covers bug #3 (today data dropped/duplicated in summary).
 *
 * @covers \Statnive\Api\SummaryController
 */
final class SummaryControllerTest extends WP_UnitTestCase {

	private SummaryController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		delete_transient( 'statnive_realtime' );

		$this->controller = new SummaryController();

		// Set admin user for permission checks.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Insert raw visitor/session/view data for a given date.
	 *
	 * @param string $date    Date (Y-m-d).
	 * @param string $uri     Page URI.
	 * @param int    $count   Number of visitors/sessions/views to create.
	 */
	private function insert_raw_data( string $date, string $uri, int $count ): void {
		global $wpdb;

		$visitors_table = TableRegistry::get( 'visitors' );
		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );
		$uris_table     = TableRegistry::get( 'resource_uris' );

		$datetime = $date . ' 12:00:00';

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
			] );
			$session_id = (int) $wpdb->insert_id;

			$wpdb->insert( $views_table, [
				'session_id'      => $session_id,
				'resource_uri_id' => $uri_id,
				'viewed_at'       => $datetime,
				'duration'        => 30,
			] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Build a summary GET request with date range.
	 *
	 * @param string $from Start date (Y-m-d).
	 * @param string $to   End date (Y-m-d).
	 * @return WP_REST_Request
	 */
	private function build_summary_request( string $from, string $to ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/statnive/v1/summary' );
		$request->set_param( 'from', $from );
		$request->set_param( 'to', $to );
		return $request;
	}

	/**
	 * @testdox Summary includes today's live data without aggregation
	 */
	public function test_summary_includes_today_live_data(): void {
		$today = gmdate( 'Y-m-d' );

		// Insert raw data for today — no aggregation run.
		$this->insert_raw_data( $today, '/home', 3 );

		$request  = $this->build_summary_request( $today, $today );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThan( 0, $data['totals']['visitors'], 'Summary should include today live visitors without aggregation' );
		$this->assertGreaterThan( 0, $data['totals']['views'], 'Summary should include today live views without aggregation' );
	}

	/**
	 * @testdox Summary produces no duplicate row for today after aggregation
	 */
	public function test_summary_no_duplicate_today_row(): void {
		$today = gmdate( 'Y-m-d' );

		// Insert raw data and aggregate.
		$this->insert_raw_data( $today, '/home', 2 );
		AggregationService::aggregate_day( $today );

		$request  = $this->build_summary_request( $today, $today );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		// Count rows in the daily array that match today's date.
		$today_rows = array_filter(
			$data['daily'],
			fn( $row ) => ( $row['date'] ?? '' ) === $today
		);

		$this->assertCount( 1, $today_rows, 'Summary daily array must have exactly 1 row for today, not duplicated' );
	}

	/**
	 * @testdox Summary live data replaces stale aggregation when new views arrive
	 */
	public function test_summary_live_data_replaces_stale_aggregation(): void {
		$today = gmdate( 'Y-m-d' );

		// Insert initial data and aggregate.
		$this->insert_raw_data( $today, '/landing', 2 );
		AggregationService::aggregate_day( $today );

		// Read the aggregated view count from summary_totals.
		global $wpdb;
		$summary_totals = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$aggregated_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT views FROM `{$summary_totals}` WHERE date = %s",
				$today
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Insert 2 more views after aggregation.
		$this->insert_raw_data( $today, '/landing', 2 );

		$request  = $this->build_summary_request( $today, $today );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertGreaterThan(
			$aggregated_views,
			$data['totals']['views'],
			'Summary views should reflect fresh data that arrived after aggregation'
		);
	}

	/**
	 * Insert raw data with explicit view duration (for duration pipeline tests).
	 *
	 * @param string $date         Date (Y-m-d).
	 * @param string $uri          Page URI.
	 * @param int    $count        Number of visitors/sessions/views.
	 * @param int    $view_duration Duration in seconds for each view.
	 */
	private function insert_raw_data_with_duration( string $date, string $uri, int $count, int $view_duration ): void {
		global $wpdb;

		$visitors_table = TableRegistry::get( 'visitors' );
		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );
		$uris_table     = TableRegistry::get( 'resource_uris' );

		$datetime = $date . ' 12:00:00';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
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

			// Session duration stays 0 (realistic — never populated by EngagementController).
			$wpdb->insert( $sessions_table, [
				'visitor_id'  => $visitor_id,
				'started_at'  => $datetime,
				'total_views' => 1,
			] );
			$session_id = (int) $wpdb->insert_id;

			$wpdb->insert( $views_table, [
				'session_id'      => $session_id,
				'resource_uri_id' => $uri_id,
				'viewed_at'       => $datetime,
				'duration'        => $view_duration,
			] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @testdox Summary today real-time fallback includes view duration
	 */
	public function test_summary_today_includes_view_duration(): void {
		$today = gmdate( 'Y-m-d' );

		// Insert data with duration on views, not sessions.
		$this->insert_raw_data_with_duration( $today, '/duration-test', 3, 25 );

		$request  = $this->build_summary_request( $today, $today );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 75, $data['totals']['total_duration'], 'Today real-time fallback should sum views.duration (3 × 25 = 75)' );
	}

	/**
	 * @testdox Summary aggregated data includes view duration
	 */
	public function test_summary_aggregated_includes_view_duration(): void {
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		$this->insert_raw_data_with_duration( $yesterday, '/duration-agg', 2, 40 );
		AggregationService::aggregate_day( $yesterday );

		$request  = $this->build_summary_request( $yesterday, $yesterday );
		$response = $this->controller->get_items( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 80, $data['totals']['total_duration'], 'Aggregated summary should sum views.duration (2 × 40 = 80)' );
	}
}
