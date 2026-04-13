<?php
/**
 * Generated from BDD scenarios (11-realtime-email-reports.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Cron;

use Statnive\Cron\EmailReportJob;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the email report cron job.
 *
 * @covers \Statnive\Cron\EmailReportJob
 * @covers \Statnive\Email\ReportBuilder
 */
final class EmailReportJobTest extends WP_UnitTestCase {

	/**
	 * Captured emails via wp_mail filter.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $sent_emails = [];

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		$this->sent_emails = [];

		// Capture all wp_mail calls.
		add_filter( 'wp_mail', function ( array $args ) {
			$this->sent_emails[] = $args;
			return $args;
		} );

		// Prevent actual email sending.
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_mail' );
		remove_all_filters( 'pre_wp_mail' );

		// Clean up options created during test.
		delete_option( 'statnive_email_reports' );
		delete_option( 'statnive_email_frequency' );
		delete_option( 'statnive_email_recipients' );

		parent::tear_down();
	}

	/**
	 * Insert summary totals data for the past week.
	 *
	 * @param int $visitors Total visitors.
	 * @param int $views    Total pageviews.
	 */
	private function insert_weekly_data( int $visitors, int $views ): void {
		global $wpdb;
		$summary_totals = TableRegistry::get( 'summary_totals' );

		for ( $i = 1; $i <= 7; $i++ ) {
			$date = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$day_visitors = (int) ceil( $visitors / 7 );
			$day_views    = (int) ceil( $views / 7 );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO `{$summary_totals}` (date, visitors, sessions, views) VALUES (%s, %d, %d, %d)
					ON DUPLICATE KEY UPDATE visitors = VALUES(visitors), views = VALUES(views)",
					$date,
					$day_visitors,
					$day_visitors,
					$day_views
				)
			);
		}
	}

	/**
	 * @testdox Weekly email sends HTML summary
	 */
	public function test_weekly_email_sends_html_summary(): void {
		update_option( 'statnive_email_reports', true );
		update_option( 'statnive_email_frequency', 'weekly' );
		update_option( 'statnive_email_recipients', 'owner@example.com' );

		$this->insert_weekly_data( 1250, 3400 );

		EmailReportJob::run();

		$this->assertNotEmpty( $this->sent_emails, 'At least one email should be sent for weekly report' );

		$email = $this->sent_emails[0];
		$this->assertSame( 'owner@example.com', $email['to'], 'Email recipient should be owner@example.com' );
		$this->assertStringContainsString( 'Weekly', $email['subject'], 'Subject should contain "Weekly"' );
		$this->assertStringContainsString( 'Report', $email['subject'], 'Subject should contain "Report"' );
	}

	/**
	 * @testdox Configurable recipient and frequency
	 */
	public function test_configurable_recipient_and_frequency(): void {
		update_option( 'statnive_email_reports', true );
		update_option( 'statnive_email_frequency', 'monthly' );
		update_option( 'statnive_email_recipients', 'team@example.com,manager@example.com' );

		$this->insert_weekly_data( 500, 1000 );

		EmailReportJob::run();

		$this->assertCount( 2, $this->sent_emails, 'Two emails should be sent for two comma-separated recipients' );

		$recipients = array_column( $this->sent_emails, 'to' );
		$this->assertContains( 'team@example.com', $recipients, 'team@example.com should receive the report' );
		$this->assertContains( 'manager@example.com', $recipients, 'manager@example.com should receive the report' );
	}

	/**
	 * @testdox Zero-traffic week sends empty summary
	 */
	public function test_zero_traffic_week_sends_empty_summary(): void {
		update_option( 'statnive_email_reports', true );
		update_option( 'statnive_email_frequency', 'weekly' );
		update_option( 'statnive_email_recipients', 'admin@example.com' );

		// No data inserted — zero traffic.

		EmailReportJob::run();

		$this->assertNotEmpty( $this->sent_emails, 'Email should still be sent even with zero traffic' );

		$email = $this->sent_emails[0];
		$this->assertSame( 'admin@example.com', $email['to'], 'Recipient should be admin@example.com' );
		// The report should contain "0" for metrics.
		$this->assertStringContainsString( '0', $email['message'], 'Zero-traffic report should contain "0" metrics' );
	}

	/**
	 * @testdox Disabled reports do not send emails
	 */
	public function test_disabled_reports_do_not_send(): void {
		update_option( 'statnive_email_reports', false );

		EmailReportJob::run();

		$this->assertEmpty( $this->sent_emails, 'No emails should be sent when reports are disabled' );
	}
}
