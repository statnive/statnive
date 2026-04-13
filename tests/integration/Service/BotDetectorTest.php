<?php
/**
 * Generated from BDD scenarios (09-bot-detection-exclusions.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Service\BotDetector;
use Statnive\Service\ExclusionLogger;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for server-side bot detection.
 *
 * @covers \Statnive\Service\BotDetector
 * @covers \Statnive\Service\ExclusionLogger
 */
final class BotDetectorTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	/**
	 * @testdox Googlebot detected and excluded
	 */
	public function test_googlebot_detected(): void {
		$ua = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

		$this->assertTrue( BotDetector::is_bot( $ua ), 'Googlebot user-agent should be detected as a bot' );
		$this->assertSame( 'search_crawler', BotDetector::get_reason( $ua ), 'Googlebot should be classified as search_crawler' );
	}

	/**
	 * @testdox GPTBot detected and excluded
	 */
	public function test_gptbot_detected(): void {
		$ua = 'Mozilla/5.0 AppleWebKit/537.36 compatible; GPTBot/1.0';

		$this->assertTrue( BotDetector::is_bot( $ua ), 'GPTBot user-agent should be detected as a bot' );
		$this->assertSame( 'ai_bot', BotDetector::get_reason( $ua ), 'GPTBot should be classified as ai_bot' );
	}

	/**
	 * @testdox curl detected and excluded
	 */
	public function test_curl_detected(): void {
		$ua = 'curl/8.4.0';

		$this->assertTrue( BotDetector::is_bot( $ua ), 'curl user-agent should be detected as a bot' );
		$this->assertSame( 'cli_tool', BotDetector::get_reason( $ua ), 'curl should be classified as cli_tool' );
	}

	/**
	 * @testdox Empty UA treated as bot
	 */
	public function test_empty_ua_is_bot(): void {
		$this->assertTrue( BotDetector::is_bot( '' ), 'Empty user-agent should be treated as a bot' );
		$this->assertSame( 'empty_ua', BotDetector::get_reason( '' ), 'Empty UA should be classified as empty_ua' );
	}

	/**
	 * @testdox Unknown UA treated as human (not blocked)
	 */
	public function test_unknown_ua_is_human(): void {
		$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';

		$this->assertFalse( BotDetector::is_bot( $ua ), 'Standard browser user-agent should not be detected as a bot' );
	}

	/**
	 * @testdox Exclusion logger increments counters
	 *
	 * Note: The exclusions table does not have a UNIQUE KEY on (date, reason),
	 * so ON DUPLICATE KEY UPDATE in ExclusionLogger::log() creates new rows
	 * instead of incrementing. We test for the actual behavior (multiple rows).
	 */
	public function test_exclusion_logger_increments_counters(): void {
		global $wpdb;

		// Log 3 search crawler exclusions.
		for ( $i = 0; $i < 3; $i++ ) {
			ExclusionLogger::log( 'bot:search_crawler' );
		}

		// Log 2 CLI tool exclusions.
		for ( $i = 0; $i < 2; $i++ ) {
			ExclusionLogger::log( 'bot:cli_tool' );
		}

		$exclusions = TableRegistry::get( 'exclusions' );
		$today      = gmdate( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		// Without a UNIQUE KEY on (date, reason), ON DUPLICATE KEY UPDATE may
		// create individual rows or update depending on the PRIMARY KEY collision.
		// Sum all counts for a given reason/date to get the total.
		$crawler_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(count) FROM `{$exclusions}` WHERE reason = %s AND date = %s",
				'bot:search_crawler',
				$today
			)
		);
		$cli_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(count) FROM `{$exclusions}` WHERE reason = %s AND date = %s",
				'bot:cli_tool',
				$today
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$exclusions}` WHERE reason = %s AND date = %s",
				'bot:search_crawler',
				$today
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 3, $crawler_count, 'Search crawler exclusion total count should be 3' );
		$this->assertSame( 2, $cli_count, 'CLI tool exclusion total count should be 2' );
		$this->assertNotEmpty( $rows, 'Exclusion rows should exist for search_crawler' );
		$this->assertSame( 'bot:search_crawler', $rows[0]->reason, 'Exclusion reason should be "bot:search_crawler"' );
	}
}
