<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GeoIP resolution service using MaxMind GeoLite2-City database.
 *
 * Resolves IP addresses to country, city, and region data.
 * Gracefully degrades when the database file is unavailable.
 *
 * Dependency: geoip2/geoip2 (Mozart-scoped to Statnive\Dependencies\GeoIp2).
 */
final class GeoIPService {

	/**
	 * In-memory cache of resolved IPs (per-request).
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $cache = [];

	/**
	 * Lazy-loaded ISO 3166-1 alpha-2 → English name map.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $iso_codes = null;

	/**
	 * The canonical empty result shape shared by every resolver path.
	 *
	 * @return array{country_code: string, country_name: string, city_name: string, region_code: string, continent_code: string, continent: string}
	 */
	private static function empty_result(): array {
		return [
			'country_code'   => '',
			'country_name'   => '',
			'city_name'      => '',
			'region_code'    => '',
			'continent_code' => '',
			'continent'      => '',
		];
	}

	/**
	 * Build a result row carrying only a country, with city/region/continent empty.
	 *
	 * Used by the CDN-header and timezone tiers — neither has city or region
	 * signal. Returns the canonical empty shape if the code is missing from
	 * the ISO map (defensive: callers should validate first).
	 *
	 * @param string                $code ISO 3166-1 alpha-2 code.
	 * @param array<string, string> $map  ISO code → English name lookup.
	 * @return array{country_code: string, country_name: string, city_name: string, region_code: string, continent_code: string, continent: string}
	 */
	private static function country_only_result( string $code, array $map ): array {
		if ( ! isset( $map[ $code ] ) ) {
			return self::empty_result();
		}
		return [
			'country_code'   => $code,
			'country_name'   => $map[ $code ],
			'city_name'      => '',
			'region_code'    => '',
			'continent_code' => '',
			'continent'      => '',
		];
	}

	/**
	 * Lazy-load and cache the ISO 3166-1 alpha-2 → name map.
	 *
	 * @return array<string, string>
	 */
	private static function iso_codes(): array {
		if ( null === self::$iso_codes ) {
			/**
			 * ISO 3166-1 alpha-2 → English name map returned by the static data file.
			 *
			 * @var array<string, string> $map
			 */
			$map             = require __DIR__ . '/../Data/countries.php';
			self::$iso_codes = $map;
		}
		return self::$iso_codes;
	}

	/**
	 * CDN country-header $_SERVER key → display label, in priority order.
	 */
	private const CDN_HEADERS = [
		'HTTP_CF_IPCOUNTRY'              => 'CF-IPCountry',
		'HTTP_CLOUDFRONT_VIEWER_COUNTRY' => 'CloudFront-Viewer-Country',
		'HTTP_X_VERCEL_IP_COUNTRY'       => 'X-Vercel-IP-Country',
		'HTTP_X_COUNTRY_CODE'            => 'X-Country-Code',
	];

	/**
	 * Sentinel codes returned by CDNs for unknown / anonymising traffic —
	 * these must never reach the ISO map lookup.
	 */
	private const REJECTED_CDN_CODES = [ 'XX', 'T1' ];

	/**
	 * Resolve an IP address to geographic data.
	 *
	 * @param string $ip Raw IP address.
	 * @return array{country_code: string, country_name: string, city_name: string, region_code: string, continent_code: string, continent: string}
	 */
	public static function resolve( string $ip ): array {
		if ( empty( $ip ) || IpExtractor::is_private_ip( $ip ) ) {
			return self::empty_result();
		}

		// Check per-request cache.
		if ( isset( self::$cache[ $ip ] ) ) {
			return self::$cache[ $ip ];
		}

		$db_path = self::get_database_path();
		if ( ! file_exists( $db_path ) ) {
			return self::empty_result();
		}

		try {
			// Use MaxMind GeoIP2 reader (Mozart-scoped).
			// Class will be at Statnive\Dependencies\GeoIp2\Database\Reader after Mozart.
			if ( ! class_exists( '\GeoIp2\Database\Reader' ) ) {
				return self::empty_result();
			}

			$reader = new \GeoIp2\Database\Reader( $db_path );
			$record = $reader->city( $ip );

			$result = [
				'country_code'   => $record->country->isoCode ?? '',
				'country_name'   => $record->country->name ?? '',
				'city_name'      => $record->city->name ?? '',
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'region_code'    => $record->mostSpecificSubdivision->isoCode ?? '',
				'continent_code' => $record->continent->code ?? '',
				'continent'      => $record->continent->name ?? '',
			];

			$reader->close();

			self::$cache[ $ip ] = $result;
			return $result;

		} catch ( \Exception $e ) {
			// Graceful degradation: log and return empty.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Statnive][GeoIP] Resolution failed for ' . $ip . ': ' . $e->getMessage() );
			}
			return self::empty_result();
		}
	}

	/**
	 * Whether inbound CDN country headers may be trusted.
	 *
	 * Sites with a publicly reachable origin (where visitors can bypass the
	 * CDN and hit the web server directly) should disable this via the
	 * `statnive_trust_cdn_country_headers` filter — otherwise an attacker
	 * could forge `CF-IPCountry` from a direct request and pollute the
	 * country column in analytics. No security impact beyond data quality.
	 *
	 * @return bool
	 */
	private static function cdn_headers_trusted(): bool {
		/**
		 * Filters whether inbound CDN country headers are trusted.
		 *
		 * @since 0.4.3
		 * @param bool $trusted Default true — preserves historical behaviour.
		 */
		return (bool) apply_filters( 'statnive_trust_cdn_country_headers', true );
	}

	/**
	 * Resolve an approximate country from inbound CDN country headers.
	 *
	 * Reads $_SERVER for the standard country-indicating headers emitted by
	 * Cloudflare, AWS CloudFront, Vercel, and generic reverse proxies. Each
	 * value passes through the canonical isset → wp_unslash → sanitize chain
	 * and is then validated against the ISO 3166-1 alpha-2 map before use.
	 *
	 * A light spoof guard rejects the result when REMOTE_ADDR is missing,
	 * private, or loopback — the request did not traverse a CDN in that case,
	 * so any country header is untrusted.
	 *
	 * @return array{country_code: string, country_name: string, city_name: string, region_code: string, continent_code: string, continent: string}
	 */
	public static function resolve_from_request_headers(): array {
		if ( ! self::cdn_headers_trusted() ) {
			return self::empty_result();
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';
		if ( ! IpExtractor::is_valid_ip( $remote ) || IpExtractor::is_private_ip( $remote ) ) {
			return self::empty_result();
		}

		$map = self::iso_codes();
		foreach ( array_keys( self::CDN_HEADERS ) as $header ) {
			if ( ! isset( $_SERVER[ $header ] ) ) {
				continue;
			}
			$raw  = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
			$code = strtoupper( substr( $raw, 0, 2 ) );

			if ( 2 !== strlen( $code )
				|| ! ctype_alpha( $code )
				|| in_array( $code, self::REJECTED_CDN_CODES, true )
				|| ! isset( $map[ $code ] ) ) {
				continue;
			}

			return self::country_only_result( $code, $map );
		}

		return self::empty_result();
	}

	/**
	 * Resolve an approximate country from the visitor's IANA timezone.
	 *
	 * Pure in-process lookup against a static table generated from public-domain
	 * IANA tzdb sources — no network call, no external service. Used as the
	 * final tier of the fallback chain when MaxMind and CDN headers both yield
	 * nothing, so a fresh install on a vanilla WP host (no CDN, no MaxMind)
	 * still attributes visitors to a country with no settings touched.
	 *
	 * @param string $tz IANA timezone identifier from the tracker payload.
	 * @return array{country_code: string, country_name: string, city_name: string, region_code: string, continent_code: string, continent: string}
	 */
	public static function resolve_from_timezone( string $tz ): array {
		$code = TimezoneGeoResolver::resolve( $tz );
		if ( '' === $code ) {
			return self::empty_result();
		}
		return self::country_only_result( $code, self::iso_codes() );
	}

	/**
	 * Return the display name of the first CDN country header present on
	 * the inbound request, or null if none is set.
	 *
	 * @return string|null
	 */
	public static function first_cdn_header_name(): ?string {
		if ( ! self::cdn_headers_trusted() ) {
			return null;
		}

		foreach ( self::CDN_HEADERS as $header => $label ) {
			if ( ! isset( $_SERVER[ $header ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
			if ( '' !== $value ) {
				return $label;
			}
		}
		return null;
	}

	/**
	 * Describe which country source is currently available for this request.
	 *
	 * The timezone tier is always reachable on requests that carry a tracker
	 * payload, so a fresh install on a vanilla host reports 'timezone' rather
	 * than 'none'. The 'none' return is kept in the type for symmetry with
	 * dashboard states that may surface it (e.g. when the tracker payload
	 * carried no timezone at all).
	 *
	 * MaxMind wins ties when both .mmdb files are on disk (paid > free tier).
	 *
	 * @return string 'maxmind' | 'dbip_city' | 'cdn_headers' | 'timezone' | 'none'
	 */
	public static function detect_source(): string {
		$base = self::get_database_dir();
		if ( file_exists( $base . GeoIPDownloader::MAXMIND_FILENAME ) ) {
			return 'maxmind';
		}
		if ( file_exists( $base . GeoIPDownloader::DBIP_FILENAME ) ) {
			return 'dbip_city';
		}
		if ( null !== self::first_cdn_header_name() ) {
			return 'cdn_headers';
		}
		return 'timezone';
	}

	/**
	 * Directory holding all Statnive GeoIP database files.
	 *
	 * @return string Absolute path with trailing slash.
	 */
	public static function get_database_dir(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/statnive/';
	}

	/**
	 * Get the path to the active GeoIP .mmdb database.
	 *
	 * MaxMind wins ties (paid > free). When neither file exists the legacy
	 * MaxMind path is returned so diagnostic callers still get a meaningful
	 * value — `is_available()` will then correctly return false.
	 *
	 * @return string Absolute path to .mmdb file.
	 */
	public static function get_database_path(): string {
		$base = self::get_database_dir();
		foreach ( [ GeoIPDownloader::MAXMIND_FILENAME, GeoIPDownloader::DBIP_FILENAME ] as $filename ) {
			if ( file_exists( $base . $filename ) ) {
				return $base . $filename;
			}
		}
		return $base . GeoIPDownloader::MAXMIND_FILENAME;
	}

	/**
	 * Check if any GeoIP database is available on disk.
	 *
	 * @return bool True if a .mmdb file exists for either provider.
	 */
	public static function is_available(): bool {
		return file_exists( self::get_database_path() );
	}

	/**
	 * Clear the per-request cache.
	 */
	public static function clear_cache(): void {
		self::$cache = [];
	}
}
