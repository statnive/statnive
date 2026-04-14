<?php

declare(strict_types=1);

namespace Statnive\Service;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dimension table resolver.
 *
 * Central service for looking up or creating dimension records in all 10 dimension tables.
 * Uses in-memory caching and INSERT IGNORE for concurrent-safe upserts.
 */
final class DimensionService {

	/**
	 * In-memory cache: table_name -> lookup_key -> ID.
	 *
	 * @var array<string, array<string, int>>
	 */
	private static array $cache = [];

	/**
	 * Resolve a country dimension record.
	 *
	 * @param string $code           ISO 3166-1 alpha-2 country code.
	 * @param string $name           Country name.
	 * @param string $continent_code Continent code.
	 * @param string $continent      Continent name.
	 * @return int Country ID.
	 */
	public static function resolve_country( string $code, string $name, string $continent_code = '', string $continent = '' ): int {
		if ( empty( $code ) ) {
			return 0;
		}

		return self::resolve(
			'countries',
			$code,
			[
				'code'           => $code,
				'name'           => $name,
				'continent_code' => $continent_code,
				'continent'      => $continent,
			],
			'code'
		);
	}

	/**
	 * Resolve a city dimension record.
	 *
	 * @param int    $country_id  Country ID.
	 * @param string $city_name   City name.
	 * @param string $region_code Region code.
	 * @param string $region_name Region name.
	 * @return int City ID.
	 */
	public static function resolve_city( int $country_id, string $city_name, string $region_code = '', string $region_name = '' ): int {
		if ( empty( $city_name ) || 0 === $country_id ) {
			return 0;
		}

		global $wpdb;
		$key = $country_id . ':' . $city_name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$select = $wpdb->prepare(
			'SELECT ID FROM %i WHERE country_id = %d AND city_name = %s LIMIT 1',
			TableRegistry::get( 'cities' ),
			$country_id,
			$city_name
		);
		return self::resolve_by_key(
			'cities',
			$key,
			[
				'country_id'  => $country_id,
				'city_name'   => $city_name,
				'region_code' => $region_code,
				'region_name' => $region_name,
			],
			$select
		);
	}

	/**
	 * Resolve a device type dimension record.
	 *
	 * @param string $name Device type name.
	 * @return int Device type ID.
	 */
	public static function resolve_device_type( string $name ): int {
		return empty( $name ) ? 0 : self::resolve( 'device_types', $name, [ 'name' => $name ], 'name' );
	}

	/**
	 * Resolve a browser dimension record.
	 *
	 * @param string $name Browser name.
	 * @return int Browser ID.
	 */
	public static function resolve_browser( string $name ): int {
		return empty( $name ) ? 0 : self::resolve( 'device_browsers', $name, [ 'name' => $name ], 'name' );
	}

	/**
	 * Resolve a browser version dimension record.
	 *
	 * @param int    $browser_id Browser ID.
	 * @param string $version    Version string.
	 * @return int Browser version ID.
	 */
	public static function resolve_browser_version( int $browser_id, string $version ): int {
		if ( 0 === $browser_id || empty( $version ) ) {
			return 0;
		}

		global $wpdb;
		$key = $browser_id . ':' . $version;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$select = $wpdb->prepare(
			'SELECT ID FROM %i WHERE browser_id = %d AND version = %s LIMIT 1',
			TableRegistry::get( 'device_browser_versions' ),
			$browser_id,
			$version
		);
		return self::resolve_by_key(
			'device_browser_versions',
			$key,
			[
				'browser_id' => $browser_id,
				'version'    => $version,
			],
			$select
		);
	}

	/**
	 * Resolve an OS dimension record.
	 *
	 * @param string $name OS name.
	 * @return int OS ID.
	 */
	public static function resolve_os( string $name ): int {
		return empty( $name ) ? 0 : self::resolve( 'device_oss', $name, [ 'name' => $name ], 'name' );
	}

	/**
	 * Resolve a screen resolution dimension record.
	 *
	 * @param int $width  Screen width.
	 * @param int $height Screen height.
	 * @return int Resolution ID.
	 */
	public static function resolve_resolution( int $width, int $height ): int {
		if ( 0 === $width && 0 === $height ) {
			return 0;
		}

		global $wpdb;
		$key = $width . 'x' . $height;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$select = $wpdb->prepare(
			'SELECT ID FROM %i WHERE width = %d AND height = %d LIMIT 1',
			TableRegistry::get( 'resolutions' ),
			$width,
			$height
		);
		return self::resolve_by_key(
			'resolutions',
			$key,
			[
				'width'  => $width,
				'height' => $height,
			],
			$select
		);
	}

	/**
	 * Resolve a language dimension record.
	 *
	 * @param string $code Language code (e.g., 'en-US').
	 * @return int Language ID.
	 */
	public static function resolve_language( string $code ): int {
		return empty( $code ) ? 0 : self::resolve( 'languages', $code, [ 'code' => $code ], 'code' );
	}

	/**
	 * Resolve a timezone dimension record.
	 *
	 * @param string $name IANA timezone name.
	 * @return int Timezone ID.
	 */
	public static function resolve_timezone( string $name ): int {
		return empty( $name ) ? 0 : self::resolve( 'timezones', $name, [ 'name' => $name ], 'name' );
	}

	/**
	 * Resolve a referrer dimension record with CRC32 deduplication.
	 *
	 * @param string $channel Channel classification.
	 * @param string $name    Source name.
	 * @param string $domain  Referrer domain.
	 * @return int Referrer ID.
	 */
	public static function resolve_referrer( string $channel, string $name, string $domain ): int {
		if ( empty( $channel ) ) {
			return 0;
		}

		global $wpdb;
		$domain_hash = crc32( $domain );
		$key         = $channel . ':' . $domain;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$select = $wpdb->prepare(
			'SELECT ID FROM %i WHERE domain_hash = %d AND domain = %s AND channel = %s LIMIT 1',
			TableRegistry::get( 'referrers' ),
			$domain_hash,
			$domain,
			$channel
		);
		return self::resolve_by_key(
			'referrers',
			$key,
			[
				'channel'     => $channel,
				'name'        => $name,
				'domain'      => $domain,
				'domain_hash' => $domain_hash,
			],
			$select
		);
	}

	/**
	 * Resolve all dimensions for a VisitorProfile and return FK IDs.
	 *
	 * @param \Statnive\Entity\VisitorProfile $profile The profile to resolve.
	 * @return array<string, int> Map of FK column name to dimension ID.
	 */
	public static function resolve_all( \Statnive\Entity\VisitorProfile $profile ): array {
		$country_id = self::resolve_country(
			$profile->get( 'country_code', '' ),
			$profile->get( 'country_name', '' ),
			$profile->get( 'continent_code', '' ),
			$profile->get( 'continent', '' )
		);

		$city_id = self::resolve_city(
			$country_id,
			$profile->get( 'city_name', '' ),
			$profile->get( 'region_code', '' )
		);

		$browser_id = self::resolve_browser( $profile->get( 'browser_name', '' ) );

		return [
			'country_id'                => $country_id,
			'city_id'                   => $city_id,
			'device_type_id'            => self::resolve_device_type( $profile->get( 'device_type', '' ) ),
			'device_browser_id'         => $browser_id,
			'device_browser_version_id' => self::resolve_browser_version( $browser_id, $profile->get( 'browser_version', '' ) ),
			'device_os_id'              => self::resolve_os( $profile->get( 'os_name', '' ) ),
			'resolution_id'             => self::resolve_resolution( $profile->get( 'screen_width', 0 ), $profile->get( 'screen_height', 0 ) ),
			'language_id'               => self::resolve_language( $profile->get( 'language', '' ) ),
			'timezone_id'               => self::resolve_timezone( $profile->get( 'timezone', '' ) ),
			'referrer_id'               => self::resolve_referrer(
				$profile->get( 'referrer_channel', '' ),
				$profile->get( 'referrer_name', '' ),
				$profile->get( 'referrer_domain', '' )
			),
		];
	}

	/**
	 * Generic resolve for tables with a single UNIQUE column.
	 *
	 * @param string               $table_name Table name without prefix.
	 * @param string               $cache_key  Cache lookup key.
	 * @param array<string, mixed> $data       Column data for insert.
	 * @param string               $unique_col The UNIQUE column name.
	 * @return int Row ID.
	 */
	private static function resolve( string $table_name, string $cache_key, array $data, string $unique_col ): int {
		// Check in-memory cache.
		if ( isset( self::$cache[ $table_name ][ $cache_key ] ) ) {
			return self::$cache[ $table_name ][ $cache_key ];
		}

		global $wpdb;
		$table = TableRegistry::get( $table_name );

		// Lookup by unique column.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ID FROM %i WHERE %i = %s LIMIT 1',
				$table,
				$unique_col,
				$data[ $unique_col ]
			)
		);

		if ( null !== $id ) {
			self::$cache[ $table_name ][ $cache_key ] = (int) $id;
			return (int) $id;
		}

		// Insert with race condition handling.
		$wpdb->insert( $table, $data, self::build_format( $data ) );
		$new_id = (int) $wpdb->insert_id;

		if ( $new_id > 0 ) {
			self::$cache[ $table_name ][ $cache_key ] = $new_id;
			return $new_id;
		}

		// Race condition: another process inserted the same value.
		// Re-query to get the ID.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ID FROM %i WHERE %i = %s LIMIT 1',
				$table,
				$unique_col,
				$data[ $unique_col ]
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$resolved = ( null !== $id ) ? (int) $id : 0;
		if ( $resolved > 0 ) {
			self::$cache[ $table_name ][ $cache_key ] = $resolved;
		}

		return $resolved;
	}

	/**
	 * Generic resolve for tables with composite unique keys.
	 *
	 * Accepts a fully prepared SELECT query (already run through
	 * $wpdb->prepare()) so PCP can trace the literal SQL at each call site.
	 *
	 * @param string               $table_name     Table name without prefix.
	 * @param string               $cache_key      Cache lookup key.
	 * @param array<string, mixed> $data           Column data for insert.
	 * @param string               $prepared_select Fully prepared SELECT query.
	 * @return int Row ID.
	 */
	private static function resolve_by_key( string $table_name, string $cache_key, array $data, string $prepared_select ): int {
		if ( isset( self::$cache[ $table_name ][ $cache_key ] ) ) {
			return self::$cache[ $table_name ][ $cache_key ];
		}

		global $wpdb;
		$table = TableRegistry::get( $table_name );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- $prepared_select is already prepared by each caller via $wpdb->prepare().
		$id = $wpdb->get_var( $prepared_select );

		if ( null !== $id ) {
			self::$cache[ $table_name ][ $cache_key ] = (int) $id;
			return (int) $id;
		}

		$wpdb->insert( $table, $data, self::build_format( $data ) );
		$new_id = (int) $wpdb->insert_id;

		if ( $new_id > 0 ) {
			self::$cache[ $table_name ][ $cache_key ] = $new_id;
			return $new_id;
		}

		// Race condition fallback.
		$id = $wpdb->get_var( $prepared_select );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		$resolved = ( null !== $id ) ? (int) $id : 0;
		if ( $resolved > 0 ) {
			self::$cache[ $table_name ][ $cache_key ] = $resolved;
		}

		return $resolved;
	}

	/**
	 * Build a format array for $wpdb->insert() from data values.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return string[] Format placeholders (%d or %s).
	 */
	private static function build_format( array $data ): array {
		return array_map( static fn( $v ) => is_int( $v ) ? '%d' : '%s', $data );
	}

	/**
	 * Clear all in-memory dimension caches.
	 */
	public static function clear_cache(): void {
		self::$cache = [];
	}
}
