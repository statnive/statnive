<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Entity;

use Statnive\Api\RealtimeController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Entity\Session;
use Statnive\Entity\View;
use Statnive\Entity\Visitor;
use Statnive\Entity\VisitorProfile;
use WP_REST_Request;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for resource deduplication in the View entity.
 *
 * Covers bugs #6 (duplicate resources) and #7 (INSERT IGNORE race conditions).
 *
 * @covers \Statnive\Entity\View
 */
final class ResourceDeduplicationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		delete_transient( 'statnive_realtime' );

		// Set admin user for permission checks on realtime endpoint.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Create a VisitorProfile with a session, ready for View::record().
	 *
	 * @param int    $resource_id   Resource ID.
	 * @param string $resource_type Resource type.
	 * @param string $page_url      Page URL for URI resolution.
	 * @return VisitorProfile
	 */
	private function create_session_profile( int $resource_id = 1, string $resource_type = 'post', string $page_url = '' ): VisitorProfile {
		$profile = new VisitorProfile();
		$profile->set( 'visitor_hash', random_bytes( 8 ) );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'user_id', 0 );
		$profile->set( 'resource_type', $resource_type );
		$profile->set( 'resource_id', $resource_id );
		if ( ! empty( $page_url ) ) {
			$profile->set( 'page_url', $page_url );
		}

		Visitor::record( $profile );
		Session::record( $profile );

		return $profile;
	}

	/**
	 * @testdox View::record() reuses existing resource row for the same resource_type+resource_id
	 */
	public function test_resolve_resource_reuses_existing(): void {
		global $wpdb;

		$resources = TableRegistry::get( 'resources' );

		// Record first view for resource_type=post, resource_id=1.
		$profile1 = $this->create_session_profile( 1, 'post' );
		View::record( $profile1 );

		// Record second view for same resource (different session/visitor).
		$profile2 = $this->create_session_profile( 1, 'post' );
		View::record( $profile2 );

		// Count resources rows with resource_type='post' AND resource_id=1.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$resources}` WHERE resource_type = %s AND resource_id = %d",
				'post',
				1
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 1, $count, 'Resources table should have exactly 1 row for resource_type=post, resource_id=1 after 2 views' );
	}

	/**
	 * @testdox INSERT IGNORE handles pre-existing resource row without creating duplicates
	 */
	public function test_insert_ignore_handles_race_condition(): void {
		global $wpdb;

		$resources = TableRegistry::get( 'resources' );

		// Manually insert a resource row (simulating a concurrent request that won the race).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $resources, [
			'resource_type' => 'post',
			'resource_id'   => 99,
			'cached_title'  => 'Pre-existing Resource',
		] );
		$pre_existing_id = (int) $wpdb->insert_id;
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Now call View::record() for the same resource.
		$profile = $this->create_session_profile( 99, 'post' );
		View::record( $profile );

		// Verify no duplicate.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$resources}` WHERE resource_type = %s AND resource_id = %d",
				'post',
				99
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 1, $count, 'No duplicate resource row should be created when one already exists' );

		// Verify the view got the correct resource_id linkage.
		$views_table = TableRegistry::get( 'views' );
		$view_id     = $profile->get( 'view_id' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$view_resource_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT resource_id FROM `{$views_table}` WHERE ID = %d",
				$view_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( $pre_existing_id, $view_resource_id, 'View should reference the pre-existing resource ID' );
	}

	/**
	 * @testdox Realtime recent_feed has no duplicate entries from pre-existing duplicate resource rows
	 */
	public function test_realtime_no_duplicate_views_from_resource_dupes(): void {
		global $wpdb;

		$resources = TableRegistry::get( 'resources' );
		$uris      = TableRegistry::get( 'resource_uris' );
		$visitors  = TableRegistry::get( 'visitors' );
		$sessions  = TableRegistry::get( 'sessions' );
		$views     = TableRegistry::get( 'views' );
		$countries = TableRegistry::get( 'countries' );
		$browsers  = TableRegistry::get( 'device_browsers' );

		$time = gmdate( 'Y-m-d H:i:s', time() - 60 );

		// Insert 2 duplicate resource rows for resource_id=50 (simulating pre-existing bug data).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $resources, [
			'resource_type' => 'post',
			'resource_id'   => 50,
			'cached_title'  => 'Duplicate Resource',
		] );
		$wpdb->insert( $resources, [
			'resource_type' => 'post',
			'resource_id'   => 50,
			'cached_title'  => 'Duplicate Resource',
		] );

		// Insert country and browser dimensions.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$countries}` (code, name) VALUES (%s, %s)",
				'US',
				'US'
			)
		);
		$country_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$countries}` WHERE code = %s", 'US' )
		);

		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$browsers}` (name) VALUES (%s)",
				'Chrome'
			)
		);
		$browser_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$browsers}` WHERE name = %s", 'Chrome' )
		);

		// Insert resource URI with resource_id=50.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$uris}` (uri, uri_hash, resource_id) VALUES (%s, %d, %d)",
				'/duped-page',
				crc32( '/duped-page' ),
				50
			)
		);
		$uri_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM `{$uris}` WHERE uri = %s", '/duped-page' )
		);

		// Insert 1 visitor, 1 session, 1 view.
		$wpdb->insert( $visitors, [
			'hash'       => random_bytes( 8 ),
			'created_at' => $time,
		] );
		$visitor_id = (int) $wpdb->insert_id;

		$wpdb->insert( $sessions, [
			'visitor_id'        => $visitor_id,
			'started_at'        => $time,
			'total_views'       => 1,
			'country_id'        => $country_id,
			'device_browser_id' => $browser_id,
		] );
		$session_id = (int) $wpdb->insert_id;

		$wpdb->insert( $views, [
			'session_id'      => $session_id,
			'resource_uri_id' => $uri_id,
			'viewed_at'       => $time,
		] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$realtime = new RealtimeController();
		$request  = new WP_REST_Request( 'GET', '/statnive/v1/realtime' );
		$response = $realtime->get_items( $request );
		$data     = $response->get_data();

		// Count entries in recent_feed for /duped-page.
		$duped_entries = array_filter(
			$data['recent_feed'],
			fn( $entry ) => $entry['uri'] === '/duped-page'
		);

		$this->assertCount( 1, $duped_entries, 'Recent feed should have exactly 1 entry for a single view, even with duplicate resource rows' );
	}
}
