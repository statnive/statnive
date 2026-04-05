<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IP address anonymizer for privacy-safe hashing.
 *
 * Zeros the last octet of IPv4 addresses and the last 80 bits of IPv6 addresses,
 * reducing precision while preserving enough information for meaningful hashing.
 */
final class IpAnonymizer {

	/**
	 * Anonymize an IP address by zeroing the least significant bits.
	 *
	 * IPv4: 192.168.1.42 → 192.168.1.0
	 * IPv6: 2001:db8::1 → 2001:db8::0:0:0:0:0
	 *
	 * @param string $ip Raw IP address.
	 * @return string Anonymized IP address.
	 */
	public static function anonymize( string $ip ): string {
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return self::anonymize_ipv4( $ip );
		}

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return self::anonymize_ipv6( $ip );
		}

		// Invalid IP — return zeroed placeholder.
		return '0.0.0.0';
	}

	/**
	 * Anonymize IPv4: zero the last octet.
	 *
	 * @param string $ip IPv4 address.
	 * @return string Anonymized IPv4 (e.g., '192.168.1.0').
	 */
	private static function anonymize_ipv4( string $ip ): string {
		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			return '0.0.0.0';
		}

		// Zero the last byte.
		$packed[3] = "\0";

		$result = inet_ntop( $packed );
		return false !== $result ? $result : '0.0.0.0';
	}

	/**
	 * Anonymize IPv6: zero the last 80 bits (10 bytes).
	 *
	 * Preserves the /48 network prefix (first 6 bytes).
	 *
	 * @param string $ip IPv6 address.
	 * @return string Anonymized IPv6.
	 */
	private static function anonymize_ipv6( string $ip ): string {
		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			return '::';
		}

		// Zero last 10 bytes (bits 49-128).
		for ( $i = 6; $i < 16; $i++ ) {
			$packed[ $i ] = "\0";
		}

		$result = inet_ntop( $packed );
		return false !== $result ? $result : '::';
	}
}
