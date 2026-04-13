<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Http\PayloadValidator;
use Statnive\Http\PayloadValidatorException;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Edge-case tests for PayloadValidator.
 *
 * Exercises boundary conditions not covered by the main PayloadValidatorTest:
 *  - Body size at exact boundary (MAX_BODY_BYTES and MAX_BODY_BYTES + 1)
 *  - Empty body handling
 *  - Malformed JSON body handling
 *  - Unicode byte-counting correctness
 */
#[CoversClass(PayloadValidator::class)]
#[CoversClass(PayloadValidatorException::class)]
final class PayloadValidatorEdgeCasesTest extends TestCase {

	// ── Body size boundary tests ──

	public function test_body_exactly_at_max_bytes_passes(): void {
		$body = str_repeat( 'x', PayloadValidator::MAX_BODY_BYTES );

		self::assertSame( PayloadValidator::MAX_BODY_BYTES, strlen( $body ) );
		self::assertNull( PayloadValidator::validate_body_size( $body ) );
	}

	public function test_body_one_byte_over_max_fails(): void {
		$body = str_repeat( 'x', PayloadValidator::MAX_BODY_BYTES + 1 );

		self::assertSame( PayloadValidator::MAX_BODY_BYTES + 1, strlen( $body ) );

		$result = PayloadValidator::validate_body_size( $body );

		self::assertIsArray( $result );
		self::assertSame( 'payload_too_large', $result[0] );
		self::assertSame( 413, $result[2] );
	}

	public function test_max_body_bytes_constant_is_8192(): void {
		self::assertSame( 8192, PayloadValidator::MAX_BODY_BYTES );
	}

	// ── Empty body handling ──

	public function test_empty_body_passes_size_validation(): void {
		self::assertNull( PayloadValidator::validate_body_size( '' ) );
	}

	public function test_empty_body_fails_json_decode(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( '' );
	}

	public function test_empty_body_exception_has_correct_error_code(): void {
		try {
			PayloadValidator::decode_json_object( '' );
			self::fail( 'Expected PayloadValidatorException was not thrown.' );
		} catch ( PayloadValidatorException $e ) {
			self::assertSame( 'invalid_payload', $e->get_error_code() );
			self::assertSame( 400, $e->get_status_code() );
		}
	}

	// ── Malformed JSON body handling ──

	public function test_malformed_json_missing_closing_brace(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( '{"key": "value"' );
	}

	public function test_malformed_json_trailing_comma(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( '{"key": "value",}' );
	}

	public function test_malformed_json_single_quotes(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( "{'key': 'value'}" );
	}

	public function test_malformed_json_bare_string(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( 'just a string' );
	}

	public function test_malformed_json_numeric_string(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( '42' );
	}

	public function test_malformed_json_boolean(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( 'true' );
	}

	// ── Unicode byte-counting ──

	public function test_unicode_body_counts_bytes_not_characters(): void {
		// Each emoji is 4 bytes in UTF-8.
		// 2048 emoji = 8192 bytes = MAX_BODY_BYTES exactly.
		$emoji = "\xF0\x9F\x98\x80"; // U+1F600 (grinning face), 4 bytes.
		$body  = str_repeat( $emoji, 2048 );

		self::assertSame( 8192, strlen( $body ), 'strlen must count bytes, not characters' );
		self::assertNull( PayloadValidator::validate_body_size( $body ) );
	}

	public function test_unicode_body_one_byte_over_max_fails(): void {
		// 2048 emoji (8192 bytes) + 1 ASCII character = 8193 bytes.
		$emoji = "\xF0\x9F\x98\x80";
		$body  = str_repeat( $emoji, 2048 ) . 'x';

		self::assertSame( 8193, strlen( $body ) );

		$result = PayloadValidator::validate_body_size( $body );

		self::assertIsArray( $result );
		self::assertSame( 'payload_too_large', $result[0] );
		self::assertSame( 413, $result[2] );
	}

	public function test_multibyte_json_body_counts_bytes_correctly(): void {
		// Build a JSON object with multibyte characters that stays under the limit.
		// Each CJK character is 3 bytes in UTF-8.
		// Key "v" = 1 byte, JSON overhead {"v":""} = 8 bytes.
		// 8192 - 8 = 8184 bytes available for the value.
		// 2728 CJK chars * 3 bytes = 8184 bytes.
		$cjk  = "\xe4\xb8\xad"; // U+4E2D (中), 3 bytes.
		$body = '{"v":"' . str_repeat( $cjk, 2728 ) . '"}';

		self::assertSame( 8192, strlen( $body ), 'JSON body should be exactly at the byte limit' );
		self::assertNull( PayloadValidator::validate_body_size( $body ) );
	}

	// ── Valid JSON edge cases that should succeed ──

	public function test_valid_json_empty_object_decodes(): void {
		$result = PayloadValidator::decode_json_object( '{}' );

		self::assertSame( [], $result );
	}

	public function test_valid_json_with_nested_objects_decodes(): void {
		$body   = '{"a":{"b":{"c":1}}}';
		$result = PayloadValidator::decode_json_object( $body );

		self::assertIsArray( $result );
		self::assertArrayHasKey( 'a', $result );
	}

	public function test_valid_json_with_unicode_values_decodes(): void {
		$body   = '{"name":"\u4e2d\u6587"}';
		$result = PayloadValidator::decode_json_object( $body );

		self::assertIsArray( $result );
		self::assertSame( '中文', $result['name'] );
	}
}
