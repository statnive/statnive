<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Service\GeoIPDownloader;

/**
 * Unit tests for GeoIPDownloader constants and configuration.
 *
 * Limited to what can be tested without WordPress functions.
 */
#[CoversClass(GeoIPDownloader::class)]
final class GeoIPDownloaderTest extends TestCase {

	public function test_no_third_party_mirror_constant(): void {
		$reflection = new \ReflectionClass( GeoIPDownloader::class );

		$this->assertFalse(
			$reflection->hasConstant( 'CDN_URL' ),
			'CDN_URL constant must not exist — MaxMind is the only download source.'
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
