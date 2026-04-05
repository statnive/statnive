<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IP address extraction from HTTP request headers.
 *
 * Checks proxy headers in priority order to determine the real client IP.
 * Supports both IPv4 and IPv6 addresses.
 */
final class IpExtractor {

	/**
	 * Header priority chain for IP extraction.
	 *
	 * @var string[]
	 */
	private const HEADERS = [
		'HTTP_CF_CONNECTING_IP',   // Cloudflare.
		'HTTP_X_FORWARDED_FOR',    // Generic proxy (may contain chain).
		'HTTP_X_REAL_IP',          // Nginx reverse proxy.
		'REMOTE_ADDR',             // Direct connection.
	];

	/**
	 * Extract the client IP address from request headers.
	 *
	 * @return string Valid IP address, or '127.0.0.1' as fallback.
	 */
	public static function extract(): string {
		$ip = '127.0.0.1';

		foreach ( self::HEADERS as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

			// X-Forwarded-For may contain a chain of IPs — take the first (client).
			if ( str_contains( $raw, ',' ) ) {
				$raw = trim( explode( ',', $raw )[0] );
			}

			if ( self::is_valid_ip( $raw ) ) {
				$ip = $raw;
				break;
			}
		}

		/**
		 * Filter the extracted client IP address.
		 *
		 * Useful for local development/testing where REMOTE_ADDR is always 127.0.0.1.
		 * Example: add_filter( 'statnive_client_ip', fn() => '8.8.8.8' );
		 *
		 * @param string $ip Extracted client IP.
		 */
		return (string) apply_filters( 'statnive_client_ip', $ip );
	}

	/**
	 * Validate an IP address (IPv4 or IPv6).
	 *
	 * @param string $ip IP address to validate.
	 * @return bool True if valid.
	 */
	public static function is_valid_ip( string $ip ): bool {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Check if an IP is a private/reserved address.
	 *
	 * @param string $ip IP address.
	 * @return bool True if private or reserved.
	 */
	public static function is_private_ip( string $ip ): bool {
		return false === filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
