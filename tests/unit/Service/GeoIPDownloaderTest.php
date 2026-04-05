<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Statnive\Service\GeoIPDownloader;

/**
 * Unit tests for GeoIPDownloader constants and configuration (bugs #8, #9).
 *
 * Limited to what can be tested without WordPress functions.
 *
 * @covers \Statnive\Service\GeoIPDownloader
 */
final class GeoIPDownloaderTest extends TestCase {

	public function test_cdn_url_constant_is_valid_url(): void {
		$reflection = new \ReflectionClass( GeoIPDownloader::class );
		$cdn_url    = $reflection->getConstant( 'CDN_URL' );

		$this->assertIsString( $cdn_url, 'CDN_URL must be a string.' );
		$this->assertNotEmpty( $cdn_url, 'CDN_URL must not be empty.' );
		$this->assertNotFalse(
			filter_var( $cdn_url, FILTER_VALIDATE_URL ),
			"CDN_URL must be a valid URL, got: {$cdn_url}"
		);
	}

	/**
	 * Verify that the CDN URL is reachable via HTTP.
	 *
	 * @group network
	 */
	public function test_cdn_url_is_reachable(): void {
		$reflection = new \ReflectionClass( GeoIPDownloader::class );
		$cdn_url    = $reflection->getConstant( 'CDN_URL' );

		$headers = @get_headers( $cdn_url );
		$this->assertNotFalse( $headers, 'CDN URL must be reachable.' );
		$this->assertNotEmpty( $headers, 'CDN URL must return headers.' );

		// Accept 200, 301, or 302 as valid responses (GitHub redirects to release assets).
		$status_line = $headers[0];
		$this->assertMatchesRegularExpression(
			'/HTTP\/[\d.]+ (200|301|302)/',
			$status_line,
			"CDN URL should return HTTP 200, 301, or 302. Got: {$status_line}"
		);
	}

	public function test_cron_hook_constant_matches_expected(): void {
		$this->assertSame(
			'statnive_weekly_geoip_update',
			GeoIPDownloader::CRON_HOOK,
			'CRON_HOOK must be "statnive_weekly_geoip_update".'
		);
	}
}
