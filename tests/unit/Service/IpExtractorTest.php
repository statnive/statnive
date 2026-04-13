<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Statnive\Service\IpExtractor;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

#[CoversClass(IpExtractor::class)]
final class IpExtractorTest extends TestCase {

	/**
	 * Reset $_SERVER between tests so headers don't leak.
	 */
	protected function setUp(): void {
		parent::setUp();

		unset(
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_X_FORWARDED_FOR'],
			$_SERVER['HTTP_X_REAL_IP'],
			$_SERVER['REMOTE_ADDR']
		);
	}

	#[DataProvider('proxy_header_provider')]
	public function test_ip_extracted_from_proxy_header( string $header, string $value, string $expected_ip ): void {
		$_SERVER[ $header ]     = $value;
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$result = IpExtractor::extract();

		$this->assertSame( $expected_ip, $result );
	}

	/**
	 * Data provider mapping BDD Scenario Outline examples.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function proxy_header_provider(): array {
		return [
			'CF-Connecting-IP'  => [ 'HTTP_CF_CONNECTING_IP', '198.51.100.10', '198.51.100.10' ],
			'X-Forwarded-For'   => [ 'HTTP_X_FORWARDED_FOR', '203.0.113.42, 10.0.0.1, 172.16.0.1', '203.0.113.42' ],
			'X-Real-IP'         => [ 'HTTP_X_REAL_IP', '203.0.113.42', '203.0.113.42' ],
		];
	}

	public function test_fallback_to_remote_addr_when_no_proxy_headers(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.42';

		$result = IpExtractor::extract();

		$this->assertSame( '203.0.113.42', $result );
	}

	public function test_fallback_to_loopback_when_no_headers_at_all(): void {
		// No $_SERVER headers set at all.
		$result = IpExtractor::extract();

		$this->assertSame( '127.0.0.1', $result );
	}

	public function test_cf_connecting_ip_takes_priority_over_x_forwarded_for(): void {
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.10';
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '203.0.113.42';
		$_SERVER['REMOTE_ADDR']           = '127.0.0.1';

		$result = IpExtractor::extract();

		$this->assertSame( '198.51.100.10', $result );
	}

	public function test_is_valid_ip_with_ipv4(): void {
		$this->assertTrue( IpExtractor::is_valid_ip( '192.168.1.1' ) );
		$this->assertTrue( IpExtractor::is_valid_ip( '8.8.8.8' ) );
	}

	public function test_is_valid_ip_with_ipv6(): void {
		$this->assertTrue( IpExtractor::is_valid_ip( '2001:db8::1' ) );
		$this->assertTrue( IpExtractor::is_valid_ip( '::1' ) );
	}

	public function test_is_valid_ip_rejects_invalid(): void {
		$this->assertFalse( IpExtractor::is_valid_ip( 'not-an-ip' ) );
		$this->assertFalse( IpExtractor::is_valid_ip( '' ) );
		$this->assertFalse( IpExtractor::is_valid_ip( '999.999.999.999' ) );
	}

	public function test_is_private_ip(): void {
		$this->assertTrue( IpExtractor::is_private_ip( '192.168.1.1' ) );
		$this->assertTrue( IpExtractor::is_private_ip( '10.0.0.1' ) );
		$this->assertTrue( IpExtractor::is_private_ip( '127.0.0.1' ) );
	}

	public function test_is_not_private_ip(): void {
		$this->assertFalse( IpExtractor::is_private_ip( '8.8.8.8' ) );
		$this->assertFalse( IpExtractor::is_private_ip( '1.1.1.1' ) );
	}
}
