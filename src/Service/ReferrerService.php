<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Referrer sanitization and spam filtering service.
 *
 * Strips query parameters from referrer URLs to prevent PII leakage (P-08),
 * detects self-referrals, and checks against a spam domain blocklist.
 */
final class ReferrerService {

	/**
	 * Sanitize a referrer URL by stripping query params and fragments.
	 *
	 * @param string $url Raw referrer URL.
	 * @return string Sanitized URL (scheme + host + path only).
	 */
	public static function sanitize( string $url ): string {
		if ( empty( $url ) ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( false === $parsed || empty( $parsed['host'] ) ) {
			return '';
		}

		$scheme = ! empty( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : 'https://';
		$host   = strtolower( $parsed['host'] );
		$path   = $parsed['path'] ?? '/';

		return $scheme . $host . $path;
	}

	/**
	 * Extract the domain from a referrer URL.
	 *
	 * @param string $url Referrer URL.
	 * @return string Domain (lowercase), or empty string.
	 */
	public static function extract_domain( string $url ): string {
		if ( empty( $url ) ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( false === $parsed || empty( $parsed['host'] ) ) {
			return '';
		}

		return strtolower( $parsed['host'] );
	}

	/**
	 * Check if a referrer is a self-referral (same site).
	 *
	 * @param string $referrer_url Referrer URL.
	 * @return bool True if referrer is from the same site.
	 */
	public static function is_self_referral( string $referrer_url ): bool {
		$referrer_domain = self::extract_domain( $referrer_url );
		$site_domain     = self::extract_domain( home_url() );

		return ! empty( $referrer_domain ) && $referrer_domain === $site_domain;
	}

	/**
	 * Check if a domain is known referrer spam.
	 *
	 * @param string $domain Domain to check.
	 * @return bool True if spam.
	 */
	public static function is_spam( string $domain ): bool {
		$domain = strtolower( $domain );

		$spam_list = self::get_spam_domains();

		/**
		 * Filter the referrer spam domain list.
		 *
		 * @param string[] $spam_list Array of spam domain names.
		 */
		$spam_list = apply_filters( 'statnive_referrer_spam_domains', $spam_list );

		foreach ( $spam_list as $spam_domain ) {
			if ( $domain === $spam_domain || str_ends_with( $domain, '.' . $spam_domain ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the built-in spam domain blocklist.
	 *
	 * @return string[]
	 */
	private static function get_spam_domains(): array {
		return [
			'semalt.com',
			'buttons-for-website.com',
			'darodar.com',
			'econom.co',
			'ilovevitaly.com',
			'priceg.com',
			'savetubevideo.com',
			'kambasoft.com',
			'lomb.co',
			'cop.su',
			'hulfingtonpost.com',
			'best-seo-offer.com',
			'best-seo-solution.com',
			'buy-cheap-online.info',
			'7makemoneyonline.com',
			'o-o-6-o-o.com',
			'cenoval.ru',
			'seoanalyses.com',
			'ranksonic.info',
			'cyber-monday.ga',
			'simple-share-buttons.com',
			'social-buttons.com',
			'free-social-buttons.com',
			'event-tracking.com',
			'get-free-traffic-now.com',
			'traffic2money.com',
			'makemoneyonline.com',
			'trafficmonetizer.org',
			'web-revenue.xyz',
			'claim-your-bonus.com',
		];
	}
}
