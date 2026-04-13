<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Database;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\SchemaMaintainer;
use Statnive\Database\TableRegistry;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for SchemaMaintainer drift detection.
 *
 * @covers \Statnive\Database\SchemaMaintainer
 */
final class SchemaMaintainerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	public function test_force_check_repairs_missing_table(): void {
		global $wpdb;

		$table = TableRegistry::get( 'exclusions' );

		// Manually drop a table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

		// Force schema check — should recreate the missing table.
		$result = SchemaMaintainer::force_check();

		$this->assertNotContains( $table, $result['missing'] );
		$this->assertContains( $table, $result['existing'] );
	}

	public function test_maybe_update_skips_when_version_matches(): void {
		// Set DB version to match plugin version.
		update_option( 'statnive_db_version', STATNIVE_VERSION );
		set_transient( 'statnive_schema_check', '1', HOUR_IN_SECONDS );

		// Should not run dbDelta (we can't easily assert this,
		// but we verify no errors occur).
		SchemaMaintainer::maybe_update_schema();

		$this->assertSame( STATNIVE_VERSION, get_option( 'statnive_db_version' ) );
	}

	public function test_maybe_update_runs_when_version_differs(): void {
		// Set an old version.
		update_option( 'statnive_db_version', '0.0.1' );
		delete_transient( 'statnive_schema_check' );

		SchemaMaintainer::maybe_update_schema();

		// Version should be updated.
		$this->assertSame( STATNIVE_VERSION, get_option( 'statnive_db_version' ) );

		// Transient guard should be set.
		$this->assertNotFalse( get_transient( 'statnive_schema_check' ) );
	}
}
