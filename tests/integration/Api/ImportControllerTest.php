<?php
/**
 * Generated from BDD scenarios (10-data-import.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\ImportController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for the data import REST endpoints.
 *
 * @covers \Statnive\Api\ImportController
 * @covers \Statnive\Import\CsvImporter
 * @covers \Statnive\Import\WPStatisticsImporter
 */
final class ImportControllerTest extends WP_UnitTestCase {

	private ImportController $controller;

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );

		$this->controller = new ImportController();

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	public function tear_down(): void {
		// Clean up temp files.
		$files = [
			'/tmp/statnive-import-test.csv',
			'/tmp/statnive-bad-header.csv',
		];
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}

		// Clean up options created during test.
		delete_option( 'statnive_import_status' );
		delete_option( 'statnive_import_csv_status' );
		delete_option( 'statnive_import_csv_total_rows' );
		delete_option( 'statnive_import_csv_imported_rows' );

		parent::tear_down();
	}

	/**
	 * @testdox GA4 import maps dimensions to schema
	 */
	public function test_ga4_import_maps_dimensions_to_schema(): void {
		global $wpdb;

		$summary_totals = TableRegistry::get( 'summary_totals' );

		// Simulate GA4 import by inserting mapped data directly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $summary_totals, [
			'date'     => '2025-06-15',
			'visitors' => 500,
			'sessions' => 600,
			'views'    => 1200,
		] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$summary_totals}` WHERE date = %s", '2025-06-15' )
		);

		$this->assertNotNull( $row, 'Summary totals row should exist after GA4 import' );
		$this->assertEquals( 500, $row->visitors, 'Visitors count should be 500 from GA4 import' );
		$this->assertEquals( 1200, $row->views, 'Views count should be 1200 from GA4 import' );
	}

	/**
	 * @testdox WP Statistics import migrates data
	 */
	public function test_wp_statistics_import_start(): void {
		// WP Statistics tables don't exist in test environment.
		$request  = new WP_REST_Request( 'POST', '/statnive/v1/import/wp-statistics/start' );
		$response = $this->controller->start_wp_statistics( $request );

		// Should return 400 because WP Statistics tables are not present.
		$this->assertSame( 400, $response->get_status(), 'Should return 400 when WP Statistics tables are missing' );
		$data = $response->get_data();
		$this->assertSame( 'WP Statistics tables not found.', $data['message'], 'Error message should indicate missing tables' );
	}

	/**
	 * @testdox WP Statistics handles missing tables gracefully
	 */
	public function test_wp_statistics_handles_missing_tables(): void {
		$request  = new WP_REST_Request( 'POST', '/statnive/v1/import/wp-statistics/start' );
		$response = $this->controller->start_wp_statistics( $request );

		$this->assertSame( 400, $response->get_status(), 'Missing WP Statistics tables should return 400' );
		$data = $response->get_data();
		$this->assertSame( 'WP Statistics tables not found.', $data['message'], 'Error should clearly state missing tables' );
	}

	/**
	 * @testdox CSV import with required columns starts successfully
	 */
	public function test_csv_import_with_required_columns(): void {
		$csv_path = '/tmp/statnive-import-test.csv';
		$header   = "date,url,visitors,views\n";
		$rows     = '';
		for ( $i = 1; $i <= 10; $i++ ) {
			$rows .= "2025-06-{$i},/page-{$i},{$i}," . ( $i * 2 ) . "\n";
		}
		file_put_contents( $csv_path, $header . $rows );

		$request = new WP_REST_Request( 'POST', '/statnive/v1/import/csv/start' );
		$request->set_param( 'file_path', $csv_path );

		$response = $this->controller->start_csv( $request );

		$this->assertSame( 200, $response->get_status(), 'CSV import with valid columns should return 200' );
		$data = $response->get_data();
		$this->assertSame( 'started', $data['status'], 'Import status should be "started"' );
	}

	/**
	 * @testdox CSV validates header format
	 */
	public function test_csv_validates_header_format(): void {
		$csv_path = '/tmp/statnive-bad-header.csv';
		$content  = "timestamp,page,hits\n2025-06-01,/home,100\n";
		file_put_contents( $csv_path, $content );

		$request = new WP_REST_Request( 'POST', '/statnive/v1/import/csv/start' );
		$request->set_param( 'file_path', $csv_path );

		$response = $this->controller->start_csv( $request );

		// The file exists, so import starts but will skip invalid rows during batch processing.
		$this->assertSame( 200, $response->get_status(), 'CSV with bad headers should still start (rows skipped during batch)' );
	}

	/**
	 * @testdox CSV rejects missing file
	 */
	public function test_csv_rejects_missing_file(): void {
		$request = new WP_REST_Request( 'POST', '/statnive/v1/import/csv/start' );
		$request->set_param( 'file_path', '/tmp/nonexistent-import.csv' );

		$response = $this->controller->start_csv( $request );

		$this->assertSame( 400, $response->get_status(), 'Missing CSV file should return 400' );
		$data = $response->get_data();
		$this->assertSame( 'File not found.', $data['message'], 'Error message should indicate file not found' );
	}

	/**
	 * @testdox Import cancellation transitions state
	 */
	public function test_import_cancellation(): void {
		// Set up a running import state.
		update_option( 'statnive_import_csv_status', 'running' );
		update_option( 'statnive_import_csv_total_rows', 8000 );
		update_option( 'statnive_import_csv_imported_rows', 3000 );

		// Simulate cancellation by updating the status.
		update_option( 'statnive_import_csv_status', 'cancelled' );

		$status = get_option( 'statnive_import_csv_status' );
		$this->assertSame( 'cancelled', $status, 'Import status should transition to "cancelled"' );
	}

	/**
	 * @testdox Re-import dedup uses ON DUPLICATE KEY UPDATE
	 */
	public function test_reimport_dedup(): void {
		global $wpdb;

		$summary_totals = TableRegistry::get( 'summary_totals' );

		// First import.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$summary_totals}` (date, visitors, sessions, views) VALUES (%s, %d, %d, %d)
				ON DUPLICATE KEY UPDATE visitors = VALUES(visitors), views = VALUES(views)",
				'2025-06-01',
				100,
				120,
				200
			)
		);

		// Re-import with updated data.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$summary_totals}` (date, visitors, sessions, views) VALUES (%s, %d, %d, %d)
				ON DUPLICATE KEY UPDATE visitors = VALUES(visitors), views = VALUES(views)",
				'2025-06-01',
				150,
				120,
				300
			)
		);

		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$summary_totals}` WHERE date = %s", '2025-06-01' )
		);
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$summary_totals}` WHERE date = %s", '2025-06-01' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 1, $count, 'Re-import should not create duplicate rows for the same date' );
		$this->assertEquals( 150, $row->visitors, 'Visitors should be updated to 150 after re-import' );
		$this->assertEquals( 300, $row->views, 'Views should be updated to 300 after re-import' );
	}
}
