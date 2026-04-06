<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Service\DeviceService;

/**
 * Unit tests for DeviceService::parse() with various User-Agent strings.
 *
 * Verifies correct browser, device type, and OS detection regardless of
 * whether DeviceDetector or the fallback regex parser is used (bug #5).
 */
#[CoversClass(DeviceService::class)]
final class DeviceServiceFallbackTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		DeviceService::clear_cache();
	}

	protected function tearDown(): void {
		DeviceService::clear_cache();
		parent::tearDown();
	}

	public function test_parse_chrome_desktop(): void {
		$ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
		$result = DeviceService::parse( $ua );

		$this->assertStringContainsStringIgnoringCase( 'Chrome', $result['browser_name'] );
		$this->assertStringContainsStringIgnoringCase( 'Desktop', $result['device_type'] );
		$this->assertStringContainsStringIgnoringCase( 'Windows', $result['os_name'] );
	}

	public function test_parse_firefox_desktop(): void {
		$ua     = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0';
		$result = DeviceService::parse( $ua );

		$this->assertSame( 'Firefox', $result['browser_name'] );
		$this->assertMatchesRegularExpression( '/Mac|OS X/i', $result['os_name'] );
	}

	public function test_parse_safari_mobile(): void {
		$ua     = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
		$result = DeviceService::parse( $ua );

		$this->assertMatchesRegularExpression( '/Safari|Mobile Safari/i', $result['browser_name'] );
		$this->assertMatchesRegularExpression( '/Mobile|smartphone/i', $result['device_type'] );
		$this->assertMatchesRegularExpression( '/iOS|Mac/i', $result['os_name'] );
	}

	public function test_parse_edge(): void {
		$ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
		$result = DeviceService::parse( $ua );

		$this->assertStringContainsStringIgnoringCase( 'Edge', $result['browser_name'] );
	}

	public function test_parse_android_mobile(): void {
		$ua     = 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
		$result = DeviceService::parse( $ua );

		$this->assertMatchesRegularExpression( '/Mobile|smartphone/i', $result['device_type'] );
		$this->assertSame( 'Android', $result['os_name'] );
	}

	public function test_parse_empty_ua_returns_empty(): void {
		$result = DeviceService::parse( '' );

		$this->assertSame( '', $result['device_type'] );
		$this->assertSame( '', $result['browser_name'] );
		$this->assertSame( '', $result['browser_version'] );
		$this->assertSame( '', $result['os_name'] );
	}

	public function test_parse_caches_results(): void {
		$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

		$first  = DeviceService::parse( $ua );
		$second = DeviceService::parse( $ua );
		$this->assertSame( $first, $second, 'Cached result should match first parse.' );

		DeviceService::clear_cache();

		$third = DeviceService::parse( $ua );
		$this->assertSame( $first, $third, 'Result after cache clear should match original parse.' );
	}

	public function test_parse_returns_correct_keys(): void {
		$ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
		$result = DeviceService::parse( $ua );

		$expected_keys = [ 'device_type', 'browser_name', 'browser_version', 'os_name' ];
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result, "Result must contain key '{$key}'." );
		}
		$this->assertCount( 4, $result, 'Result should have exactly 4 keys.' );
	}
}
