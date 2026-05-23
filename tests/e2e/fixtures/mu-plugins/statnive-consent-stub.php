<?php
/**
 * E2E-only: stub the WordPress Consent API for tests.
 *
 * Activated by the `.statnive-e2e-on` sentinel file. Defines
 * `wp_has_consent()` if no consent-API plugin is installed and reads the
 * answer from a transient the tests flip via REST, so we can drive
 * Statnive's `ConsentApiIntegration::has_consent()` fallback path
 * deterministically.
 *
 * @package Statnive\Tests\E2E
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// File-sentinel gate — see statnive-ip-filter.php for rationale.
if ( ! file_exists( __DIR__ . '/.statnive-e2e-on' ) ) {
	return;
}

// The real `wp-consent-api` plugin ships its own `wp_has_consent()`. Stubbing
// here would collide (mu-plugins load BEFORE regular plugins), causing a
// "Cannot redeclare" fatal. Bail when the plugin file is present and rely on
// its own API instead — tests that need this path can drive it via cookies.
if ( file_exists( WP_PLUGIN_DIR . '/wp-consent-api/wp-consent-api.php' ) ) {
	return;
}

if ( ! function_exists( 'wp_has_consent' ) ) {
	/**
	 * Stub `wp_has_consent()` for E2E tests.
	 *
	 * @param string $category Consent category (e.g., 'statistics').
	 * @return bool
	 */
	function wp_has_consent( string $category ): bool {
		$transient = get_transient( '_statnive_e2e_consent_' . $category );
		return '1' === (string) $transient;
	}
}
