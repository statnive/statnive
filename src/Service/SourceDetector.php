<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Traffic source channel classification.
 *
 * Classifies referrer domains into human-readable channels:
 * Direct, Organic Search, Social Media, Email, Referral, Paid Search, Paid Social.
 */
final class SourceDetector {

	/**
	 * Search engine domain patterns.
	 *
	 * @var array<string, string>
	 */
	private const SEARCH_ENGINES = [
		'google'     => 'Google',
		'bing'       => 'Bing',
		'duckduckgo' => 'DuckDuckGo',
		'yahoo'      => 'Yahoo',
		'baidu'      => 'Baidu',
		'yandex'     => 'Yandex',
		'ecosia'     => 'Ecosia',
		'brave'      => 'Brave',
		'startpage'  => 'Startpage',
		'qwant'      => 'Qwant',
		'sogou'      => 'Sogou',
		'naver'      => 'Naver',
	];

	/**
	 * Social media domain patterns.
	 *
	 * @var array<string, string>
	 */
	private const SOCIAL_PLATFORMS = [
		'facebook'  => 'Facebook',
		'fb.com'    => 'Facebook',
		'twitter'   => 'Twitter/X',
		'x.com'     => 'Twitter/X',
		't.co'      => 'Twitter/X',
		'linkedin'  => 'LinkedIn',
		'reddit'    => 'Reddit',
		'pinterest' => 'Pinterest',
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
		'mastodon'  => 'Mastodon',
		'threads'   => 'Threads',
		'tumblr'    => 'Tumblr',
		'discord'   => 'Discord',
		'slack'     => 'Slack',
		'telegram'  => 'Telegram',
		'whatsapp'  => 'WhatsApp',
	];

	/**
	 * Known email service domains.
	 *
	 * @var string[]
	 */
	private const EMAIL_DOMAINS = [
		'mail.google.com',
		'outlook.live.com',
		'outlook.office.com',
		'mail.yahoo.com',
		'mail.aol.com',
	];

	/**
	 * Classify a referrer into a traffic channel.
	 *
	 * @param string $domain    Referrer domain (lowercase).
	 * @param string $url       Full referrer URL.
	 * @param string $utm_medium UTM medium parameter (if available).
	 * @return array{channel: string, name: string}
	 */
	public static function classify( string $domain, string $url = '', string $utm_medium = '' ): array {
		// UTM medium overrides domain-based detection.
		if ( ! empty( $utm_medium ) ) {
			$result = self::classify_by_utm( $utm_medium );
			if ( null !== $result ) {
				return $result;
			}
		}

		// Empty domain = Direct traffic.
		if ( empty( $domain ) ) {
			return [
				'channel' => 'Direct',
				'name'    => '',
			];
		}

		// Check search engines.
		foreach ( self::SEARCH_ENGINES as $pattern => $name ) {
			if ( str_contains( $domain, $pattern ) ) {
				return [
					'channel' => 'Organic Search',
					'name'    => $name,
				];
			}
		}

		// Check social platforms.
		foreach ( self::SOCIAL_PLATFORMS as $pattern => $name ) {
			if ( str_contains( $domain, $pattern ) ) {
				return [
					'channel' => 'Social Media',
					'name'    => $name,
				];
			}
		}

		// Check email services.
		foreach ( self::EMAIL_DOMAINS as $email_domain ) {
			if ( $domain === $email_domain || str_ends_with( $domain, '.' . $email_domain ) ) {
				return [
					'channel' => 'Email',
					'name'    => $domain,
				];
			}
		}

		// Default: Referral.
		return [
			'channel' => 'Referral',
			'name'    => $domain,
		];
	}

	/**
	 * Classify by UTM medium parameter.
	 *
	 * @param string $utm_medium UTM medium value.
	 * @return array{channel: string, name: string}|null Classification, or null if unrecognized.
	 */
	private static function classify_by_utm( string $utm_medium ): ?array {
		$medium = strtolower( trim( $utm_medium ) );

		$map = [
			'cpc'         => 'Paid Search',
			'ppc'         => 'Paid Search',
			'paidsearch'  => 'Paid Search',
			'paid_search' => 'Paid Search',
			'cpm'         => 'Paid Social',
			'paid_social' => 'Paid Social',
			'social'      => 'Social Media',
			'email'       => 'Email',
			'e-mail'      => 'Email',
			'newsletter'  => 'Email',
		];

		if ( isset( $map[ $medium ] ) ) {
			return [
				'channel' => $map[ $medium ],
				'name'    => '',
			];
		}

		return null;
	}
}
