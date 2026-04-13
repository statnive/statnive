<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Plugin;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Plugin;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for plugin activation / deactivation / uninstall lifecycle.
 *
 * Covers production scenarios:
 *  - Reactivation preserves existing analytics data
 *  - Fresh activation creates all 21 tables
 *  - Uninstall removes all tables and options
 *  - Default options are set on first activation
 *
 * @covers \Statnive\Plugin::activate
 * @covers \Statnive\Plugin::deactivate
 */
final class ActivationIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * @testdox Reactivation preserves existing analytics data
	 */
	public function test_reactivation_preserves_existing_data(): void {
		global $wpdb;

		// Initial activation creates tables.
		Plugin::activate();

		$summary_totals = TableRegistry::get( 'summary_totals' );

		// Insert a row to simulate existing analytics data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $summary_totals, [
			'date'     => '2025-01-15',
			'visitors' => 42,
			'sessions' => 50,
			'views'    => 100,
		] );

		$this->assertSame( 1, $wpdb->rows_affected, 'Seed row should be inserted' );

		// Deactivate then reactivate.
		Plugin::deactivate();
		Plugin::activate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$summary_totals}` WHERE date = %s", '2025-01-15' )
		);

		$this->assertNotNull( $row, 'Existing data should survive reactivation' );
		$this->assertEquals( 42, $row->visitors );
	}

	/**
	 * @testdox Activation creates all 21 database tables
	 */
	public function test_activation_creates_all_tables(): void {
		Plugin::activate();

		$check = DatabaseFactory::check_tables();

		$this->assertCount( 0, $check['missing'], 'Missing tables after activation: ' . implode( ', ', $check['missing'] ) );
		$this->assertCount( 21, $check['existing'], 'Expected 21 tables after activation' );
	}

	/**
	 * @testdox Uninstall removes all tables and options
	 */
	public function test_uninstall_removes_all_tables_and_options(): void {
		global $wpdb;

		// Activate first so tables and options exist.
		Plugin::activate();

		$check_before = DatabaseFactory::check_tables();
		$this->assertCount( 21, $check_before['existing'], 'Tables must exist before uninstall test' );

		// Simulate uninstall by including uninstall.php.
		// WordPress defines WP_UNINSTALL_PLUGIN before including the file.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		$uninstall_path = dirname( __DIR__, 3 ) . '/uninstall.php';

		if ( ! file_exists( $uninstall_path ) ) {
			$this->markTestSkipped( 'uninstall.php not found — expected at ' . $uninstall_path );
		}

		require $uninstall_path;

		// Verify tables are dropped.
		foreach ( TableRegistry::all_names() as $name ) {
			$table = TableRegistry::get( $name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			$this->assertNull( $exists, "Table {$table} should be dropped after uninstall" );
		}

		// Verify options are deleted.
		$this->assertFalse( get_option( 'statnive_version' ), 'statnive_version option should be deleted' );
		$this->assertFalse( get_option( 'statnive_db_version' ), 'statnive_db_version option should be deleted' );
	}

	/**
	 * @testdox Activation sets expected default options
	 */
	public function test_activation_sets_default_options(): void {
		// Delete any pre-existing options.
		delete_option( 'statnive_version' );
		delete_option( 'statnive_respect_dnt' );
		delete_option( 'statnive_respect_gpc' );
		delete_option( 'statnive_tracking_enabled' );
		delete_option( 'statnive_geoip_enabled' );

		Plugin::activate();

		$this->assertNotFalse( get_option( 'statnive_version' ), 'statnive_version should be set' );
		$this->assertTrue( (bool) get_option( 'statnive_respect_dnt' ), 'DNT should be enabled by default' );
		$this->assertTrue( (bool) get_option( 'statnive_respect_gpc' ), 'GPC should be enabled by default' );
		$this->assertTrue( (bool) get_option( 'statnive_tracking_enabled' ), 'Tracking should be enabled by default' );
		$this->assertFalse( (bool) get_option( 'statnive_geoip_enabled' ), 'GeoIP should be disabled by default' );
	}
}
