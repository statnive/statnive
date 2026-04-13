<?php
/**
 * Generated from BDD scenarios (07-privacy-compliance.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Privacy;

use Statnive\Database\DatabaseFactory;
use Statnive\Privacy\ComplianceAuditor;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the privacy compliance audit engine.
 *
 * @covers \Statnive\Privacy\ComplianceAuditor
 */
final class ComplianceAuditTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
	}

	/**
	 * @testdox Compliant score when all checks pass
	 */
	public function test_compliant_score_when_all_checks_pass(): void {
		// Configure all privacy settings to pass.
		update_option( 'statnive_consent_mode', 'cookieless' );
		update_option( 'statnive_respect_dnt', true );
		update_option( 'statnive_respect_gpc', true );
		update_option( 'statnive_retention_mode', 'delete' );
		update_option( 'statnive_retention_days', 90 );
		update_option( 'statnive_salt_rotated_at', gmdate( 'Y-m-d H:i:s', time() - 3600 ) );

		// Create a published privacy policy page.
		$page_id = self::factory()->post->create( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Privacy Policy',
		] );
		update_option( 'wp_page_for_privacy_policy', $page_id );

		// Schedule the purge cron.
		wp_schedule_event( time(), 'daily', 'statnive_daily_data_purge' );

		// Simulate HTTPS.
		$_SERVER['HTTPS'] = 'on';

		$score = ComplianceAuditor::score();

		$this->assertSame( 100, $score, 'Compliance score should be 100 when all privacy checks pass' );

		// Clean up.
		wp_clear_scheduled_hook( 'statnive_daily_data_purge' );
		unset( $_SERVER['HTTPS'] );
	}

	/**
	 * @testdox Audit returns all 10 check results
	 */
	public function test_audit_returns_all_checks(): void {
		$checks = ComplianceAuditor::audit();

		$this->assertCount( 10, $checks, 'Audit should return exactly 10 check results' );

		foreach ( $checks as $check ) {
			$this->assertArrayHasKey( 'id', $check, 'Each check should have an "id" key' );
			$this->assertArrayHasKey( 'label', $check, 'Each check should have a "label" key' );
			$this->assertArrayHasKey( 'status', $check, 'Each check should have a "status" key' );
			$this->assertArrayHasKey( 'detail', $check, 'Each check should have a "detail" key' );
			$this->assertContains( $check['status'], [ 'pass', 'warning', 'fail' ], 'Check status must be pass, warning, or fail' );
		}
	}

	/**
	 * @testdox Missing consent mode reduces score
	 */
	public function test_missing_consent_mode_reduces_score(): void {
		delete_option( 'statnive_consent_mode' );

		$score = ComplianceAuditor::score();

		$this->assertLessThan( 100, $score, 'Missing consent mode should reduce the compliance score below 100' );
	}
}
