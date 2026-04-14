<?php

declare(strict_types=1);

namespace Statnive\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic privacy policy content generator.
 *
 * Generates privacy policy text based on current plugin settings,
 * registered via wp_add_privacy_policy_content().
 */
final class PrivacyPolicyGenerator {

	/**
	 * Register the privacy policy content hook.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'add_policy_content' ] );
	}

	/**
	 * Add privacy policy content to WordPress.
	 */
	public static function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			'Statnive',
			wp_kses_post( self::generate_content() )
		);
	}

	/**
	 * Generate privacy policy content based on current settings.
	 *
	 * @return string HTML content.
	 */
	public static function generate_content(): string {
		$mode           = PrivacyManager::get_consent_mode();
		$respect_dnt    = PrivacyManager::should_respect_dnt();
		$respect_gpc    = PrivacyManager::should_respect_gpc();
		$retention      = PrivacyManager::get_retention_config();
		$retention_days = $retention['days'];

		$sections = [];

		// Introduction.
		$sections[] = '<h2>' . esc_html__( 'Analytics (Statnive)', 'statnive' ) . '</h2>';
		$sections[] = '<p>' . esc_html__( 'This website uses Statnive, a privacy-first analytics plugin that runs entirely on our server. No data is shared with third-party services.', 'statnive' ) . '</p>';

		// Cookie statement.
		$sections[] = '<h3>' . esc_html__( 'Cookies & Tracking', 'statnive' ) . '</h3>';
		$sections[] = '<p>' . esc_html__( 'Statnive does not use cookies, localStorage, sessionStorage, or any form of browser fingerprinting. Visitor identification uses a daily-rotating cryptographic hash that cannot be reversed or used to track individuals across days.', 'statnive' ) . '</p>';

		// Data collected.
		$sections[] = '<h3>' . esc_html__( 'Data Collected', 'statnive' ) . '</h3>';
		$sections[] = '<p>' . esc_html__( 'We collect the following anonymized data for each page visit:', 'statnive' ) . '</p>';
		$sections[] = '<ul>';
		$sections[] = '<li>' . esc_html__( 'Page URL visited (without query parameters)', 'statnive' ) . '</li>';
		$sections[] = '<li>' . esc_html__( 'Referrer URL (domain only, query parameters stripped)', 'statnive' ) . '</li>';
		$sections[] = '<li>' . esc_html__( 'Country and city (derived from IP, which is immediately discarded)', 'statnive' ) . '</li>';
		$sections[] = '<li>' . esc_html__( 'Device type, browser, and operating system', 'statnive' ) . '</li>';
		$sections[] = '<li>' . esc_html__( 'Screen resolution, language, and timezone', 'statnive' ) . '</li>';
		$sections[] = '</ul>';

		// IP handling.
		$sections[] = '<h3>' . esc_html__( 'IP Address Handling', 'statnive' ) . '</h3>';
		$sections[] = '<p>' . esc_html__( 'Your IP address is never stored. It is used only momentarily to determine your approximate geographic location and to generate a one-way cryptographic hash for visit counting. The IP is discarded immediately after processing and cannot be recovered from the stored hash.', 'statnive' ) . '</p>';

		// Consent mode.
		$sections[] = '<h3>' . esc_html__( 'Consent', 'statnive' ) . '</h3>';
		if ( ConsentMode::COOKIELESS === $mode ) {
			$sections[] = '<p>' . esc_html__( 'Because Statnive uses no cookies and stores no personal data, analytics run in cookieless mode without requiring consent under most privacy regulations.', 'statnive' ) . '</p>';
		} elseif ( ConsentMode::DISABLED_UNTIL_CONSENT === $mode ) {
			$sections[] = '<p>' . esc_html__( 'Analytics are disabled until you provide explicit consent through our cookie/consent banner. No data is collected before consent is granted.', 'statnive' ) . '</p>';
		} else {
			$sections[] = '<p>' . esc_html__( 'Analytics are active for all visitors. You may opt out using your browser\'s Do Not Track or Global Privacy Control settings.', 'statnive' ) . '</p>';
		}

		// DNT/GPC.
		if ( $respect_dnt || $respect_gpc ) {
			$sections[] = '<h3>' . esc_html__( 'Do Not Track & Global Privacy Control', 'statnive' ) . '</h3>';
			$signals    = [];
			if ( $respect_dnt ) {
				$signals[] = esc_html__( 'Do Not Track (DNT)', 'statnive' );
			}
			if ( $respect_gpc ) {
				$signals[] = esc_html__( 'Global Privacy Control (GPC)', 'statnive' );
			}
			$sections[] = '<p>' . sprintf(
				/* translators: %s: comma-separated list of privacy signals */
				esc_html__( 'We honor the following browser privacy signals: %s. When enabled, no analytics data is collected.', 'statnive' ),
				implode( ', ', $signals )
			) . '</p>';
		}

		// Retention.
		$sections[] = '<h3>' . esc_html__( 'Data Retention', 'statnive' ) . '</h3>';
		if ( 'forever' === $retention['mode'] ) {
			$sections[] = '<p>' . esc_html__( 'Aggregated analytics data is retained indefinitely. No personal data is stored.', 'statnive' ) . '</p>';
		} else {
			$sections[] = '<p>' . sprintf(
				/* translators: %d: number of days */
				esc_html__( 'Raw analytics data is automatically deleted after %d days. Only aggregated statistics are retained for historical reporting.', 'statnive' ),
				$retention_days
			) . '</p>';
		}

		// Rights.
		$sections[] = '<h3>' . esc_html__( 'Your Rights', 'statnive' ) . '</h3>';
		$sections[] = '<p>' . esc_html__( 'You may request export or deletion of any analytics data associated with your user account through the WordPress personal data tools. Anonymous visitor data cannot be attributed to individuals due to the cookieless, hash-based architecture.', 'statnive' ) . '</p>';

		return implode( "\n", $sections );
	}
}
