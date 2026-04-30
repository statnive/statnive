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
	 * Search engine hosts. Match is `domain === host` OR `domain` ends with `.host`,
	 * so subdomains (`www.google.com`, `mail.google.com`) classify correctly while
	 * `googleads-merchant.example.com` does not.
	 *
	 * @var array<string, string>
	 */
	private const SEARCH_ENGINES = [
		'google.com'       => 'Google',
		'google.co.uk'     => 'Google',
		'google.de'        => 'Google',
		'google.fr'        => 'Google',
		'google.es'        => 'Google',
		'google.it'        => 'Google',
		'google.nl'        => 'Google',
		'google.pl'        => 'Google',
		'google.se'        => 'Google',
		'google.ch'        => 'Google',
		'google.be'        => 'Google',
		'google.at'        => 'Google',
		'google.ru'        => 'Google',
		'google.ca'        => 'Google',
		'google.com.au'    => 'Google',
		'google.com.br'    => 'Google',
		'google.com.mx'    => 'Google',
		'google.com.tr'    => 'Google',
		'google.co.jp'     => 'Google',
		'google.co.in'     => 'Google',
		'bing.com'         => 'Bing',
		'duckduckgo.com'   => 'DuckDuckGo',
		'yahoo.com'        => 'Yahoo',
		'search.yahoo.com' => 'Yahoo',
		'baidu.com'        => 'Baidu',
		'yandex.com'       => 'Yandex',
		'yandex.ru'        => 'Yandex',
		'ecosia.org'       => 'Ecosia',
		'search.brave.com' => 'Brave',
		'startpage.com'    => 'Startpage',
		'qwant.com'        => 'Qwant',
		'sogou.com'        => 'Sogou',
		'naver.com'        => 'Naver',
	];

	/**
	 * Social media hosts (full registrable hosts and known shorteners).
	 *
	 * Same suffix-match semantics as SEARCH_ENGINES — entries must be
	 * complete hosts. Never include short fragments like `'twitter'` or
	 * `'.com'`: those would match arbitrary domains.
	 *
	 * @var array<string, string>
	 */
	private const SOCIAL_PLATFORMS = [
		'facebook.com'     => 'Facebook',
		'm.facebook.com'   => 'Facebook',
		'l.facebook.com'   => 'Facebook',
		'lm.facebook.com'  => 'Facebook',
		'fb.com'           => 'Facebook',
		'fb.me'            => 'Facebook',
		'twitter.com'      => 'Twitter/X',
		'x.com'            => 'Twitter/X',
		't.co'             => 'Twitter/X',
		'linkedin.com'     => 'LinkedIn',
		'lnkd.in'          => 'LinkedIn',
		'reddit.com'       => 'Reddit',
		'old.reddit.com'   => 'Reddit',
		'out.reddit.com'   => 'Reddit',
		'redd.it'          => 'Reddit',
		'pinterest.com'    => 'Pinterest',
		'pinterest.co.uk'  => 'Pinterest',
		'pin.it'           => 'Pinterest',
		'instagram.com'    => 'Instagram',
		'l.instagram.com'  => 'Instagram',
		'youtube.com'      => 'YouTube',
		'youtu.be'         => 'YouTube',
		'tiktok.com'       => 'TikTok',
		'vm.tiktok.com'    => 'TikTok',
		'mastodon.social'  => 'Mastodon',
		'threads.net'      => 'Threads',
		'tumblr.com'       => 'Tumblr',
		'discord.com'      => 'Discord',
		'discord.gg'       => 'Discord',
		'slack.com'        => 'Slack',
		't.me'             => 'Telegram',
		'telegram.org'     => 'Telegram',
		'wa.me'            => 'WhatsApp',
		'web.whatsapp.com' => 'WhatsApp',
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

		foreach ( self::SEARCH_ENGINES as $host => $name ) {
			if ( self::host_matches( $domain, $host ) ) {
				return [
					'channel' => 'Organic Search',
					'name'    => $name,
				];
			}
		}

		foreach ( self::SOCIAL_PLATFORMS as $host => $name ) {
			if ( self::host_matches( $domain, $host ) ) {
				return [
					'channel' => 'Social Media',
					'name'    => $name,
				];
			}
		}

		foreach ( self::EMAIL_DOMAINS as $email_domain ) {
			if ( self::host_matches( $domain, $email_domain ) ) {
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
	 * True when `$domain` is exactly `$host` or a proper subdomain of it.
	 * Anchored at the domain boundary, so `tantei-mt.com` does not match `t.co`.
	 *
	 * @param string $domain Lowercased referrer host to test.
	 * @param string $host   Full registrable host to compare against.
	 */
	private static function host_matches( string $domain, string $host ): bool {
		return $domain === $host || str_ends_with( $domain, '.' . $host );
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
