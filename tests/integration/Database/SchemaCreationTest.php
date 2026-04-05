<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Database;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\SchemaDefinition;
use Statnive\Database\TableRegistry;
use WP_UnitTestCase;

/**
 * Integration tests for database schema creation.
 *
 * Verifies that all 21 tables are created correctly via dbDelta.
 * Requires WordPress test framework (WP_UnitTestCase).
 *
 * @covers \Statnive\Database\SchemaDefinition
 * @covers \Statnive\Database\DatabaseFactory
 * @covers \Statnive\Database\TableRegistry
 */
final class SchemaCreationTest extends WP_UnitTestCase {

	public function test_create_tables_creates_all_21_tables(): void {
		DatabaseFactory::create_tables();

		$check = DatabaseFactory::check_tables();

		$this->assertCount( 0, $check['missing'], 'Missing tables: ' . implode( ', ', $check['missing'] ) );
		$this->assertCount( 21, $check['existing'] );
	}

	public function test_visitors_table_has_binary_hash_column(): void {
		global $wpdb;

		DatabaseFactory::create_tables();
		$table = TableRegistry::get( 'visitors' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		$hash_col = array_filter( $columns, fn( $c ) => 'hash' === $c['Field'] );
		$hash_col = reset( $hash_col );

		$this->assertNotFalse( $hash_col );
		$this->assertStringContainsString( 'binary(8)', $hash_col['Type'] );
	}

	public function test_sessions_table_has_covering_indexes(): void {
		global $wpdb;

		DatabaseFactory::create_tables();
		$table = TableRegistry::get( 'sessions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
		$index_names = array_unique( array_column( $indexes, 'Key_name' ) );

		$expected_indexes = [
			'idx_visitor_id',
			'idx_started_at',
			'idx_started_visitor',
			'idx_started_referrer',
			'idx_started_country',
		];

		foreach ( $expected_indexes as $idx ) {
			$this->assertContains( $idx, $index_names, "Missing index: {$idx}" );
		}
	}

	public function test_table_registry_returns_correct_count(): void {
		$names = TableRegistry::all_names();

		$this->assertCount( 21, $names );
		$this->assertContains( 'visitors', $names );
		$this->assertContains( 'sessions', $names );
		$this->assertContains( 'views', $names );
		$this->assertContains( 'summary', $names );
	}

	public function test_get_sql_returns_non_empty_string(): void {
		$sql = SchemaDefinition::get_sql();

		$this->assertNotEmpty( $sql );
		$this->assertStringContainsString( 'CREATE TABLE', $sql );
		$this->assertStringContainsString( 'statnive_visitors', $sql );
		$this->assertStringContainsString( 'statnive_sessions', $sql );
	}
}
