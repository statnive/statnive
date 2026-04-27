<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclusion rule matcher.
 *
 * Checks IP ranges (CIDR), user roles, and URL patterns against configured rules.
 */
final class ExclusionMatcher {

	/**
	 * Per-request cache of parsed IP ranges.
	 *
	 * Null means "not yet read". Empty array means "read, no exclusions".
	 *
	 * @var string[]|null
	 */
	private static ?array $cached_ip_ranges = null;

	/**
	 * Per-request cache of excluded role slugs.
	 *
	 * @var string[]|null
	 */
	private static ?array $cached_excluded_roles = null;

	/**
	 * Reset the per-request memoization.
	 *
	 * Call from tests and from code that updates the underlying options
	 * inside the same request.
	 */
	public static function reset_cache(): void {
		self::$cached_ip_ranges      = null;
		self::$cached_excluded_roles = null;
	}

	/**
	 * Check if an IP is in the excluded ranges.
	 *
	 * Supports CIDR notation (192.168.0.0/16) and single IPs.
	 *
	 * @param string $ip IP address to check.
	 * @return bool True if excluded.
	 */
	public static function is_excluded_ip( string $ip ): bool {
		if ( null === self::$cached_ip_ranges ) {
			$excluded               = (string) get_option( 'statnive_excluded_ips', '' );
			self::$cached_ip_ranges = '' === $excluded
				? []
				: array_values( array_filter( array_map( 'trim', explode( "\n", $excluded ) ) ) );
		}

		if ( empty( self::$cached_ip_ranges ) ) {
			return false;
		}

		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return false;
		}

		foreach ( self::$cached_ip_ranges as $range ) {
			if ( self::ip_in_cidr( $packed, $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the current user has an excluded role.
	 *
	 * Cheap path for the anonymous-tracker case: empty option → skip the
	 * session parsing `is_user_logged_in()` triggers.
	 *
	 * @return bool True if excluded.
	 */
	public static function is_excluded_role(): bool {
		if ( null === self::$cached_excluded_roles ) {
			$opt                         = get_option( 'statnive_excluded_roles', [] );
			self::$cached_excluded_roles = is_array( $opt ) ? array_values( $opt ) : [];
		}

		if ( empty( self::$cached_excluded_roles ) ) {
			return false;
		}

		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		return ! empty( array_intersect( $user->roles, self::$cached_excluded_roles ) );
	}

	/**
	 * Check if a URI matches an excluded URL pattern.
	 *
	 * Supports wildcard (*) patterns, one per line.
	 *
	 * @param string $uri Request URI.
	 * @return bool True if excluded.
	 */
	public static function is_excluded_url( string $uri ): bool {
		$patterns = get_option( 'statnive_excluded_urls', '' );
		if ( empty( $patterns ) ) {
			return false;
		}

		$lines = array_filter( array_map( 'trim', explode( "\n", $patterns ) ) );

		foreach ( $lines as $pattern ) {
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
			if ( preg_match( $regex, $uri ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a packed IP is within a CIDR range.
	 *
	 * @param string $packed_ip Packed IP (from inet_pton).
	 * @param string $cidr      CIDR notation or single IP.
	 * @return bool True if IP is in range.
	 */
	private static function ip_in_cidr( string $packed_ip, string $cidr ): bool {
		if ( str_contains( $cidr, '/' ) ) {
			list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		} else {
			$subnet = $cidr;
			$bits   = ( strlen( $packed_ip ) === 4 ) ? '32' : '128';
		}

		$packed_subnet = inet_pton( $subnet );
		if ( false === $packed_subnet || strlen( $packed_ip ) !== strlen( $packed_subnet ) ) {
			return false;
		}

		$bits  = (int) $bits;
		$bytes = (int) floor( $bits / 8 );
		$rem   = $bits % 8;

		// Compare full bytes.
		for ( $i = 0; $i < $bytes; $i++ ) {
			if ( $packed_ip[ $i ] !== $packed_subnet[ $i ] ) {
				return false;
			}
		}

		// Compare remaining bits.
		if ( $rem > 0 && $bytes < strlen( $packed_ip ) ) {
			$mask = 0xFF << ( 8 - $rem );
			if ( ( ord( $packed_ip[ $bytes ] ) & $mask ) !== ( ord( $packed_subnet[ $bytes ] ) & $mask ) ) {
				return false;
			}
		}

		return true;
	}
}
