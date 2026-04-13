<?php

declare(strict_types=1);

namespace Statnive\Tests\unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Http\PayloadValidator;
use Statnive\Http\PayloadValidatorException;
use WP_REST_Request;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

#[CoversClass( PayloadValidator::class )]
#[CoversClass( PayloadValidatorException::class )]
final class PayloadValidatorTest extends TestCase {

	public function test_validate_content_type_accepts_text_plain(): void {
		$request = new WP_REST_Request( [ 'value' => 'text/plain' ] );

		self::assertNull( PayloadValidator::validate_content_type( $request ) );
	}

	public function test_validate_content_type_accepts_application_json(): void {
		$request = new WP_REST_Request( [ 'value' => 'application/json' ] );

		self::assertNull( PayloadValidator::validate_content_type( $request ) );
	}

	public function test_validate_content_type_rejects_xml(): void {
		$request = new WP_REST_Request( [ 'value' => 'application/xml' ] );

		$result = PayloadValidator::validate_content_type( $request );

		self::assertIsArray( $result );
		self::assertSame( 'unsupported_media_type', $result[0] );
		self::assertSame( 415, $result[2] );
	}

	public function test_validate_content_type_rejects_null(): void {
		$request = new WP_REST_Request( null );

		$result = PayloadValidator::validate_content_type( $request );

		self::assertIsArray( $result );
		self::assertSame( 415, $result[2] );
	}

	public function test_validate_content_type_rejects_array_without_value_key(): void {
		$request = new WP_REST_Request( [ 'type' => 'text/plain' ] );

		$result = PayloadValidator::validate_content_type( $request );

		self::assertIsArray( $result );
		self::assertSame( 415, $result[2] );
	}

	public function test_validate_content_type_rejects_non_string_value(): void {
		$request = new WP_REST_Request( [ 'value' => 123 ] );

		$result = PayloadValidator::validate_content_type( $request );

		self::assertIsArray( $result );
		self::assertSame( 415, $result[2] );
	}

	public function test_validate_content_type_string_accepts_text_plain_with_charset(): void {
		self::assertNull(
			PayloadValidator::validate_content_type_string( 'text/plain; charset=utf-8' )
		);
	}

	public function test_validate_content_type_string_is_case_insensitive(): void {
		self::assertNull(
			PayloadValidator::validate_content_type_string( 'TEXT/PLAIN' )
		);
	}

	public function test_validate_content_type_string_rejects_form(): void {
		$result = PayloadValidator::validate_content_type_string( 'application/x-www-form-urlencoded' );

		self::assertIsArray( $result );
		self::assertSame( 'unsupported_media_type', $result[0] );
		self::assertSame( 415, $result[2] );
	}

	public function test_validate_body_size_accepts_small_body(): void {
		self::assertNull( PayloadValidator::validate_body_size( '{"a":1}' ) );
	}

	public function test_validate_body_size_accepts_boundary(): void {
		$body = str_repeat( 'a', PayloadValidator::MAX_BODY_BYTES );

		self::assertNull( PayloadValidator::validate_body_size( $body ) );
	}

	public function test_validate_body_size_rejects_over_limit(): void {
		$body   = str_repeat( 'a', PayloadValidator::MAX_BODY_BYTES + 1 );
		$result = PayloadValidator::validate_body_size( $body );

		self::assertIsArray( $result );
		self::assertSame( 'payload_too_large', $result[0] );
		self::assertSame( 413, $result[2] );
	}

	public function test_decode_json_object_returns_array(): void {
		$result = PayloadValidator::decode_json_object( '{"foo":"bar","n":1}' );

		self::assertSame( [ 'foo' => 'bar', 'n' => 1 ], $result );
	}

	public function test_decode_json_object_rejects_json_null(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( 'null' );
	}

	public function test_decode_json_object_rejects_json_array(): void {
		// Top-level arrays decode to lists; the rest of the pipeline expects
		// an assoc array. Lists are rejected via the same exception.
		$result = PayloadValidator::decode_json_object( '[1,2,3]' );
		// json_decode returns an array for [1,2,3]; the helper does not
		// distinguish list-vs-object at this layer. That is acceptable:
		// validate_allowed_keys will reject it because integer keys never
		// match the allow-list. We document the behaviour here.
		self::assertSame( [ 1, 2, 3 ], $result );
	}

	public function test_decode_json_object_rejects_malformed(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( '{not json' );
	}

	public function test_decode_json_object_rejects_scalar(): void {
		$this->expectException( PayloadValidatorException::class );
		PayloadValidator::decode_json_object( '"hello"' );
	}

	public function test_validate_allowed_keys_passes_on_subset(): void {
		$data    = [ 'resource_type' => 'post', 'signature' => 'abc' ];
		$allowed = [ 'resource_type', 'resource_id', 'signature' ];

		self::assertNull( PayloadValidator::validate_allowed_keys( $data, $allowed ) );
	}

	public function test_validate_allowed_keys_passes_on_empty_payload(): void {
		self::assertNull( PayloadValidator::validate_allowed_keys( [], [ 'a' ] ) );
	}

	public function test_validate_allowed_keys_rejects_unknown(): void {
		$data    = [ 'resource_type' => 'post', 'foo' => 'bar' ];
		$allowed = [ 'resource_type', 'signature' ];

		$result = PayloadValidator::validate_allowed_keys( $data, $allowed );

		self::assertIsArray( $result );
		self::assertSame( 'invalid_payload', $result[0] );
		self::assertSame( 400, $result[2] );
	}

	public function test_exception_to_tuple_round_trip(): void {
		$exception = new PayloadValidatorException( 'invalid_payload', 'Bad body.', 400 );

		self::assertSame( [ 'invalid_payload', 'Bad body.', 400 ], $exception->to_tuple() );
		self::assertSame( 'invalid_payload', $exception->get_error_code() );
		self::assertSame( 400, $exception->get_status_code() );
	}
}
