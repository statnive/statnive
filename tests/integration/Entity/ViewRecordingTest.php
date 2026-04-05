<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Entity;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Entity\Session;
use Statnive\Entity\View;
use Statnive\Entity\Visitor;
use Statnive\Entity\VisitorProfile;
use WP_UnitTestCase;

/**
 * Integration tests for View entity recording.
 *
 * @covers \Statnive\Entity\View
 */
final class ViewRecordingTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	private function create_session_profile(): VisitorProfile {
		$profile = new VisitorProfile();
		$profile->set( 'visitor_hash', random_bytes( 8 ) );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'user_id', 0 );
		$profile->set( 'resource_type', 'post' );
		$profile->set( 'resource_id', 1 );
		Visitor::record( $profile );
		Session::record( $profile );
		return $profile;
	}

	public function test_record_creates_view(): void {
		$profile = $this->create_session_profile();

		View::record( $profile );

		$view_id = $profile->get( 'view_id' );
		$this->assertIsInt( $view_id );
		$this->assertGreaterThan( 0, $view_id );
	}

	public function test_record_creates_resource_uri(): void {
		global $wpdb;

		$profile = $this->create_session_profile();
		View::record( $profile );

		$resource_uri_id = $profile->get( 'resource_uri_id' );
		$this->assertIsInt( $resource_uri_id );
		$this->assertGreaterThan( 0, $resource_uri_id );

		$table = TableRegistry::get( 'resource_uris' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$uri = $wpdb->get_var(
			$wpdb->prepare( "SELECT uri FROM `{$table}` WHERE ID = %d", $resource_uri_id )
		);
		$this->assertSame( '/post/1', $uri );
	}

	public function test_record_skips_when_no_session(): void {
		$profile = new VisitorProfile();
		// No session_id set.

		View::record( $profile );

		$this->assertNull( $profile->get( 'view_id' ) );
	}
}
