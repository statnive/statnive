<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Import\ImportManager;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Unit tests for the ImportManager state machine.
 *
 * Uses a concrete anonymous subclass to exercise the abstract base.
 */
#[CoversClass(ImportManager::class)]
final class ImportManagerTest extends TestCase {

	private ImportManager $manager;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_options'] = [];

		$this->manager = new class() extends ImportManager {
			public function get_source(): string {
				return 'test';
			}

			public function process_batch(): bool {
				return false;
			}
		};
	}

	public function test_start_sets_status_to_running(): void {
		$this->manager->start( [ 'file_path' => '/tmp/test.csv' ] );

		$state = $GLOBALS['statnive_test_options']['statnive_import_test'] ?? [];

		$this->assertSame( 'running', $state['status'] );
	}

	public function test_get_progress_calculates_percentage(): void {
		$GLOBALS['statnive_test_options']['statnive_import_test'] = [
			'status'        => 'running',
			'total_rows'    => 200,
			'imported_rows' => 50,
			'error'         => null,
		];

		$progress = $this->manager->get_progress();

		$this->assertSame( 25, $progress['percentage'] );
		$this->assertSame( 'running', $progress['status'] );
	}

	public function test_get_progress_returns_zero_percent_when_total_is_zero(): void {
		$GLOBALS['statnive_test_options']['statnive_import_test'] = [
			'status'        => 'running',
			'total_rows'    => 0,
			'imported_rows' => 0,
			'error'         => null,
		];

		$progress = $this->manager->get_progress();

		$this->assertSame( 0, $progress['percentage'] );
	}

	public function test_cancel_sets_status_to_cancelled(): void {
		$GLOBALS['statnive_test_options']['statnive_import_test'] = [
			'status' => 'running',
		];

		$this->manager->cancel();

		$state = $GLOBALS['statnive_test_options']['statnive_import_test'];
		$this->assertSame( 'cancelled', $state['status'] );
	}

	public function test_get_progress_returns_idle_when_no_state(): void {
		$progress = $this->manager->get_progress();

		$this->assertSame( 'idle', $progress['status'] );
		$this->assertNull( $progress['error'] );
	}
}
