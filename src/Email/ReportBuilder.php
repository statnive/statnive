<?php

declare(strict_types=1);

namespace Statnive\Email;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email report HTML builder.
 *
 * Queries summary data and builds an inline-CSS HTML email.
 */
final class ReportBuilder {

	/**
	 * Build a report for the given date range.
	 *
	 * @param string $from      Start date (Y-m-d).
	 * @param string $to        End date (Y-m-d).
	 * @param string $frequency Report frequency label.
	 * @return string HTML email content.
	 */
	public static function build( string $from, string $to, string $frequency ): string {
		global $wpdb;

		$summary_table = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(visitors) AS visitors, SUM(sessions) AS sessions,
				SUM(views) AS views, SUM(bounces) AS bounces
				FROM `{$summary_table}` WHERE date BETWEEN %s AND %s",
				$from,
				$to
			)
		);

		$pages_table = TableRegistry::get( 'summary' );
		$uris_table  = TableRegistry::get( 'resource_uris' );

		$top_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ru.uri, SUM(s.visitors) AS visitors, SUM(s.views) AS views
				FROM `{$pages_table}` s
				JOIN `{$uris_table}` ru ON s.resource_uri_id = ru.ID
				WHERE s.date BETWEEN %s AND %s
				GROUP BY ru.uri ORDER BY visitors DESC LIMIT 10",
				$from,
				$to
			)
		);
		// phpcs:enable

		$site_name = get_bloginfo( 'name' );
		$dash_url  = admin_url( 'admin.php?page=statnive' );

		$visitors = $totals->visitors ?? 0;
		$sessions = $totals->sessions ?? 0;
		$views    = $totals->views ?? 0;

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head><meta charset="UTF-8"></head>
		<body style="margin:0;padding:0;background:#f8fafc;font-family:system-ui,-apple-system,sans-serif;">
		<div style="max-width:600px;margin:24px auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
			<div style="background:#2271b1;color:#fff;padding:24px 32px;">
				<h1 style="margin:0;font-size:20px;"><?php echo esc_html( $site_name ); ?> — <?php echo esc_html( ucfirst( $frequency ) ); ?> Report</h1>
				<p style="margin:8px 0 0;opacity:0.85;font-size:14px;"><?php echo esc_html( $from ); ?> to <?php echo esc_html( $to ); ?></p>
			</div>
			<div style="padding:24px 32px;">
				<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
					<tr>
						<td style="text-align:center;padding:16px;border:1px solid #e5e7eb;">
							<div style="font-size:28px;font-weight:700;color:#111827;"><?php echo esc_html( number_format( (float) $visitors ) ); ?></div>
							<div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Visitors</div>
						</td>
						<td style="text-align:center;padding:16px;border:1px solid #e5e7eb;">
							<div style="font-size:28px;font-weight:700;color:#111827;"><?php echo esc_html( number_format( (float) $sessions ) ); ?></div>
							<div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Sessions</div>
						</td>
						<td style="text-align:center;padding:16px;border:1px solid #e5e7eb;">
							<div style="font-size:28px;font-weight:700;color:#111827;"><?php echo esc_html( number_format( (float) $views ) ); ?></div>
							<div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Pageviews</div>
						</td>
					</tr>
				</table>

				<?php if ( ! empty( $top_pages ) ) : ?>
				<h2 style="font-size:16px;color:#374151;margin:0 0 12px;">Top Pages</h2>
				<table style="width:100%;border-collapse:collapse;font-size:14px;">
					<tr style="background:#f9fafb;">
						<th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e5e7eb;">Page</th>
						<th style="text-align:right;padding:8px 12px;border-bottom:1px solid #e5e7eb;">Visitors</th>
						<th style="text-align:right;padding:8px 12px;border-bottom:1px solid #e5e7eb;">Views</th>
					</tr>
					<?php foreach ( $top_pages as $page ) : ?>
					<tr>
						<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;color:#374151;"><?php echo esc_html( $page->uri ); ?></td>
						<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;text-align:right;color:#374151;"><?php echo esc_html( number_format( (float) $page->visitors ) ); ?></td>
						<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;text-align:right;color:#6b7280;"><?php echo esc_html( number_format( (float) $page->views ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>

				<div style="margin-top:24px;text-align:center;">
					<a href="<?php echo esc_url( $dash_url ); ?>" style="display:inline-block;background:#2271b1;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">View Full Dashboard</a>
				</div>
			</div>
			<div style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">
				Sent by Statnive — Privacy-first analytics for WordPress
			</div>
		</div>
		</body>
		</html>
		<?php
		$output = ob_get_clean();
		return ( false !== $output ) ? $output : '';
	}
}
