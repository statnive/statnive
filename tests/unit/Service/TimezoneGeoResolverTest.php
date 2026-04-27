<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Statnive\Service\TimezoneGeoResolver;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

#[CoversClass(TimezoneGeoResolver::class)]
final class TimezoneGeoResolverTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		TimezoneGeoResolver::clear_cache();
	}

	#[DataProvider('canonical_zone_provider')]
	public function test_canonical_zones_resolve_to_expected_country( string $tz, string $expected ): void {
		$this->assertSame( $expected, TimezoneGeoResolver::resolve( $tz ) );
	}

	/**
	 * Canonical zones from zone1970.tab covering each populated continent.
	 *
	 * Multi-country zones map to the IANA-designated primary (first listed):
	 * Europe/Berlin → DE, Europe/London → GB, Europe/Paris → FR, Asia/Tokyo → JP.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function canonical_zone_provider(): array {
		return [
			'New York'    => [ 'America/New_York', 'US' ],
			'Los Angeles' => [ 'America/Los_Angeles', 'US' ],
			'Toronto'     => [ 'America/Toronto', 'CA' ],
			'Sao Paulo'   => [ 'America/Sao_Paulo', 'BR' ],
			'Berlin'      => [ 'Europe/Berlin', 'DE' ],
			'London'      => [ 'Europe/London', 'GB' ],
			'Paris'       => [ 'Europe/Paris', 'FR' ],
			'Vienna'      => [ 'Europe/Vienna', 'AT' ],
			'Helsinki'    => [ 'Europe/Helsinki', 'FI' ],
			'Tokyo'       => [ 'Asia/Tokyo', 'JP' ],
			'Kolkata'     => [ 'Asia/Kolkata', 'IN' ],
			'Shanghai'    => [ 'Asia/Shanghai', 'CN' ],
			'Sydney'      => [ 'Australia/Sydney', 'AU' ],
			'Auckland'    => [ 'Pacific/Auckland', 'NZ' ],
			'Cairo'       => [ 'Africa/Cairo', 'EG' ],
		];
	}

	#[DataProvider('backward_alias_provider')]
	public function test_backward_aliases_resolve_to_canonical_country( string $alias, string $expected ): void {
		$this->assertSame( $expected, TimezoneGeoResolver::resolve( $alias ) );
	}

	/**
	 * Aliases from the IANA backward file should resolve to the same country
	 * as their canonical zone after pre-flattening at build time.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function backward_alias_provider(): array {
		return [
			'US/Eastern legacy alias' => [ 'US/Eastern', 'US' ],
			'US/Pacific legacy alias' => [ 'US/Pacific', 'US' ],
			'Asia/Calcutta → Kolkata' => [ 'Asia/Calcutta', 'IN' ],
			'Asia/Saigon → Ho Chi Minh' => [ 'Asia/Saigon', 'VN' ],
			'Europe/Kiev → Kyiv'      => [ 'Europe/Kiev', 'UA' ],
		];
	}

	#[DataProvider('unresolvable_provider')]
	public function test_unresolvable_inputs_return_empty( string $tz ): void {
		$this->assertSame( '', TimezoneGeoResolver::resolve( $tz ) );
	}

	/**
	 * Inputs that carry no country signal — generic offset zones, empty
	 * strings, and unknown identifiers — must never guess a country.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unresolvable_provider(): array {
		return [
			'empty string'         => [ '' ],
			'UTC'                  => [ 'UTC' ],
			'GMT'                  => [ 'GMT' ],
			'Etc offset (positive)' => [ 'Etc/GMT+5' ],
			'Etc offset (negative)' => [ 'Etc/GMT-3' ],
			'numeric offset +'     => [ '+05:30' ],
			'numeric offset -'     => [ '-08:00' ],
			'unknown zone'         => [ 'Atlantis/El_Dorado' ],
			'partial match'        => [ 'America/' ],
		];
	}

	public function test_lookup_table_has_substantial_coverage(): void {
		// Regression alarm: a build that accidentally drops the bulk of the
		// table (parser bug, file truncation) would make this test fail.
		// 200 is a deliberately loose floor; the real table sits at ~550.
		$path = dirname( __DIR__, 3 ) . '/src/Data/timezone-countries.php';
		$this->assertFileExists( $path );

		/** @var array<string, string> $map */
		$map = require $path;
		$this->assertGreaterThan( 200, count( $map ) );

		// Every value must be a 2-letter uppercase ISO code.
		foreach ( $map as $tz => $code ) {
			$this->assertMatchesRegularExpression( '/^[A-Z]{2}$/', $code, "Bad ISO code for {$tz}" );
		}
	}
}
