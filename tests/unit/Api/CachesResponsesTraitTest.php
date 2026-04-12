<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Statnive\Api\Concerns\CachesResponses;

/**
 * Unit tests for the CachesResponses trait.
 *
 * TDD: these tests define the expected caching behaviour. The trait is
 * implemented to make them pass.
 *
 * @covers \Statnive\Api\Concerns\CachesResponses
 */
final class CachesResponsesTraitTest extends TestCase {

	/**
	 * Concrete class that uses the trait so we can test it directly.
	 *
	 * @var object
	 */
	private object $subject;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_options']    = [];
		$GLOBALS['statnive_test_transients'] = [];

		$this->subject = new class() {
			use CachesResponses;

			/**
			 * Public proxy so tests can call the protected method.
			 *
			 * @param string $endpoint Cache endpoint name.
			 * @param array  $params   Query parameters.
			 * @return string
			 */
			public function test_build_cache_key( string $endpoint, array $params ): string {
				return $this->build_cache_key( $endpoint, $params );
			}

			/**
			 * Public proxy for TTL calculation.
			 *
			 * @param string $from Date range start.
			 * @param string $to   Date range end.
			 * @return int
			 */
			public function test_get_cache_ttl( string $from, string $to ): int {
				return $this->get_cache_ttl( $from, $to );
			}
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['statnive_test_options'], $GLOBALS['statnive_test_transients'] );
		parent::tearDown();
	}

	/**
	 * @testdox Cache key uses wp_hash, not md5, to avoid CVE-2024-55885
	 */
	public function test_cache_key_uses_wp_hash_not_md5(): void {
		$params = [ 'from' => '2026-04-05', 'to' => '2026-04-11', 'limit' => 10 ];

		$key = $this->subject->test_build_cache_key( 'summary', $params );

		// Key must NOT contain a raw md5 hash (32-hex chars).
		$raw_md5 = md5( wp_json_encode( $params ) );
		$this->assertStringNotContainsString( $raw_md5, $key, 'Cache key must not use raw md5 hash' );

		// Key must start with the statnive prefix.
		$this->assertStringStartsWith( 'statnive_', $key );
	}

	/**
	 * @testdox Cache key includes version salt from option
	 */
	public function test_cache_key_includes_version_salt(): void {
		// Set a known version.
		update_option( 'statnive_cache_version', 42 );

		$key = $this->subject->test_build_cache_key( 'summary', [ 'from' => '2026-04-05', 'to' => '2026-04-11' ] );

		$this->assertStringContainsString( '_v42_', $key, 'Cache key must include version salt' );
	}

	/**
	 * @testdox TTL is 30 seconds when date range includes today
	 */
	public function test_ttl_30s_for_today_range(): void {
		$today = gmdate( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', strtotime( '-6 days' ) );

		$ttl = $this->subject->test_get_cache_ttl( $from, $today );

		$this->assertSame( 30, $ttl );
	}

	/**
	 * @testdox TTL is 5 minutes for purely historical range
	 */
	public function test_ttl_5min_for_historical_range(): void {
		$to   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$from = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		$ttl = $this->subject->test_get_cache_ttl( $from, $to );

		$this->assertSame( 5 * MINUTE_IN_SECONDS, $ttl );
	}

	/**
	 * @testdox Different parameters produce different cache keys
	 */
	public function test_different_params_produce_different_keys(): void {
		$key_a = $this->subject->test_build_cache_key( 'summary', [ 'from' => '2026-04-01', 'to' => '2026-04-07' ] );
		$key_b = $this->subject->test_build_cache_key( 'summary', [ 'from' => '2026-04-01', 'to' => '2026-04-08' ] );

		$this->assertNotSame( $key_a, $key_b );
	}

	/**
	 * @testdox Different endpoints produce different cache keys
	 */
	public function test_different_endpoints_produce_different_keys(): void {
		$params = [ 'from' => '2026-04-01', 'to' => '2026-04-07' ];

		$key_a = $this->subject->test_build_cache_key( 'summary', $params );
		$key_b = $this->subject->test_build_cache_key( 'sources', $params );

		$this->assertNotSame( $key_a, $key_b );
	}

	/**
	 * @testdox Cache key length is within WordPress transient name limit (172 chars)
	 */
	public function test_cache_key_under_172_chars(): void {
		$params = [
			'from'   => '2026-04-01',
			'to'     => '2026-04-30',
			'limit'  => 100,
			'offset' => 500,
			'type'   => 'countries',
		];

		$key = $this->subject->test_build_cache_key( 'dimensions', $params );

		$this->assertLessThanOrEqual( 172, strlen( $key ), 'Transient name must be ≤172 chars' );
	}
}
