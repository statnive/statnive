<?php
/**
 * Generated from BDD scenarios (07-privacy-compliance.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Privacy;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Privacy\PrivacyEraser;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the WordPress privacy data eraser.
 *
 * @covers \Statnive\Privacy\PrivacyEraser
 */
final class PrivacyEraserTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
	}

	/**
	 * Insert sessions for a user with view counts.
	 *
	 * @param int $user_id     WordPress user ID.
	 * @param int $num_sessions Number of sessions.
	 * @return array<int> Session IDs.
	 */
	private function insert_sessions( int $user_id, int $num_sessions ): array {
		global $wpdb;

		$visitors = TableRegistry::get( 'visitors' );
		$sessions = TableRegistry::get( 'sessions' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $visitors, [
			'hash'       => random_bytes( 8 ),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		] );
		$visitor_id = (int) $wpdb->insert_id;

		$session_ids = [];
		for ( $i = 0; $i < $num_sessions; $i++ ) {
			$wpdb->insert( $sessions, [
				'visitor_id'  => $visitor_id,
				'user_id'     => $user_id,
				'ip_hash'     => random_bytes( 8 ),
				'started_at'  => gmdate( 'Y-m-d H:i:s', time() - ( $i * 3600 ) ),
				'total_views' => 3 + $i,
				'duration'    => 300 + ( $i * 60 ),
			] );
			$session_ids[] = (int) $wpdb->insert_id;
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return $session_ids;
	}

	/**
	 * @testdox Anonymizes visitor sessions, preserves aggregate counts
	 */
	public function test_eraser_anonymizes_sessions_preserves_counts(): void {
		global $wpdb;

		$user_id = self::factory()->user->create( [
			'user_email' => 'testuser@example.com',
			'role'       => 'subscriber',
		] );

		$session_ids = $this->insert_sessions( $user_id, 5 );
		$sessions    = TableRegistry::get( 'sessions' );

		// Record original total_views for comparison.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$original_views = $wpdb->get_col( "SELECT total_views FROM `{$sessions}` ORDER BY ID ASC" );

		$result = PrivacyEraser::erase( 'testuser@example.com' );

		$this->assertSame( 5, $result['items_removed'], 'Eraser should report 5 items removed' );
		$this->assertSame( 0, $result['items_retained'], 'Eraser should report 0 items retained' );
		$this->assertTrue( $result['done'], 'Eraser should report done as true' );

		// Verify anonymization.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		foreach ( $session_ids as $sid ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT user_id, visitor_id, ip_hash FROM `{$sessions}` WHERE ID = %d", $sid )
			);
			$this->assertNull( $row->user_id, "Session {$sid} user_id should be null after anonymization" );
			$this->assertNull( $row->visitor_id, "Session {$sid} visitor_id should be null after anonymization" );
			// binary(8) column pads '' with null bytes, so check for zero-filled value.
			$this->assertSame( str_repeat( "\0", 8 ), $row->ip_hash, "Session {$sid} ip_hash should be zeroed after anonymization" );
		}

		// Verify total_views remain unchanged.
		$post_views = $wpdb->get_col( "SELECT total_views FROM `{$sessions}` ORDER BY ID ASC" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( $original_views, $post_views, 'Aggregate total_views should be preserved after anonymization' );
	}

	/**
	 * @testdox Eraser returns empty result for unknown user
	 */
	public function test_eraser_returns_empty_for_unknown_user(): void {
		$result = PrivacyEraser::erase( 'nobody@example.com' );

		$this->assertSame( 0, $result['items_removed'], 'Eraser should report 0 items removed for unknown user' );
		$this->assertSame( 0, $result['items_retained'], 'Eraser should report 0 items retained for unknown user' );
		$this->assertTrue( $result['done'], 'Eraser should report done as true for unknown user' );
	}
}
