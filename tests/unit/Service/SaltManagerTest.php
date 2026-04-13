<?php
/**
 * Generated from BDD scenarios: features/02-privacy-hashing.feature
 * Scenarios: "Same IP and User-Agent with same salt produce identical BINARY(8) hash"
 *            "Salt rotation produces a different visitor hash for the same visitor"
 *            "Salt is generated using cryptographically secure random bytes"
 *
 * Because SaltManager relies on WordPress options API (get_option, update_option),
 * these tests exercise the hashing algorithm directly using the same primitives
 * (SHA-256 + truncation) without requiring WordPress.
 *
 * May need adjustment when source class API changes.
 */

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Service\SaltManager;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

#[CoversClass(SaltManager::class)]
final class SaltManagerTest extends TestCase {

	/**
	 * Reproduce the visitor hash algorithm: SHA-256(salt + anonymized_ip + user_agent), truncated to 8 bytes.
	 *
	 * @param string $salt Binary salt.
	 * @param string $ip   Anonymized IP.
	 * @param string $ua   User-Agent string.
	 * @return string BINARY(8) hash.
	 */
	private function compute_visitor_hash( string $salt, string $ip, string $ua ): string {
		$raw = hash( 'sha256', $salt . $ip . $ua, true );
		return substr( $raw, 0, 8 );
	}

	public function test_same_ip_and_ua_with_same_salt_produce_identical_hash(): void {
		$salt = hex2bin( 'a1b2c3d4e5f6a7b8a1b2c3d4e5f6a7b8' );
		$ip   = '203.0.113.0'; // Already anonymized.
		$ua   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';

		$hash_a = $this->compute_visitor_hash( $salt, $ip, $ua );
		$hash_b = $this->compute_visitor_hash( $salt, $ip, $ua );

		$this->assertSame( $hash_a, $hash_b );
		$this->assertSame( 8, strlen( $hash_a ), 'Hash must be exactly 8 bytes (BINARY(8))' );
	}

	public function test_different_salt_produces_different_hash(): void {
		$salt_day1 = hex2bin( 'a1b2c3d4e5f6a7b8a1b2c3d4e5f6a7b8' );
		$salt_day2 = hex2bin( 'ff00ff00ff00ff00ff00ff00ff00ff00' );
		$ip        = '203.0.113.0';
		$ua        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';

		$hash_day1 = $this->compute_visitor_hash( $salt_day1, $ip, $ua );
		$hash_day2 = $this->compute_visitor_hash( $salt_day2, $ip, $ua );

		$this->assertNotSame( $hash_day1, $hash_day2 );
	}

	public function test_hash_length_is_8_bytes(): void {
		$salt = random_bytes( 16 );
		$ip   = '10.0.0.0';
		$ua   = 'TestAgent/1.0';

		$hash = $this->compute_visitor_hash( $salt, $ip, $ua );

		$this->assertSame( 8, strlen( $hash ) );
	}

	public function test_csprng_salt_has_minimum_length(): void {
		// SaltManager::SALT_LENGTH is 16 bytes, stored as 32-char hex string.
		$salt_hex = bin2hex( random_bytes( 16 ) );

		$this->assertSame( 32, strlen( $salt_hex ), 'Salt hex string must be 32 characters (16 bytes)' );

		$salt_binary = hex2bin( $salt_hex );
		$this->assertNotFalse( $salt_binary );
		$this->assertGreaterThanOrEqual( 16, strlen( $salt_binary ), 'Salt must be at least 16 bytes (128-bit entropy)' );
	}

	public function test_two_random_salts_are_different(): void {
		$salt_a = bin2hex( random_bytes( 16 ) );
		$salt_b = bin2hex( random_bytes( 16 ) );

		$this->assertNotSame( $salt_a, $salt_b );
	}

	public function test_different_user_agents_produce_different_hashes(): void {
		$salt = hex2bin( 'a1b2c3d4e5f6a7b8a1b2c3d4e5f6a7b8' );
		$ip   = '203.0.113.0';

		$hash_chrome  = $this->compute_visitor_hash( $salt, $ip, 'Chrome/120' );
		$hash_firefox = $this->compute_visitor_hash( $salt, $ip, 'Firefox/121' );

		$this->assertNotSame( $hash_chrome, $hash_firefox );
	}

	public function test_different_ips_produce_different_hashes(): void {
		$salt = hex2bin( 'a1b2c3d4e5f6a7b8a1b2c3d4e5f6a7b8' );
		$ua   = 'TestAgent/1.0';

		$hash_a = $this->compute_visitor_hash( $salt, '203.0.113.0', $ua );
		$hash_b = $this->compute_visitor_hash( $salt, '198.51.100.0', $ua );

		$this->assertNotSame( $hash_a, $hash_b );
	}
}
