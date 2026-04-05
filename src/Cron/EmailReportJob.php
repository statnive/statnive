<?php

declare(strict_types=1);

namespace Statnive\Cron;

use Statnive\Email\ReportBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-Cron job for sending scheduled email reports.
 *
 * Supports daily, weekly, and monthly frequencies.
 */
final class EmailReportJob {

	public const HOOK = 'statnive_email_report';

	/**
	 * Register the cron hook callback.
	 */
	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Send email reports to configured recipients.
	 */
	public static function run(): void {
		if ( ! (bool) get_option( 'statnive_email_reports', false ) ) {
			return;
		}

		$recipients = get_option( 'statnive_email_recipients', '' );
		if ( empty( $recipients ) ) {
			$admin_email = get_option( 'admin_email' );
			$recipients  = $admin_email ? $admin_email : '';
		}

		if ( empty( $recipients ) ) {
			return;
		}

		$frequency = get_option( 'statnive_email_frequency', 'weekly' );
		$period    = self::get_period( $frequency );

		$html = ReportBuilder::build( $period['from'], $period['to'], $frequency );

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: report frequency */
			__( '%1$s Analytics — %2$s Report', 'statnive' ),
			get_bloginfo( 'name' ),
			ucfirst( $frequency )
		);

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		$emails  = array_map( 'sanitize_email', explode( ',', $recipients ) );

		foreach ( $emails as $email ) {
			if ( is_email( $email ) ) {
				wp_mail( $email, $subject, $html, $headers );
			}
		}
	}

	/**
	 * Get the date range for the report period.
	 *
	 * @param string $frequency Report frequency.
	 * @return array{from: string, to: string}
	 */
	private static function get_period( string $frequency ): array {
		$to = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		switch ( $frequency ) {
			case 'daily':
				return [
					'from' => $to,
					'to'   => $to,
				];
			case 'monthly':
				return [
					'from' => gmdate( 'Y-m-01', strtotime( '-1 month' ) ),
					'to'   => gmdate( 'Y-m-t', strtotime( '-1 month' ) ),
				];
			default: // weekly.
				return [
					'from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
					'to'   => $to,
				];
		}
	}

	/**
	 * Schedule based on configured frequency.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$frequency = get_option( 'statnive_email_frequency', 'weekly' );
			wp_schedule_event( time(), $frequency, self::HOOK );
		}
	}

	/**
	 * Unschedule.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
