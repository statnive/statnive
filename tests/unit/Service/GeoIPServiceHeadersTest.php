<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Statnive\Service\GeoIPService;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

#[CoversClass(GeoIPService::class)]
final class GeoIPServiceHeadersTest extends TestCase {

	private const ALL_HEADERS = [
		'HTTP_CF_IPCOUNTRY',
		'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
		'HTTP_X_VERCEL_IP_COUNTRY',
		'HTTP_X_COUNTRY_CODE',
		'REMOTE_ADDR',
	];

	protected function setUp(): void {
		parent::setUp();
		GeoIPService::clear_cache();
		foreach ( self::ALL_HEADERS as $key ) {
			unset( $_SERVER[ $key ] );
		}
	}

	protected function tearDown(): void {
		foreach ( self::ALL_HEADERS as $key ) {
			unset( $_SERVER[ $key ] );
		}
		parent::tearDown();
	}

	#[DataProvider('valid_header_provider')]
	public function test_resolves_country_from_each_cdn_header( string $header, string $value, string $expected_code, string $expected_name ): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
		$_SERVER[ $header ]     = $value;

		$result = GeoIPService::resolve_from_request_headers();

		$this->assertSame( $expected_code, $result['country_code'] );
		$this->assertSame( $expected_name, $result['country_name'] );
		$this->assertSame( '', $result['city_name'] );
		$this->assertSame( '', $result['region_code'] );
		$this->assertSame( '', $result['continent_code'] );
		$this->assertSame( '', $result['continent'] );
	}

	/**
	 * @return array<string, array{string, string, string, string}>
	 */
	public static function valid_header_provider(): array {
		return [
			'Cloudflare'  => [ 'HTTP_CF_IPCOUNTRY', 'DE', 'DE', 'Germany' ],
			'CloudFront'  => [ 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'FR', 'FR', 'France' ],
			'Vercel'      => [ 'HTTP_X_VERCEL_IP_COUNTRY', 'JP', 'JP', 'Japan' ],
			'Generic'     => [ 'HTTP_X_COUNTRY_CODE', 'CA', 'CA', 'Canada' ],
			'lowercase'   => [ 'HTTP_CF_IPCOUNTRY', 'de', 'DE', 'Germany' ],
		];
	}

	public function test_cloudflare_wins_priority_when_multiple_headers_set(): void {
		$_SERVER['REMOTE_ADDR']                    = '203.0.113.5';
		$_SERVER['HTTP_CF_IPCOUNTRY']              = 'DE';
		$_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] = 'FR';
		$_SERVER['HTTP_X_VERCEL_IP_COUNTRY']       = 'JP';

		$result = GeoIPService::resolve_from_request_headers();

		$this->assertSame( 'DE', $result['country_code'] );
	}

	#[DataProvider('rejected_value_provider')]
	public function test_rejects_sentinel_and_malformed_codes( string $value ): void {
		$_SERVER['REMOTE_ADDR']       = '203.0.113.5';
		$_SERVER['HTTP_CF_IPCOUNTRY'] = $value;

		$result = GeoIPService::resolve_from_request_headers();

		$this->assertSame( '', $result['country_code'] );
		$this->assertSame( '', $result['country_name'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function rejected_value_provider(): array {
		return [
			'sentinel XX'    => [ 'XX' ],
			'sentinel T1'    => [ 'T1' ],
			'too-short U'    => [ 'U' ],
			'empty string'   => [ '' ],
			'invalid QQ'     => [ 'QQ' ],
			'digits 12'      => [ '12' ],
			'unicode punct'  => [ '--' ],
		];
	}

	public function test_accepts_three_letter_code_by_taking_first_two_when_valid_prefix(): void {
		// "USA" first-two is "US" which is valid; design intentionally tolerates this.
		$_SERVER['REMOTE_ADDR']       = '203.0.113.5';
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'USA';

		$result = GeoIPService::resolve_from_request_headers();

		$this->assertSame( 'US', $result['country_code'] );
	}

	#[DataProvider('private_remote_provider')]
	public function test_rejects_when_remote_addr_is_private_or_missing( string $remote ): void {
		if ( '' !== $remote ) {
			$_SERVER['REMOTE_ADDR'] = $remote;
		}
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';

		$result = GeoIPService::resolve_from_request_headers();

		$this->assertSame( '', $result['country_code'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function private_remote_provider(): array {
		return [
			'loopback v4' => [ '127.0.0.1' ],
			'RFC1918 /8'  => [ '10.0.0.5' ],
			'RFC1918 /16' => [ '192.168.1.1' ],
			'loopback v6' => [ '::1' ],
			'missing'     => [ '' ],
		];
	}

	public function test_first_cdn_header_name_is_null_without_any_header(): void {
		$this->assertNull( GeoIPService::first_cdn_header_name() );
	}

	public function test_first_cdn_header_name_returns_display_label(): void {
		$_SERVER['HTTP_X_VERCEL_IP_COUNTRY'] = 'US';
		$this->assertSame( 'X-Vercel-IP-Country', GeoIPService::first_cdn_header_name() );
	}

	public function test_detect_source_falls_back_through_the_tiers(): void {
		$this->assertSame( 'none', GeoIPService::detect_source() );

		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';
		$this->assertSame( 'cdn_headers', GeoIPService::detect_source() );
	}
}
