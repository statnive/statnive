<?php

declare(strict_types=1);

namespace Statnive\Api\Concerns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for caching REST API responses via WordPress Transients.
 *
 * Uses version-salted cache keys and event-driven invalidation.
 * Cache key format: statnive_{endpoint}_v{version}_{wp_hash(params)}
 *
 * @see RealtimeController for the original pattern this was extracted from.
 */
trait CachesResponses {

	/**
	 * Build a cache key for a REST endpoint response.
	 *
	 * Uses wp_hash() instead of md5() to avoid CVE-2024-55885 (MD5 collisions
	 * in cache keys). Includes a version salt so all caches can be invalidated
	 * atomically by incrementing a single option.
	 *
	 * @param string               $endpoint The endpoint name (e.g. 'summary', 'sources').
	 * @param array<string, mixed> $params   Query parameters to include in the key.
	 * @return string Transient name (≤172 chars).
	 */
	protected function build_cache_key( string $endpoint, array $params ): string {
		$version = (int) get_option( 'statnive_cache_version', 0 );

		// Sort params for deterministic hashing.
		ksort( $params );
		$hash = substr( wp_hash( wp_json_encode( $params ) ), 0, 12 );

		return "statnive_{$endpoint}_v{$version}_{$hash}";
	}

	/**
	 * Determine cache TTL based on whether the date range includes today.
	 *
	 * - 30 seconds for ranges including today (real-time feel).
	 * - 5 minutes for purely historical ranges (data won't change).
	 *
	 * @param string $from Date range start (Y-m-d).
	 * @param string $to   Date range end (Y-m-d).
	 * @return int TTL in seconds.
	 */
	protected function get_cache_ttl( string $from, string $to ): int {
		$today = gmdate( 'Y-m-d' );

		if ( $from <= $today && $to >= $today ) {
			return 30;
		}

		return 5 * MINUTE_IN_SECONDS;
	}

	/**
	 * Try to serve a cached response. Returns null on cache miss.
	 *
	 * Uses strict === false comparison as transient values can legitimately
	 * be 0, '', or null.
	 *
	 * @param string               $endpoint The endpoint name.
	 * @param array<string, mixed> $params   Query parameters.
	 * @return mixed|null Cached data or null on miss.
	 */
	protected function get_cached_response( string $endpoint, array $params ) {
		$key    = $this->build_cache_key( $endpoint, $params );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		return null;
	}

	/**
	 * Store a response in the transient cache.
	 *
	 * @param string               $endpoint The endpoint name.
	 * @param array<string, mixed> $params   Query parameters.
	 * @param mixed                $data     Response data to cache.
	 * @param string               $from     Date range start (Y-m-d).
	 * @param string               $to       Date range end (Y-m-d).
	 */
	protected function set_cached_response( string $endpoint, array $params, $data, string $from, string $to ): void {
		$key = $this->build_cache_key( $endpoint, $params );
		$ttl = $this->get_cache_ttl( $from, $to );

		set_transient( $key, $data, $ttl );
	}

	/**
	 * Increment the global cache version, atomically invalidating all
	 * dashboard caches without deleting individual transients.
	 */
	protected function increment_version(): void {
		CacheVersion::increment();
	}
}
