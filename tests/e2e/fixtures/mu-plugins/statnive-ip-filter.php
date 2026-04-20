<?php
/**
 * E2E-only: spoof the client IP used by Statnive's tracker gate.
 *
 * Reads the IP from the `X-Test-Client-IP` header that Playwright sends
 * per-request, validates it, and feeds it into the `statnive_client_ip`
 * filter documented in `src/Service/IpExtractor.php:65`. Activated via
 * the file-sentinel `.statnive-e2e-on` (env vars don't propagate to
 * Local's PHP worker, so file presence is the only reliable signal).
 *
 * Safe-by-default: when the sentinel is absent, this file is a no-op.
 *
 * @package Statnive\Tests\E2E
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/.statnive-e2e-on' ) ) {
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
