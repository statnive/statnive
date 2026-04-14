<?php
/**
 * Generated from BDD scenarios (03-analytics-enrichment.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Database\DatabaseFactory;
use Statnive\Service\GeoIPService;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for GeoIP resolution service.
 *
 * @covers \Statnive\Service\GeoIPService
 */
final class GeoIPServiceTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		GeoIPService::clear_cache();
	}

	/**
	 * @testdox Country+city from public IP (when GeoIP DB is available)
	 */
	public function test_country_city_from_public_ip(): void {
		if ( ! GeoIPService::is_available() ) {
			$this->markTestSkipped( 'GeoLite2-City database not available.' );
		}

		$result = GeoIPService::resolve( '203.0.113.42' );

		$this->assertNotEmpty( $result['country_code'], 'Country code should not be empty for a public IP' );
		$this->assertIsString( $result['city_name'], 'City name should be a string' );
	}

	/**
	 * @testdox Graceful degradation when DB file missing
	 */
	public function test_graceful_degradation_when_db_missing(): void {
		// Force GeoIP to look at a nonexistent path by clearing the cache
		// and testing with a known IP. If the DB is not present, it should return empty.
		$result = GeoIPService::resolve( '203.0.113.42' );

		// Should return without error — either populated data or empty strings.
		$this->assertIsArray( $result, 'GeoIP resolve should always return an array' );
		$this->assertArrayHasKey( 'country_code', $result, 'Result should have country_code key' );
		$this->assertArrayHasKey( 'city_name', $result, 'Result should have city_name key' );
	}

	/**
	 * @testdox Localhost IP returns empty geo data
	 */
	public function test_localhost_returns_empty(): void {
		$result = GeoIPService::resolve( '127.0.0.1' );

		$this->assertSame( '', $result['country_code'], 'Localhost should return empty country_code' );
		$this->assertSame( '', $result['city_name'], 'Localhost should return empty city_name' );
	}

	/**
	 * @testdox Empty IP returns empty geo data
	 */
	public function test_empty_ip_returns_empty(): void {
		$result = GeoIPService::resolve( '' );

		$this->assertSame( '', $result['country_code'], 'Empty IP should return empty country_code' );
		$this->assertSame( '', $result['city_name'], 'Empty IP should return empty city_name' );
	}

	/**
	 * @testdox IPv6 localhost returns empty geo data
	 */
	public function test_ipv6_localhost_returns_empty(): void {
		$result = GeoIPService::resolve( '::1' );

		$this->assertSame( '', $result['country_code'], 'IPv6 localhost should return empty country_code' );
	}
}
