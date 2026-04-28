<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Service\GeoIPDownloader;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

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

	public function test_dbip_filename_constant(): void {
		$this->assertSame( 'dbip-city-lite.mmdb', GeoIPDownloader::DBIP_FILENAME );
	}

	public function test_maxmind_filename_constant(): void {
		$this->assertSame( 'GeoLite2-City.mmdb', GeoIPDownloader::MAXMIND_FILENAME );
	}

	public function test_dbip_pending_transient_constant(): void {
		$this->assertSame( 'statnive_geoip_dbip_pending', GeoIPDownloader::DBIP_PENDING_TRANSIENT );
	}

	public function test_is_dbip_city_active_returns_false_when_neither_signal_present(): void {
		// Reset transient stub.
		unset( $GLOBALS['statnive_test_transients']['statnive_geoip_dbip_pending'] );
		$this->assertFalse( GeoIPDownloader::is_dbip_city_active() );
	}

	public function test_is_dbip_city_active_returns_true_when_pending_transient_set(): void {
		$GLOBALS['statnive_test_transients']['statnive_geoip_dbip_pending'] = 1;
		$this->assertTrue( GeoIPDownloader::is_dbip_city_active() );
		unset( $GLOBALS['statnive_test_transients']['statnive_geoip_dbip_pending'] );
	}
}
