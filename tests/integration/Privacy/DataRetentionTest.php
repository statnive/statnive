<?php
/**
 * Generated from BDD scenarios (07-privacy-compliance.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Privacy;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Privacy\DataPurger;
use Statnive\Privacy\RetentionManager;
use WP_UnitTestCase;

/**
 * Integration tests for data retention purge thresholds.
 *
 * @covers \Statnive\Privacy\DataPurger
 * @covers \Statnive\Privacy\RetentionManager
 */
final class DataRetentionTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
	}

	/**
	 * Insert test data at various ages.
	 *
	 * @param int $days_ago_old  Days ago for "old" data.
	 * @param int $days_ago_new  Days ago for "new" data.
	 */
	private function insert_test_data( int $days_ago_old, int $days_ago_new ): void {
		global $wpdb;

		$visitors = TableRegistry::get( 'visitors' );
		$sessions = TableRegistry::get( 'sessions' );
		$views    = TableRegistry::get( 'views' );

		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago_old} days" ) );
		$new_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago_new} days" ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		// Old visitor + session + view.
		$wpdb->insert( $visitors, [ 'hash' => random_bytes( 8 ), 'created_at' => $old_date ] );
		$old_visitor = (int) $wpdb->insert_id;
		$wpdb->insert( $sessions, [
			'visitor_id'  => $old_visitor,
			'started_at'  => $old_date,
			'total_views' => 1,
		] );
		$old_session = (int) $wpdb->insert_id;
		$wpdb->insert( $views, [
			'session_id' => $old_session,
			'viewed_at'  => $old_date,
		] );

		// New visitor + session + view.
		$wpdb->insert( $visitors, [ 'hash' => random_bytes( 8 ), 'created_at' => $new_date ] );
		$new_visitor = (int) $wpdb->insert_id;
		$wpdb->insert( $sessions, [
			'visitor_id'  => $new_visitor,
			'started_at'  => $new_date,
			'total_views' => 1,
		] );
		$new_session = (int) $wpdb->insert_id;
		$wpdb->insert( $views, [
			'session_id' => $new_session,
			'viewed_at'  => $new_date,
		] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @testdox Purge at 30-day retention threshold
	 * @dataProvider retention_threshold_provider
	 *
	 * @param int $retention_days Configured retention period.
	 */
	public function test_purge_at_retention_threshold( int $retention_days ): void {
		global $wpdb;

		update_option( 'statnive_retention_mode', 'delete' );
		update_option( 'statnive_retention_days', $retention_days );

		// Insert data older than the retention period and recent data.
		$days_old = $retention_days + 30;
		$this->insert_test_data( $days_old, 5 );

		$sessions = TableRegistry::get( 'sessions' );
		$views    = TableRegistry::get( 'views' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$pre_session_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$sessions}`" );
		$pre_view_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$views}`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 2, $pre_session_count, 'Should have 2 sessions before purge' );
		$this->assertSame( 2, $pre_view_count, 'Should have 2 views before purge' );

		$result = DataPurger::purge();

		$this->assertGreaterThan( 0, $result['deleted'], 'Purge should delete at least one record' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$post_session_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$sessions}`" );
		$post_view_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$views}`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Old data should be deleted; new data (5 days) should remain.
		$this->assertSame( 1, $post_session_count, 'Only recent session should remain after purge' );
		$this->assertSame( 1, $post_view_count, 'Only recent view should remain after purge' );
	}

	/**
	 * Data provider for retention thresholds.
	 *
	 * @return array<string, array{0: int}>
	 */
	public static function retention_threshold_provider(): array {
		return [
			'30-day retention'  => [ 30 ],
			'90-day retention'  => [ 90 ],
			'365-day retention' => [ 365 ],
		];
	}

	/**
	 * @testdox Forever mode does not purge any data
	 */
	public function test_forever_mode_does_not_purge(): void {
		global $wpdb;

		update_option( 'statnive_retention_mode', 'forever' );
		$this->insert_test_data( 120, 5 );

		$result = DataPurger::purge();

		$this->assertSame( 0, $result['deleted'], 'Forever mode should not delete any records' );

		$sessions = TableRegistry::get( 'sessions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$sessions}`" );
		$this->assertSame( 2, $count, 'Both sessions should remain in forever mode' );
	}
}
