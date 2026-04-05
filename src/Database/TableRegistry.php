<?php

declare(strict_types=1);

namespace Statnive\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of all Statnive database table names.
 *
 * Provides prefixed table names for use across the plugin.
 */
final class TableRegistry {

	/**
	 * Table name suffix constants.
	 *
	 * @var string[]
	 */
	private const TABLE_NAMES = [
		'visitors',
		'sessions',
		'views',
		'resources',
		'resource_uris',
		'parameters',
		'countries',
		'cities',
		'device_types',
		'device_browsers',
		'device_browser_versions',
		'device_oss',
		'resolutions',
		'languages',
		'timezones',
		'referrers',
		'summary',
		'summary_totals',
		'events',
		'exclusions',
		'reports',
	];

	/**
	 * Get the full prefixed table name.
	 *
	 * @param string $name Table name without prefix (e.g., 'visitors').
	 * @return string Full table name (e.g., 'wp_statnive_visitors').
	 */
	public static function get( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'statnive_' . $name;
	}

	/**
	 * Get all registered table names (unprefixed suffixes).
	 *
	 * @return string[]
	 */
	public static function all_names(): array {
		return self::TABLE_NAMES;
	}

	/**
	 * Get all full prefixed table names.
	 *
	 * @return string[]
	 */
	public static function all_prefixed(): array {
		return array_map( [ self::class, 'get' ], self::TABLE_NAMES );
	}
}
