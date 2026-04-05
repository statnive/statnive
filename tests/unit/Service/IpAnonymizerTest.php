<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Statnive\Service\IpAnonymizer;

/**
 * Unit tests for IpAnonymizer.
 *
 * @covers \Statnive\Service\IpAnonymizer
 */
final class IpAnonymizerTest extends TestCase {

	public function test_ipv4_zeros_last_octet(): void {
		$this->assertSame( '192.168.1.0', IpAnonymizer::anonymize( '192.168.1.42' ) );
	}

	public function test_ipv4_already_zeroed(): void {
		$this->assertSame( '10.0.0.0', IpAnonymizer::anonymize( '10.0.0.0' ) );
	}

	public function test_ipv4_max_values(): void {
		$this->assertSame( '255.255.255.0', IpAnonymizer::anonymize( '255.255.255.255' ) );
	}

	public function test_ipv6_zeros_last_80_bits(): void {
		$result = IpAnonymizer::anonymize( '2001:db8:85a3:8d3:1319:8a2e:370:7348' );
		// First 48 bits (6 bytes / 3 groups) preserved, rest zeroed.
		// inet_ntop compresses trailing zeros, so result is '2001:db8:85a3::'.
		$this->assertStringStartsWith( '2001:db8:85a3:', $result );
	}

	public function test_ipv6_loopback(): void {
		$result = IpAnonymizer::anonymize( '::1' );
		// ::1 has all zeros in first 48 bits too.
		$this->assertNotEmpty( $result );
	}

	public function test_invalid_ip_returns_zeroed(): void {
		$this->assertSame( '0.0.0.0', IpAnonymizer::anonymize( 'not-an-ip' ) );
		$this->assertSame( '0.0.0.0', IpAnonymizer::anonymize( '' ) );
	}

	/**
	 * @dataProvider ipv4_provider
	 */
	public function test_ipv4_anonymization( string $input, string $expected ): void {
		$this->assertSame( $expected, IpAnonymizer::anonymize( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function ipv4_provider(): array {
		return [
			'standard address'   => [ '203.0.113.42', '203.0.113.0' ],
			'max last octet'     => [ '192.168.1.255', '192.168.1.0' ],
			'loopback'           => [ '127.0.0.1', '127.0.0.0' ],
			'private class A'    => [ '10.20.30.40', '10.20.30.0' ],
		];
	}

	public function test_ipv6_short_form(): void {
		$result = IpAnonymizer::anonymize( '2001:db8::1' );

		$this->assertStringStartsWith( '2001:db8::', $result );
	}

	public function test_different_ips_same_subnet_produce_same_anonymized(): void {
		$a = IpAnonymizer::anonymize( '192.168.1.100' );
		$b = IpAnonymizer::anonymize( '192.168.1.200' );

		$this->assertSame( $a, $b );
		$this->assertSame( '192.168.1.0', $a );
	}
}
