<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Entity;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Entity\Session;
use Statnive\Entity\Visitor;
use Statnive\Entity\VisitorProfile;
use WP_UnitTestCase;

/**
 * Integration tests for Session entity recording.
 *
 * @covers \Statnive\Entity\Session
 */
final class SessionRecordingTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	private function create_visitor_profile(): VisitorProfile {
		$profile = new VisitorProfile();
		$profile->set( 'visitor_hash', random_bytes( 8 ) );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'user_id', 0 );
		Visitor::record( $profile );
		return $profile;
	}

	public function test_record_creates_new_session(): void {
		$profile = $this->create_visitor_profile();

		Session::record( $profile );

		$session_id = $profile->get( 'session_id' );
		$this->assertIsInt( $session_id );
		$this->assertGreaterThan( 0, $session_id );
	}

	public function test_record_reuses_session_within_timeout(): void {
		global $wpdb;

		$profile = $this->create_visitor_profile();

		// First hit.
		Session::record( $profile );
		$first_session_id = $profile->get( 'session_id' );

		// Second hit from same visitor (within 30min timeout).
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'session_id', null );
		Session::record( $profile );
		$second_session_id = $profile->get( 'session_id' );

		$this->assertSame( $first_session_id, $second_session_id );

		// Verify total_views was incremented.
		$table = TableRegistry::get( 'sessions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_views = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT total_views FROM `{$table}` WHERE ID = %d", $first_session_id )
		);
		$this->assertSame( 2, $total_views );
	}

	public function test_record_skips_when_no_visitor_id(): void {
		$profile = new VisitorProfile();
		// No visitor_id set.

		Session::record( $profile );

		$this->assertNull( $profile->get( 'session_id' ) );
	}

	public function test_session_links_to_correct_visitor(): void {
		global $wpdb;

		$profile = $this->create_visitor_profile();
		Session::record( $profile );

		$table = TableRegistry::get( 'sessions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$visitor_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT visitor_id FROM `{$table}` WHERE ID = %d", $profile->get( 'session_id' ) )
		);

		$this->assertSame( $profile->get( 'visitor_id' ), $visitor_id );
	}
}
