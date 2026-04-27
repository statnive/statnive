<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Statnive\Service\GeoIPService;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

/**
 * Covers GeoIPService::resolve_from_timezone — composing the canonical 6-field
 * geo-result shape from a TimezoneGeoResolver lookup, and the detect_source
 * fallback's new 'timezone' return value.
 */
#[CoversClass(GeoIPService::class)]
final class GeoIPServiceTimezoneTest extends TestCase {

	private const CDN_HEADER_KEYS = [
		'HTTP_CF_IPCOUNTRY',
		'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
		'HTTP_X_VERCEL_IP_COUNTRY',
		'HTTP_X_COUNTRY_CODE',
		'REMOTE_ADDR',
	];

	protected function setUp(): void {
		parent::setUp();
		GeoIPService::clear_cache();
		foreach ( self::CDN_HEADER_KEYS as $key ) {
			unset( $_SERVER[ $key ] );
		}
	}

	protected function tearDown(): void {
		foreach ( self::CDN_HEADER_KEYS as $key ) {
			unset( $_SERVER[ $key ] );
		}
		parent::tearDown();
	}

	#[DataProvider('resolved_zone_provider')]
	public function test_resolves_country_for_known_timezone( string $tz, string $code, string $name ): void {
		$result = GeoIPService::resolve_from_timezone( $tz );

		$this->assertSame( $code, $result['country_code'] );
		$this->assertSame( $name, $result['country_name'] );
		// Other fields are always empty for the timezone tier — it has no
		// city / region / continent signal.
		$this->assertSame( '', $result['city_name'] );
		$this->assertSame( '', $result['region_code'] );
		$this->assertSame( '', $result['continent_code'] );
		$this->assertSame( '', $result['continent'] );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function resolved_zone_provider(): array {
		return [
			'New York'  => [ 'America/New_York', 'US', 'United States' ],
			'Vienna'    => [ 'Europe/Vienna', 'AT', 'Austria' ],
			'Berlin'    => [ 'Europe/Berlin', 'DE', 'Germany' ],
			'Tokyo'     => [ 'Asia/Tokyo', 'JP', 'Japan' ],
			'US/Eastern alias' => [ 'US/Eastern', 'US', 'United States' ],
		];
	}

	public function test_returns_empty_for_unresolvable_timezone(): void {
		$result = GeoIPService::resolve_from_timezone( 'Etc/GMT+5' );
		$this->assertSame( '', $result['country_code'] );
		$this->assertSame( '', $result['country_name'] );
	}

	public function test_returns_empty_for_empty_input(): void {
		$result = GeoIPService::resolve_from_timezone( '' );
		$this->assertSame( '', $result['country_code'] );
	}
}
