<?php
/**
 * Generated from BDD scenarios (03-analytics-enrichment.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Service\DimensionService;
use Statnive\Service\ReferrerService;
use Statnive\Service\SourceDetector;
use WP_UnitTestCase;

/**
 * Integration tests for traffic source classification.
 *
 * @covers \Statnive\Service\SourceDetector
 * @covers \Statnive\Service\ReferrerService
 */
final class SourceDetectorTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		DimensionService::clear_cache();
	}

	/**
	 * @testdox Referrer domain classifies into correct traffic channel
	 * @dataProvider channel_mapping_provider
	 *
	 * @param string $referrer        Referrer URL.
	 * @param string $expected_channel Expected channel.
	 * @param string $expected_name    Expected source name.
	 */
	public function test_referrer_classifies_into_correct_channel( string $referrer, string $expected_channel, string $expected_name ): void {
		$domain = ReferrerService::extract_domain( $referrer );
		$result = SourceDetector::classify( $domain, $referrer );

		$this->assertSame( $expected_channel, $result['channel'], "Referrer should classify as '{$expected_channel}' channel" );
		$this->assertSame( $expected_name, $result['name'], "Source name should be '{$expected_name}'" );
	}

	/**
	 * Data provider for 7 referrer-to-channel mappings.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function channel_mapping_provider(): array {
		return [
			'Google organic'      => [ 'https://www.google.com/search?q=wordpress+analytics', 'Organic Search', 'Google' ],
			'Bing organic'        => [ 'https://www.bing.com/search?q=statnive', 'Organic Search', 'Bing' ],
			'Facebook social'     => [ 'https://www.facebook.com/share/12345', 'Social Media', 'Facebook' ],
			'Twitter/X social'    => [ 'https://t.co/abc123', 'Social Media', 'Twitter/X' ],
			'Email (Outlook)'     => [ 'https://outlook.live.com/', 'Email', 'outlook.live.com' ],
			'Referral'            => [ 'https://blog.example.org/article', 'Referral', 'blog.example.org' ],
			'Direct (empty)'      => [ '', 'Direct', '' ],
		];
	}

	/**
	 * @testdox UTM medium overrides referrer classification
	 */
	public function test_utm_medium_overrides_referrer_classification(): void {
		$domain = ReferrerService::extract_domain( 'https://www.google.com/search?q=statnive' );
		$result = SourceDetector::classify( $domain, 'https://www.google.com/search?q=statnive', 'cpc' );

		$this->assertSame( 'Paid Search', $result['channel'], 'UTM medium "cpc" should override to Paid Search channel' );
	}

	/**
	 * @testdox Spam domain is rejected
	 */
	public function test_spam_domain_rejected(): void {
		$domain  = ReferrerService::extract_domain( 'https://semalt.com/crawler?target=shop.example.com' );
		$is_spam = ReferrerService::is_spam( $domain );

		$this->assertTrue( $is_spam, 'semalt.com should be identified as referrer spam' );
	}

	/**
	 * @testdox Self-referral is filtered
	 */
	public function test_self_referral_filtered(): void {
		// home_url() in tests defaults to the test site URL.
		$site_url    = home_url();
		$is_self_ref = ReferrerService::is_self_referral( $site_url . '/about' );

		$this->assertTrue( $is_self_ref, 'URL from same domain as home_url should be detected as self-referral' );
	}

	/**
	 * @testdox CRC32 domain hash dedup in referrers dimension table
	 */
	public function test_crc32_domain_hash_dedup(): void {
		global $wpdb;

		// Resolve the same referrer domain twice with different URLs.
		$id1 = DimensionService::resolve_referrer( 'Referral', 'blog.example.org', 'blog.example.org' );
		$id2 = DimensionService::resolve_referrer( 'Referral', 'blog.example.org', 'blog.example.org' );

		$this->assertSame( $id1, $id2, 'Resolving the same domain twice should return the same ID' );

		$referrers = TableRegistry::get( 'referrers' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$referrers}` WHERE domain = %s", 'blog.example.org' )
		);
		$this->assertSame( 1, $count, 'CRC32 dedup should store exactly 1 row for the same domain' );

		// Verify domain_hash is non-zero.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$hash = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT domain_hash FROM `{$referrers}` WHERE domain = %s", 'blog.example.org' )
		);
		$this->assertNotSame( 0, $hash, 'Domain hash (CRC32) should be a non-zero value' );
	}
}
