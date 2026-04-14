<?php
/**
 * Generated from BDD scenarios (09-bot-detection-exclusions.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Database\DatabaseFactory;
use Statnive\Service\ExclusionMatcher;
use Statnive\Service\ReferrerService;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for exclusion rule matching.
 *
 * @covers \Statnive\Service\ExclusionMatcher
 */
final class ExclusionMatcherTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	/**
	 * @testdox IP within CIDR range blocked
	 */
	public function test_ip_within_cidr_range_blocked(): void {
		update_option( 'statnive_excluded_ips', "203.0.113.0/24" );

		$this->assertTrue( ExclusionMatcher::is_excluded_ip( '203.0.113.42' ), 'IP 203.0.113.42 should be blocked by CIDR /24 range' );
		$this->assertTrue( ExclusionMatcher::is_excluded_ip( '203.0.113.1' ), 'IP 203.0.113.1 should be blocked by CIDR /24 range' );
		$this->assertFalse( ExclusionMatcher::is_excluded_ip( '203.0.114.1' ), 'IP 203.0.114.1 should NOT be blocked by CIDR /24 range' );
	}

	/**
	 * @testdox Admin role excluded
	 */
	public function test_admin_role_excluded(): void {
		update_option( 'statnive_excluded_roles', [ 'administrator' ] );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$this->assertTrue( ExclusionMatcher::is_excluded_role(), 'Administrator role should be excluded when configured' );
	}

	/**
	 * @testdox Non-excluded role is not blocked
	 */
	public function test_non_excluded_role_not_blocked(): void {
		update_option( 'statnive_excluded_roles', [ 'administrator' ] );

		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( ExclusionMatcher::is_excluded_role(), 'Subscriber role should not be excluded when only administrator is configured' );
	}

	/**
	 * @testdox URL matching excluded pattern not recorded
	 */
	public function test_url_matching_excluded_pattern(): void {
		update_option( 'statnive_excluded_urls', "/wp-admin/*" );

		$this->assertTrue( ExclusionMatcher::is_excluded_url( '/wp-admin/edit.php' ), '/wp-admin/edit.php should match exclusion pattern' );
		$this->assertTrue( ExclusionMatcher::is_excluded_url( '/wp-admin/plugins.php' ), '/wp-admin/plugins.php should match exclusion pattern' );
		$this->assertFalse( ExclusionMatcher::is_excluded_url( '/blog/my-post' ), '/blog/my-post should NOT match /wp-admin/* exclusion pattern' );
	}

	/**
	 * @testdox Referrer spam blocked
	 */
	public function test_referrer_spam_blocked(): void {
		$domain = ReferrerService::extract_domain( 'https://best-seo-service.xyz/' );

		// The built-in blocklist does not include best-seo-service.xyz,
		// so we test with a known domain from the blocklist.
		$this->assertTrue( ReferrerService::is_spam( 'semalt.com' ), 'semalt.com should be detected as referrer spam' );
		$this->assertTrue( ReferrerService::is_spam( 'buttons-for-website.com' ), 'buttons-for-website.com should be detected as referrer spam' );
		$this->assertFalse( ReferrerService::is_spam( 'example.com' ), 'example.com should NOT be detected as referrer spam' );
	}

	/**
	 * @testdox Logged-out user is not excluded by role
	 */
	public function test_logged_out_user_not_excluded_by_role(): void {
		update_option( 'statnive_excluded_roles', [ 'administrator' ] );
		wp_set_current_user( 0 );

		$this->assertFalse( ExclusionMatcher::is_excluded_role(), 'Logged-out user should not be excluded by role-based exclusion' );
	}

	/**
	 * @testdox Empty excluded IPs does not block
	 */
	public function test_empty_excluded_ips_does_not_block(): void {
		update_option( 'statnive_excluded_ips', '' );

		$this->assertFalse( ExclusionMatcher::is_excluded_ip( '203.0.113.42' ), 'Empty excluded IPs config should not block any IP' );
	}
}
