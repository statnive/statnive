<?php

declare(strict_types=1);

namespace Statnive\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Consent API integration.
 *
 * Bridges Statnive with the WordPress Consent API plugin standard.
 * Gracefully degrades when the Consent API plugin is not installed.
 */
final class ConsentApiIntegration {

	/**
	 * Register with the WP Consent API if available.
	 */
	public static function register(): void {
		// Declare Statnive compatible with WP Consent API.
		add_filter( 'wp_consent_api_registered_statnive', '__return_true' );
	}

	/**
	 * Check if the user has granted statistics consent.
	 *
	 * Returns true if:
	 * - Consent mode is not 'disabled-until-consent' (no consent needed), OR
	 * - WP Consent API reports statistics consent granted, OR
	 * - A consent signal was included in the tracker payload.
	 *
	 * @param bool $payload_consent Whether consent was signaled in the request payload.
	 * @return bool True if statistics tracking is allowed.
	 */
	public static function has_consent( bool $payload_consent = false ): bool {
		$mode = PrivacyManager::get_consent_mode();

		// In cookieless or full mode, no consent signal is required.
		if ( ConsentMode::DISABLED_UNTIL_CONSENT !== $mode ) {
			return true;
		}

		// Check payload consent signal (from consent banner JS).
		if ( $payload_consent ) {
			return true;
		}

		// Check WP Consent API if available.
		if ( function_exists( 'wp_has_consent' ) ) {
			return wp_has_consent( 'statistics' );
		}

		// No consent signal available.
		return false;
	}
}
