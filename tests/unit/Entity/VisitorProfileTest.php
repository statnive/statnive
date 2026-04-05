<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Statnive\Entity\VisitorProfile;

/**
 * Unit tests for VisitorProfile data bus.
 *
 * Tests the metadata get/set/has operations and fluent builder methods.
 * WordPress-dependent methods (from_request, persist, compute_visitor_hash)
 * are tested in integration tests.
 *
 * @covers \Statnive\Entity\VisitorProfile
 */
final class VisitorProfileTest extends TestCase {

	public function test_set_and_get(): void {
		$profile = new VisitorProfile();
		$profile->set( 'key', 'value' );

		$this->assertSame( 'value', $profile->get( 'key' ) );
	}

	public function test_get_returns_fallback_for_missing_key(): void {
		$profile = new VisitorProfile();

		$this->assertNull( $profile->get( 'missing' ) );
		$this->assertSame( 'default', $profile->get( 'missing', 'default' ) );
	}

	public function test_has_returns_correct_state(): void {
		$profile = new VisitorProfile();
		$profile->set( 'exists', true );

		$this->assertTrue( $profile->has( 'exists' ) );
		$this->assertFalse( $profile->has( 'missing' ) );
	}

	public function test_set_returns_self_for_fluent_chaining(): void {
		$profile = new VisitorProfile();

		$result = $profile->set( 'a', 1 )->set( 'b', 2 )->set( 'c', 3 );

		$this->assertSame( $profile, $result );
		$this->assertSame( 1, $profile->get( 'a' ) );
		$this->assertSame( 3, $profile->get( 'c' ) );
	}

	public function test_with_geo_ip_stores_location_data(): void {
		$profile = new VisitorProfile();
		$result  = $profile->with_geo_ip( 'US', 'United States', 'New York', 'NY', 'NA', 'North America' );

		$this->assertSame( $profile, $result );
		$this->assertSame( 'US', $profile->get( 'country_code' ) );
		$this->assertSame( 'United States', $profile->get( 'country_name' ) );
		$this->assertSame( 'New York', $profile->get( 'city_name' ) );
		$this->assertSame( 'NY', $profile->get( 'region_code' ) );
		$this->assertSame( 'NA', $profile->get( 'continent_code' ) );
	}

	public function test_with_device_data_stores_device_info(): void {
		$profile = new VisitorProfile();
		$result  = $profile->with_device_data( 'Desktop', 'Chrome', '120.0', 'Windows' );

		$this->assertSame( $profile, $result );
		$this->assertSame( 'Desktop', $profile->get( 'device_type' ) );
		$this->assertSame( 'Chrome', $profile->get( 'browser_name' ) );
		$this->assertSame( '120.0', $profile->get( 'browser_version' ) );
		$this->assertSame( 'Windows', $profile->get( 'os_name' ) );
	}

	public function test_set_overwrites_existing_value(): void {
		$profile = new VisitorProfile();
		$profile->set( 'key', 'old' );
		$profile->set( 'key', 'new' );

		$this->assertSame( 'new', $profile->get( 'key' ) );
	}

	public function test_has_returns_true_for_null_value(): void {
		$profile = new VisitorProfile();
		$profile->set( 'nullable', null );

		// has() checks key existence, not value truthiness.
		$this->assertTrue( $profile->has( 'nullable' ) );
	}

	public function test_mixed_type_values(): void {
		$profile = new VisitorProfile();
		$profile->set( 'int', 42 );
		$profile->set( 'bool', true );
		$profile->set( 'array', [ 'a', 'b' ] );
		$profile->set( 'string', 'hello' );

		$this->assertSame( 42, $profile->get( 'int' ) );
		$this->assertTrue( $profile->get( 'bool' ) );
		$this->assertSame( [ 'a', 'b' ], $profile->get( 'array' ) );
		$this->assertSame( 'hello', $profile->get( 'string' ) );
	}
}
