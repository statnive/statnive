<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Entity;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Entity\Visitor;
use Statnive\Entity\VisitorProfile;
use WP_UnitTestCase;

/**
 * Integration tests for Visitor entity recording.
 *
 * @covers \Statnive\Entity\Visitor
 */
final class VisitorRecordingTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	public function test_record_inserts_new_visitor(): void {
		global $wpdb;

		$profile = new VisitorProfile();
		$profile->set( 'visitor_hash', random_bytes( 8 ) );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );

		Visitor::record( $profile );

		$visitor_id = $profile->get( 'visitor_id' );
		$this->assertIsInt( $visitor_id );
		$this->assertGreaterThan( 0, $visitor_id );

		$table = TableRegistry::get( 'visitors' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$this->assertSame( 1, $count );
	}

	public function test_record_reuses_existing_visitor_by_hash(): void {
		global $wpdb;

		$hash = random_bytes( 8 );

		$profile1 = new VisitorProfile();
		$profile1->set( 'visitor_hash', $hash );
		$profile1->set( 'timestamp', current_time( 'mysql', true ) );
		Visitor::record( $profile1 );

		$profile2 = new VisitorProfile();
		$profile2->set( 'visitor_hash', $hash );
		$profile2->set( 'timestamp', current_time( 'mysql', true ) );
		Visitor::record( $profile2 );

		$this->assertSame( $profile1->get( 'visitor_id' ), $profile2->get( 'visitor_id' ) );

		$table = TableRegistry::get( 'visitors' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$this->assertSame( 1, $count );
	}

	public function test_different_hash_creates_different_visitor(): void {
		$profile1 = new VisitorProfile();
		$profile1->set( 'visitor_hash', random_bytes( 8 ) );
		$profile1->set( 'timestamp', current_time( 'mysql', true ) );
		Visitor::record( $profile1 );

		$profile2 = new VisitorProfile();
		$profile2->set( 'visitor_hash', random_bytes( 8 ) );
		$profile2->set( 'timestamp', current_time( 'mysql', true ) );
		Visitor::record( $profile2 );

		$this->assertNotSame( $profile1->get( 'visitor_id' ), $profile2->get( 'visitor_id' ) );
	}
}
