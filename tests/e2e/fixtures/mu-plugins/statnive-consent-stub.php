<?php
/**
 * E2E-only: stub the WordPress Consent API for tests.
 *
 * Activated only when the env var `STATNIVE_E2E_CONSENT_STUB=1` is set.
 * Defines `wp_has_consent()` if no consent-API plugin is installed and
 * reads the answer from a transient the tests flip via REST, so we can
 * drive Statnive's `ConsentApiIntegration::has_consent()` fallback path
 * deterministically.
 *
 * @package Statnive\Tests\E2E
 */

if ( '1' !== getenv( 'STATNIVE_E2E_CONSENT_STUB' ) ) {
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
