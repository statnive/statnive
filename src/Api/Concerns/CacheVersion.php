<?php

declare(strict_types=1);

namespace Statnive\Api\Concerns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helper for cache version management.
 *
 * Uses an incrementor/version salt strategy: all dashboard cache keys
 * include a version number from wp_options. Incrementing the version
 * atomically invalidates every cached response without deleting
 * individual transients.
 *
 * @see CachesResponses::build_cache_key() — includes version in key.
 */
final class CacheVersion {

	/**
	 * Option name storing the current cache version.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'statnive_cache_version';

	/**
	 * Increment the global cache version.
	 */
	public static function increment(): void {
		$version = (int) get_option( self::OPTION_KEY, 0 );
		update_option( self::OPTION_KEY, $version + 1, true );
	}
}
