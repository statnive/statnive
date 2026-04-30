<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Database;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\Migrator;
use Statnive\Database\TableRegistry;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for Migrator::migrate_reclassify_referrers().
 *
 * Seeds rows in `wp_statnive_referrers` that would have been mis-classified
 * by the pre-0.4.6 substring-matching `SourceDetector::classify()` and verifies
 * the migration flips them back to their correct channel/name.
 *
 * @covers \Statnive\Database\Migrator::migrate_reclassify_referrers
 */
final class MigratorReclassifyTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		delete_option( Migrator::RECLASSIFY_CURSOR_OPTION );
	}

	private function insert_referrer( string $channel, string $name, string $domain ): int {
		global $wpdb;
		$table = TableRegistry::get( 'referrers' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			[
				'channel'     => $channel,
				'name'        => $name,
				'domain'      => $domain,
				'domain_hash' => crc32( $domain ),
			]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	private function fetch_referrer( int $id ): array {
		global $wpdb;
		$table = TableRegistry::get( 'referrers' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT channel, name, domain FROM `{$table}` WHERE ID = %d", $id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		return is_array( $row ) ? $row : [];
	}

	/**
	 * @testdox Twitter/X false positives flip back to Referral
	 */
	public function test_false_positive_twitter_rows_reclassify_to_referral(): void {
		$id = $this->insert_referrer( 'Social Media', 'Twitter/X', 'tantei-mt.com' );

		Migrator::migrate_reclassify_referrers();

		$row = $this->fetch_referrer( $id );
		$this->assertSame( 'Referral', $row['channel'] );
		$this->assertSame( 'tantei-mt.com', $row['name'] );
		$this->assertSame( 'tantei-mt.com', $row['domain'], 'domain column must remain unchanged' );
	}

	/**
	 * @testdox Legitimate Google rows are left untouched
	 */
	public function test_legitimate_google_rows_unchanged(): void {
		$id = $this->insert_referrer( 'Organic Search', 'Google', 'www.google.com' );

		Migrator::migrate_reclassify_referrers();

		$row = $this->fetch_referrer( $id );
		$this->assertSame( 'Organic Search', $row['channel'] );
		$this->assertSame( 'Google', $row['name'] );
	}

	/**
	 * @testdox Migration is idempotent on a clean dataset
	 */
	public function test_idempotent_on_already_correct_rows(): void {
		$id = $this->insert_referrer( 'Referral', 'tantei-mt.com', 'tantei-mt.com' );

		Migrator::migrate_reclassify_referrers();
		Migrator::migrate_reclassify_referrers();

		$row = $this->fetch_referrer( $id );
		$this->assertSame( 'Referral', $row['channel'] );
		$this->assertSame( 'tantei-mt.com', $row['name'] );
	}

	/**
	 * @testdox Empty-domain rows are skipped (Direct + UTM-only paid traffic)
	 */
	public function test_empty_domain_rows_skipped(): void {
		$id = $this->insert_referrer( 'Direct', '', '' );

		Migrator::migrate_reclassify_referrers();

		$row = $this->fetch_referrer( $id );
		$this->assertSame( 'Direct', $row['channel'] );
		$this->assertSame( '', $row['name'] );
	}

	/**
	 * @testdox Mixed batch — false positives flip, real Twitter rows stay
	 */
	public function test_mixed_batch_partial_reclassification(): void {
		$false_pos = $this->insert_referrer( 'Social Media', 'Twitter/X', 'bordafax.com' );
		$real_x    = $this->insert_referrer( 'Social Media', 'Twitter/X', 't.co' );
		$google    = $this->insert_referrer( 'Organic Search', 'Google', 'www.google.de' );

		Migrator::migrate_reclassify_referrers();

		$this->assertSame( 'Referral', $this->fetch_referrer( $false_pos )['channel'] );
		$this->assertSame( 'Social Media', $this->fetch_referrer( $real_x )['channel'] );
		$this->assertSame( 'Twitter/X', $this->fetch_referrer( $real_x )['name'] );
		$this->assertSame( 'Organic Search', $this->fetch_referrer( $google )['channel'] );
	}
}
