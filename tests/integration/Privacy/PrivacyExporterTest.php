<?php
/**
 * Generated from BDD scenarios (07-privacy-compliance.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Privacy;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Privacy\PrivacyExporter;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the WordPress privacy data exporter.
 *
 * @covers \Statnive\Privacy\PrivacyExporter
 */
final class PrivacyExporterTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
	}

	/**
	 * Insert test sessions for a user.
	 *
	 * @param int $user_id     WordPress user ID.
	 * @param int $num_sessions Number of sessions to create.
	 */
	private function insert_sessions_for_user( int $user_id, int $num_sessions ): void {
		global $wpdb;

		$visitors = TableRegistry::get( 'visitors' );
		$sessions = TableRegistry::get( 'sessions' );
		$views    = TableRegistry::get( 'views' );
		$uris     = TableRegistry::get( 'resource_uris' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $visitors, [
			'hash'       => random_bytes( 8 ),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		] );
		$visitor_id = (int) $wpdb->insert_id;

		// Insert a URI.
		$wpdb->insert( $uris, [
			'uri'      => '/test-page',
			'uri_hash' => crc32( '/test-page' ),
		] );
		$uri_id = (int) $wpdb->insert_id;

		for ( $i = 0; $i < $num_sessions; $i++ ) {
			$started_at = gmdate( 'Y-m-d H:i:s', time() - ( $i * 3600 ) );
			$ended_at   = gmdate( 'Y-m-d H:i:s', time() - ( $i * 3600 ) + 300 );

			$wpdb->insert( $sessions, [
				'visitor_id'  => $visitor_id,
				'user_id'     => $user_id,
				'started_at'  => $started_at,
				'ended_at'    => $ended_at,
				'total_views' => 3,
				'duration'    => 300,
			] );
			$session_id = (int) $wpdb->insert_id;

			// Insert a view for the session.
			$wpdb->insert( $views, [
				'session_id'      => $session_id,
				'resource_uri_id' => $uri_id,
				'viewed_at'       => $started_at,
			] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @testdox Exports all session data for logged-in user
	 */
	public function test_exports_all_session_data_for_logged_in_user(): void {
		$user_id = self::factory()->user->create( [
			'user_email' => 'testuser@example.com',
			'role'       => 'subscriber',
		] );

		$this->insert_sessions_for_user( $user_id, 3 );

		$result = PrivacyExporter::export( 'testuser@example.com' );

		$this->assertTrue( $result['done'], 'Exporter should report done as true' );
		$this->assertCount( 3, $result['data'], 'Exporter should return 3 session records' );

		// Each item should belong to the statnive-sessions group.
		foreach ( $result['data'] as $item ) {
			$this->assertSame( 'statnive-sessions', $item['group_id'], 'Each exported item should belong to statnive-sessions group' );

			// Check required fields exist.
			$field_names = array_column( $item['data'], 'name' );
			$this->assertContains( 'Session Start', $field_names, 'Exported data should contain Session Start field' );
			$this->assertContains( 'Session End', $field_names, 'Exported data should contain Session End field' );
			$this->assertContains( 'Pages Viewed', $field_names, 'Exported data should contain Pages Viewed field' );
			$this->assertContains( 'Duration (seconds)', $field_names, 'Exported data should contain Duration field' );
		}
	}

	/**
	 * @testdox Export returns empty for unknown email
	 */
	public function test_export_returns_empty_for_unknown_email(): void {
		$result = PrivacyExporter::export( 'nobody@example.com' );

		$this->assertTrue( $result['done'], 'Exporter should report done as true for unknown email' );
		$this->assertEmpty( $result['data'], 'Exporter should return empty data for unknown email' );
	}
}
