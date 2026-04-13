<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Database\Migrator;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Unit tests for the database Migrator.
 *
 * Uses the bootstrap option stubs (get_option / update_option) so no
 * real WordPress or database connection is needed.
 */
#[CoversClass(Migrator::class)]
final class MigratorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_options'] = [];
	}

	public function test_no_migration_runs_when_version_matches(): void {
		// Pretend the stored version already matches the running version.
		$GLOBALS['statnive_test_options'][ Migrator::OPTION ] = STATNIVE_VERSION;

		Migrator::run();

		// Option should remain unchanged — no update_option call overwrites it.
		$this->assertSame(
			STATNIVE_VERSION,
			$GLOBALS['statnive_test_options'][ Migrator::OPTION ]
		);
	}

	public function test_option_updated_when_stored_version_is_older(): void {
		$GLOBALS['statnive_test_options'][ Migrator::OPTION ] = '0.0.1';

		Migrator::run();

		$this->assertSame(
			STATNIVE_VERSION,
			$GLOBALS['statnive_test_options'][ Migrator::OPTION ],
			'Stored db version should be bumped to the running version.'
		);
	}

	public function test_option_syncs_when_no_migrations_registered(): void {
		// No stored version at all — defaults to '0.0.0' inside run().
		Migrator::run();

		$this->assertSame(
			STATNIVE_VERSION,
			$GLOBALS['statnive_test_options'][ Migrator::OPTION ],
			'Even with zero migrations, the option must sync to the running version.'
		);
	}
}
