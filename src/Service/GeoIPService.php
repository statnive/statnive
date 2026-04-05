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
	 * Resolve an IP address to geographic data.
	 *
	 * @param string $ip Raw IP address.
	 * @return array{country_code: string, country_name: string, city_name: string, region_code: string, continent_code: string, continent: string}
	 */
	public static function resolve( string $ip ): array {
		$empty = [
			'country_code'   => '',
			'country_name'   => '',
			'city_name'      => '',
			'region_code'    => '',
			'continent_code' => '',
			'continent'      => '',
		];

		if ( empty( $ip ) || IpExtractor::is_private_ip( $ip ) ) {
			return $empty;
		}

		// Check per-request cache.
		if ( isset( self::$cache[ $ip ] ) ) {
			return self::$cache[ $ip ];
		}

		$db_path = self::get_database_path();
		if ( ! file_exists( $db_path ) ) {
			return $empty;
		}

		try {
			// Use MaxMind GeoIP2 reader (Mozart-scoped).
			// Class will be at Statnive\Dependencies\GeoIp2\Database\Reader after Mozart.
			if ( ! class_exists( '\GeoIp2\Database\Reader' ) ) {
				return $empty;
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
			return $empty;
		}
	}

	/**
	 * Get the path to the MaxMind GeoLite2-City database.
	 *
	 * @return string Absolute path to .mmdb file.
	 */
	public static function get_database_path(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/statnive/GeoLite2-City.mmdb';
	}

	/**
	 * Check if the GeoIP database is available.
	 *
	 * @return bool True if database file exists.
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
