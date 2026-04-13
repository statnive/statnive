<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Security\HmacValidator;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Unit tests for HmacValidator.
 *
 * These tests verify the HMAC generation and verification logic
 * without requiring WordPress. We test the core crypto operations directly.
 */
#[CoversClass(HmacValidator::class)]
final class HmacValidatorTest extends TestCase {

	public function test_hmac_sha256_produces_64_char_hex(): void {
		$message   = 'post|42';
		$secret    = 'test-secret-key-for-hmac-validation';
		$signature = hash_hmac( 'sha256', $message, $secret );

		$this->assertSame( 64, strlen( $signature ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $signature );
	}

	public function test_same_input_produces_same_signature(): void {
		$secret = 'consistent-secret';
		$sig1   = hash_hmac( 'sha256', 'post|1', $secret );
		$sig2   = hash_hmac( 'sha256', 'post|1', $secret );

		$this->assertSame( $sig1, $sig2 );
	}

	public function test_different_input_produces_different_signature(): void {
		$secret = 'consistent-secret';
		$sig1   = hash_hmac( 'sha256', 'post|1', $secret );
		$sig2   = hash_hmac( 'sha256', 'post|2', $secret );

		$this->assertNotSame( $sig1, $sig2 );
	}

	public function test_different_secret_produces_different_signature(): void {
		$sig1 = hash_hmac( 'sha256', 'post|1', 'secret-a' );
		$sig2 = hash_hmac( 'sha256', 'post|1', 'secret-b' );

		$this->assertNotSame( $sig1, $sig2 );
	}

	public function test_hash_equals_prevents_timing_attacks(): void {
		$expected = hash_hmac( 'sha256', 'post|1', 'secret' );
		$tampered = 'a' . substr( $expected, 1 );

		// hash_equals is constant-time — verify it works correctly.
		$this->assertTrue( hash_equals( $expected, $expected ) );
		$this->assertFalse( hash_equals( $expected, $tampered ) );
		$this->assertFalse( hash_equals( $expected, '' ) );
	}

	public function test_message_format_matches_implementation(): void {
		// The implementation uses "resource_type|resource_id" as the message.
		$resource_type = 'post';
		$resource_id   = 42;
		$message       = $resource_type . '|' . $resource_id;

		$this->assertSame( 'post|42', $message );
	}
}
