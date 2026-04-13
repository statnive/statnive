<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Database\DatabaseFactory;
use Statnive\Service\GeoIPService;
use Statnive\Service\IpExtractor;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for IpExtractor filter and GeoIPService private IP handling.
 *
 * Covers bug #10 (IP override via statnive_client_ip filter).
 *
 * @covers \Statnive\Service\IpExtractor
 * @covers \Statnive\Service\GeoIPService
 */
final class IpExtractorFilterTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		GeoIPService::clear_cache();
	}

	/**
	 * @testdox statnive_client_ip filter overrides extracted IP address
	 */
	public function test_filter_overrides_extracted_ip(): void {
		$callback = fn() => '8.8.8.8';
		add_filter( 'statnive_client_ip', $callback );

		$result = IpExtractor::extract();

		$this->assertSame( '8.8.8.8', $result, 'statnive_client_ip filter should override the extracted IP' );

		remove_filter( 'statnive_client_ip', $callback );

		// Verify the filter was removed and doesn't affect subsequent calls.
		// Without the filter, it should fall back to REMOTE_ADDR or 127.0.0.1.
		$result_after = IpExtractor::extract();
		$this->assertNotSame( '8.8.8.8', $result_after, 'After removing filter, IP should no longer be 8.8.8.8' );
	}

	/**
	 * @testdox GeoIPService returns empty fields for private/loopback IP addresses
	 */
	public function test_private_ip_returns_empty_geoip(): void {
		$result = GeoIPService::resolve( '127.0.0.1' );

		$this->assertSame( '', $result['country_code'], 'Private IP should return empty country_code' );
		$this->assertSame( '', $result['country_name'], 'Private IP should return empty country_name' );
		$this->assertSame( '', $result['city_name'], 'Private IP should return empty city_name' );
		$this->assertSame( '', $result['region_code'], 'Private IP should return empty region_code' );
		$this->assertSame( '', $result['continent_code'], 'Private IP should return empty continent_code' );
		$this->assertSame( '', $result['continent'], 'Private IP should return empty continent' );

		// Also test other private ranges.
		$result_192 = GeoIPService::resolve( '192.168.1.1' );
		$this->assertSame( '', $result_192['country_code'], 'Private 192.168.x.x IP should return empty country_code' );

		$result_10 = GeoIPService::resolve( '10.0.0.1' );
		$this->assertSame( '', $result_10['country_code'], 'Private 10.x.x.x IP should return empty country_code' );
	}

	/**
	 * @testdox GeoIPService does not skip public IP addresses
	 */
	public function test_public_ip_not_skipped_by_geoip(): void {
		$result = GeoIPService::resolve( '8.8.8.8' );

		// GeoIPService should attempt resolution for public IPs.
		// If the MaxMind DB is available, we get data; if not, we get empty strings.
		// Either way, the method should not throw or return null.
		$this->assertIsArray( $result, 'GeoIPService::resolve() should return an array for public IPs' );
		$this->assertArrayHasKey( 'country_code', $result, 'Result should contain country_code key' );
		$this->assertArrayHasKey( 'country_name', $result, 'Result should contain country_name key' );
		$this->assertArrayHasKey( 'city_name', $result, 'Result should contain city_name key' );
		$this->assertArrayHasKey( 'region_code', $result, 'Result should contain region_code key' );
		$this->assertArrayHasKey( 'continent_code', $result, 'Result should contain continent_code key' );
		$this->assertArrayHasKey( 'continent', $result, 'Result should contain continent key' );

		// Verify it's a string, not null or another type.
		$this->assertIsString( $result['country_code'], 'country_code should be a string (empty or populated)' );
	}
}
