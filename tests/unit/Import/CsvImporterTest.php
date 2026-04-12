<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Import\CsvImporter;

/**
 * Unit tests for CsvImporter.
 *
 * Only tests logic that can run without a real database ($wpdb).
 * The batch-insert path requires $wpdb and is covered by integration tests.
 */
#[CoversClass(CsvImporter::class)]
final class CsvImporterTest extends TestCase {

	private CsvImporter $importer;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_options'] = [];

		$this->importer = new CsvImporter();
	}

	public function test_process_batch_returns_false_when_not_running(): void {
		// No state at all — status defaults to empty, not 'running'.
		$this->assertFalse( $this->importer->process_batch() );
	}

	public function test_process_batch_sets_error_when_file_missing(): void {
		$GLOBALS['statnive_test_options']['statnive_import_csv'] = [
			'status'        => 'running',
			'config'        => [ 'file_path' => '/nonexistent/file.csv' ],
			'imported_rows' => 0,
		];

		$result = $this->importer->process_batch();

		$this->assertFalse( $result );

		$state = $GLOBALS['statnive_test_options']['statnive_import_csv'];
		$this->assertSame( 'error', $state['status'] );
		$this->assertSame( 'CSV file not found.', $state['error'] );
	}
}
