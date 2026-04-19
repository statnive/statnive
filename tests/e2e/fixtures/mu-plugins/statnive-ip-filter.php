<?php
/**
 * E2E-only: spoof the client IP used by Statnive's tracker gate.
 *
 * Activated only when the env var `STATNIVE_E2E_IP_FILTER=1` is set on the
 * PHP process. Reads the IP from the `X-Test-Client-IP` header that
 * Playwright sends per-request, validates it, and feeds it into the
 * `statnive_client_ip` filter documented in
 * `src/Service/IpExtractor.php:65`.
 *
 * Safe-by-default: if the env var is absent (normal browsing, CI without
 * the flag), this file is a no-op — the filter never registers.
 *
 * @package Statnive\Tests\E2E
 */

if ( '1' !== getenv( 'STATNIVE_E2E_IP_FILTER' ) ) {
	return;
}

add_filter(
	'statnive_client_ip',
	static function ( string $ip ): string {
		if ( empty( $_SERVER['HTTP_X_TEST_CLIENT_IP'] ) ) {
			return $ip;
		}

		$candidate = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_TEST_CLIENT_IP'] ) );
		if ( false === filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return $candidate;
	},
	10,
	1
);
