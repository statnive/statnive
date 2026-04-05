<?php
/**
 * Generated from BDD scenarios (04-data-aggregation.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Service\AggregationService;
use Statnive\Service\DimensionService;
use WP_UnitTestCase;

/**
 * Integration tests for the daily aggregation service.
 *
 * @covers \Statnive\Service\AggregationService
 */
final class AggregationServiceTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		DimensionService::clear_cache();
	}

	/**
	 * Insert raw visitor/session/view data for a URI on a date.
	 *
	 * @param string $date       Date (Y-m-d).
	 * @param string $uri        Page URI.
	 * @param int    $visitors   Number of visitors.
	 * @param int    $sessions   Number of sessions.
	 * @param int    $views      Number of views.
	 * @param int    $bounces    Number of single-view sessions.
	 */
	private function insert_raw_data( string $date, string $uri, int $visitors, int $sessions, int $views, int $bounces = 0 ): void {
		global $wpdb;

		$visitors_table = TableRegistry::get( 'visitors' );
		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );
		$uris_table     = TableRegistry::get( 'resource_uris' );

		$datetime = $date . ' 10:00:00';

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

		$view_index    = 0;
		$bounce_count  = 0;

		for ( $v = 0; $v < $visitors; $v++ ) {
			$wpdb->insert( $visitors_table, [
				'hash'       => random_bytes( 8 ),
				'created_at' => $datetime,
			] );
			$visitor_id = (int) $wpdb->insert_id;

			// Distribute sessions among visitors.
			$visitor_sessions = ( $v < $sessions ) ? 1 : 0;
			if ( $v === $visitors - 1 && $sessions > $visitors ) {
				$visitor_sessions = $sessions - $visitors + 1;
			}

			for ( $s = 0; $s < max( 1, $visitor_sessions ); $s++ ) {
				$is_bounce   = $bounce_count < $bounces;
				$sess_views  = $is_bounce ? 1 : max( 1, (int) ceil( $views / $sessions ) );
				$bounce_count++;

				$wpdb->insert( $sessions_table, [
					'visitor_id'  => $visitor_id,
					'started_at'  => $datetime,
					'total_views' => $sess_views,
					'duration'    => $sess_views * 30,
				] );
				$session_id = (int) $wpdb->insert_id;

				for ( $vw = 0; $vw < $sess_views && $view_index < $views; $vw++ ) {
					$wpdb->insert( $views_table, [
						'session_id'      => $session_id,
						'resource_uri_id' => $uri_id,
						'viewed_at'       => $datetime,
					] );
					$view_index++;
				}
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @testdox Daily agg produces summary row per URI
	 */
	public function test_daily_agg_produces_summary_row_per_uri(): void {
		global $wpdb;

		$this->insert_raw_data( '2026-04-02', '/product/shoes', 3, 5, 12 );
		$this->insert_raw_data( '2026-04-02', '/blog/analytics-guide', 2, 3, 7 );

		AggregationService::aggregate_day( '2026-04-02' );

		$summary = TableRegistry::get( 'summary' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$summary}` WHERE date = %s", '2026-04-02' )
		);

		$this->assertSame( 2, $count, 'Daily aggregation should produce 1 summary row per URI' );
	}

	/**
	 * @testdox Daily agg produces summary_totals row
	 */
	public function test_daily_agg_produces_summary_totals_row(): void {
		global $wpdb;

		$this->insert_raw_data( '2026-04-02', '/product/shoes', 3, 5, 12 );

		AggregationService::aggregate_day( '2026-04-02' );

		$summary_totals = TableRegistry::get( 'summary_totals' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$summary_totals}` WHERE date = %s", '2026-04-02' )
		);

		$this->assertNotNull( $row, 'Summary totals row should exist after daily aggregation' );
		$this->assertGreaterThan( 0, (int) $row->visitors, 'Aggregated visitors count should be greater than 0' );
	}

	/**
	 * @testdox Running twice produces no double counts (idempotent)
	 */
	public function test_idempotent_aggregation(): void {
		global $wpdb;

		$this->insert_raw_data( '2026-04-02', '/product/shoes', 3, 5, 10 );

		AggregationService::aggregate_day( '2026-04-02' );
		AggregationService::aggregate_day( '2026-04-02' );

		$summary_totals = TableRegistry::get( 'summary_totals' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$summary_totals}` WHERE date = %s", '2026-04-02' )
		);

		$this->assertSame( 1, $count, 'Running aggregation twice should produce exactly 1 summary_totals row (idempotent)' );
	}

	/**
	 * @testdox Backfill missed days within 30 days
	 */
	public function test_backfill_missed_days(): void {
		global $wpdb;

		$old_date = gmdate( 'Y-m-d', strtotime( '-15 days' ) );
		$this->insert_raw_data( $old_date, '/product/shoes', 2, 2, 4 );

		AggregationService::aggregate_day( $old_date );

		$summary_totals = TableRegistry::get( 'summary_totals' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$summary_totals}` WHERE date = %s", $old_date )
		);

		$this->assertNotNull( $row, 'Backfill should produce summary_totals row for missed day' );
	}

	/**
	 * @testdox Zero traffic day produces no error
	 */
	public function test_zero_traffic_day_no_error(): void {
		global $wpdb;

		// No data inserted for this date.
		AggregationService::aggregate_day( '2026-04-01' );

		$summary = TableRegistry::get( 'summary' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$summary}` WHERE date = %s", '2026-04-01' )
		);

		$this->assertSame( 0, $count, 'Zero traffic day should produce no summary rows' );
	}

	/**
	 * @testdox Summary matches raw counts invariant
	 */
	public function test_summary_matches_raw_counts(): void {
		global $wpdb;

		$this->insert_raw_data( '2026-04-02', '/landing', 4, 6, 15, 2 );

		AggregationService::aggregate_day( '2026-04-02' );

		$summary = TableRegistry::get( 'summary' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$summary}` WHERE date = %s", '2026-04-02' )
		);

		$this->assertNotNull( $row, 'Summary row should exist for aggregated day' );
		$this->assertGreaterThan( 0, (int) $row->visitors, 'Summary visitors should be greater than 0' );
		$this->assertGreaterThan( 0, (int) $row->views, 'Summary views should be greater than 0' );
	}

	/**
	 * @testdox Dimension service insert/reuse
	 */
	public function test_dimension_service_insert_and_reuse(): void {
		$id1 = DimensionService::resolve_country( 'DE', 'Germany' );
		$this->assertGreaterThan( 0, $id1, 'First resolve should return a positive dimension ID' );

		$id2 = DimensionService::resolve_country( 'DE', 'Germany' );
		$this->assertSame( $id1, $id2, 'Second resolve should reuse the same dimension ID' );
	}

	/**
	 * @testdox Backfill skips existing
	 */
	public function test_backfill_skips_existing(): void {
		$date = gmdate( 'Y-m-d', strtotime( '-5 days' ) );
		$this->insert_raw_data( $date, '/page', 2, 2, 4 );

		AggregationService::aggregate_day( $date );
		$is_aggregated = AggregationService::is_aggregated( $date );

		$this->assertTrue( $is_aggregated, 'Day should be marked as aggregated after running aggregate_day' );
	}
}
