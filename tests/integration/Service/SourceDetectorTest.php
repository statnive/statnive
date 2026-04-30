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

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

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
	 * Data provider for referrer-to-channel mappings.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function channel_mapping_provider(): array {
		return [
			'Google organic'                => [ 'https://www.google.com/search?q=wordpress+analytics', 'Organic Search', 'Google' ],
			'Google ccTLD (UK)'             => [ 'https://www.google.co.uk/search?q=x', 'Organic Search', 'Google' ],
			'Google ccTLD (Germany)'        => [ 'https://www.google.de/search?q=x', 'Organic Search', 'Google' ],
			'Google subdomain (Mail)'       => [ 'https://mail.google.com/', 'Email', 'mail.google.com' ],
			'Bing organic'                  => [ 'https://www.bing.com/search?q=statnive', 'Organic Search', 'Bing' ],
			'Brave search subdomain'        => [ 'https://search.brave.com/search?q=x', 'Organic Search', 'Brave' ],
			'AI Assistants — Gemini'        => [ 'https://gemini.google.com/', 'AI Assistants', 'Gemini' ],
			'AI Assistants — NotebookLM'    => [ 'https://notebooklm.google.com/', 'AI Assistants', 'NotebookLM' ],
			'AI Assistants — Copilot'       => [ 'https://copilot.microsoft.com/', 'AI Assistants', 'Copilot' ],
			'AI Assistants — ChatGPT (new)' => [ 'https://chatgpt.com/', 'AI Assistants', 'ChatGPT' ],
			'AI Assistants — ChatGPT (legacy)' => [ 'https://chat.openai.com/', 'AI Assistants', 'ChatGPT' ],
			'AI Assistants — Claude'        => [ 'https://claude.ai/', 'AI Assistants', 'Claude' ],
			'AI Assistants — Perplexity'    => [ 'https://perplexity.ai/', 'AI Assistants', 'Perplexity' ],
			'AI Assistants — Le Chat'       => [ 'https://chat.mistral.ai/', 'AI Assistants', 'Le Chat' ],
			'Facebook social'               => [ 'https://www.facebook.com/share/12345', 'Social Media', 'Facebook' ],
			'Facebook mobile subdomain'     => [ 'https://m.facebook.com/share/12345', 'Social Media', 'Facebook' ],
			'Facebook outbound wrapper'     => [ 'https://l.facebook.com/l.php?u=https%3A%2F%2Fexample.com', 'Social Media', 'Facebook' ],
			'Twitter/X t.co shortener'      => [ 'https://t.co/abc123', 'Social Media', 'Twitter/X' ],
			'Twitter/X x.com'               => [ 'https://x.com/user/status/1', 'Social Media', 'Twitter/X' ],
			'Twitter/X mobile subdomain'    => [ 'https://mobile.twitter.com/user', 'Social Media', 'Twitter/X' ],
			'LinkedIn shortener'            => [ 'https://lnkd.in/abc', 'Social Media', 'LinkedIn' ],
			'YouTube shortener'             => [ 'https://youtu.be/dQw4w9WgXcQ', 'Social Media', 'YouTube' ],
			'TikTok shortener'              => [ 'https://vm.tiktok.com/abc', 'Social Media', 'TikTok' ],
			'Email (Outlook)'               => [ 'https://outlook.live.com/', 'Email', 'outlook.live.com' ],
			'Referral'                      => [ 'https://blog.example.org/article', 'Referral', 'blog.example.org' ],
			'Direct (empty)'                => [ '', 'Direct', '' ],
		];
	}

	/**
	 * @testdox Domains containing brand substrings do not falsely classify
	 * @dataProvider false_positive_provider
	 *
	 * Regression guard for the v0.4.5 substring-match bug — `str_contains($domain, 't.co')`
	 * mis-classified any host ending in `t.com` as Twitter/X. See production data in the
	 * referrers table where 13/13 "Twitter/X" rows were unrelated sites.
	 */
	public function test_false_positive_substring_does_not_classify( string $domain ): void {
		$result = SourceDetector::classify( $domain, '', '' );
		$this->assertSame(
			'Referral',
			$result['channel'],
			"Domain '{$domain}' must not be classified into a known channel — got '{$result['channel']}/{$result['name']}'"
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function false_positive_provider(): array {
		return [
			// Real production data that mis-classified as Twitter/X under the old `t.co`/`x.com` substring rule.
			'tantei-mt.com (mt.com contains t.co)'                => [ 'tantei-mt.com' ],
			'kasimarket.com (et.com contains t.co)'               => [ 'kasimarket.com' ],
			'bordafax.com (suffix x.com)'                         => [ 'bordafax.com' ],
			'chatgpt.com (pt.com contains t.co)'                  => [ 'chatgpt.com' ],
			'staging.wp-slimstat.com (at.com contains t.co)'      => [ 'staging.wp-slimstat.com' ],
			'www.hudsonplaceresidencesshowflat.com'               => [ 'www.hudsonplaceresidencesshowflat.com' ],
			'belksasar3dprint.com (nt.com contains t.co)'         => [ 'belksasar3dprint.com' ],
			// Hypothetical near-misses for other brands.
			'googleads.example.com (substring "google")'          => [ 'googleads.example.com' ],
			'notgoogle.com'                                       => [ 'notgoogle.com' ],
			'fake-twitter.org (substring "twitter")'              => [ 'fake-twitter.org' ],
			'bravecruz.com (substring "brave")'                   => [ 'bravecruz.com' ],
			'instagrammers.example.com (substring "instagram")'   => [ 'instagrammers.example.com' ],
			'redditor-info.com (substring "reddit")'              => [ 'redditor-info.com' ],
			// AI-channel false-positive guards (research-recommended #9).
			'tantei-mt.ai (suffix .ai but not on AI list)'        => [ 'tantei-mt.ai' ],
			'chatgpt-clone.example.com (substring "chatgpt")'     => [ 'chatgpt-clone.example.com' ],
			'notchatgpt.com'                                      => [ 'notchatgpt.com' ],
			'fake-claude.com (substring "claude")'                => [ 'fake-claude.com' ],
			'perplexity-research.example.org'                     => [ 'perplexity-research.example.org' ],
			'jasper-ai-tutorials.com (substring "jasper.ai")'     => [ 'jasper-ai-tutorials.com' ],
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
