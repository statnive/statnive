<?php

declare(strict_types=1);

namespace Statnive\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;

/**
 * Shared payload-validation helpers for public tracking endpoints.
 *
 * Centralises the four checks that every tracking entry point performs
 * before touching the persistence pipeline:
 *
 *  1. Content-Type is text/plain or application/json (tracker uses text/plain
 *     to avoid CORS preflight — P-12).
 *  2. Request body is within MAX_BODY_BYTES.
 *  3. Body is a JSON object (associative array after decode).
 *  4. Top-level keys are drawn from a per-endpoint allow-list.
 *
 * Each helper returns either:
 *  - null on success, or
 *  - an error tuple: [string $code, string $message, int $status_code].
 *
 * Callers translate the tuple into WP_REST_Response (REST) or
 * wp_send_json_error (AJAX).
 */
final class PayloadValidator {

	/**
	 * Maximum accepted request body size in bytes.
	 *
	 * Tracking beacons are small (~1 KB typical); 8 KB is well above normal
	 * and prevents resource-exhaustion abuse on public endpoints.
	 *
	 * @var int
	 */
	public const MAX_BODY_BYTES = 8192;

	/**
	 * Accepted Content-Type values. Tracker uses text/plain to avoid CORS
	 * preflight; application/json is also accepted for curl/manual testing.
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_CONTENT_TYPES = [ 'text/plain', 'application/json' ];

	/**
	 * Validate the Content-Type of a REST request.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return array{0: string, 1: string, 2: int}|null Error tuple or null on success.
	 */
	public static function validate_content_type( WP_REST_Request $request ): ?array {
		$content_type = $request->get_content_type();
		$ct_value     = is_array( $content_type ) ? ( $content_type['value'] ?? '' ) : '';

		if ( ! is_string( $ct_value ) || ! in_array( $ct_value, self::ALLOWED_CONTENT_TYPES, true ) ) {
			return [
				'unsupported_media_type',
				'Content-Type must be text/plain or application/json.',
				415,
			];
		}

		return null;
	}

	/**
	 * Validate a raw Content-Type string (for AJAX contexts where no
	 * WP_REST_Request is available).
	 *
	 * @param string $content_type Raw Content-Type header value.
	 * @return array{0: string, 1: string, 2: int}|null Error tuple or null on success.
	 */
	public static function validate_content_type_string( string $content_type ): ?array {
		// Strip parameters such as "; charset=utf-8".
		$token = strtok( $content_type, ';' );
		$base  = strtolower( trim( false === $token ? '' : $token ) );

		if ( ! in_array( $base, self::ALLOWED_CONTENT_TYPES, true ) ) {
			return [
				'unsupported_media_type',
				'Content-Type must be text/plain or application/json.',
				415,
			];
		}

		return null;
	}

	/**
	 * Validate the raw body length.
	 *
	 * @param string $body Raw request body.
	 * @return array{0: string, 1: string, 2: int}|null Error tuple or null on success.
	 */
	public static function validate_body_size( string $body ): ?array {
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return [
				'payload_too_large',
				'Request body exceeds maximum size.',
				413,
			];
		}

		return null;
	}

	/**
	 * Decode a JSON object body into an associative array.
	 *
	 * @param string $body Raw request body.
	 * @return array<string, mixed> Decoded payload.
	 * @throws PayloadValidatorException When the body is not a JSON object.
	 */
	public static function decode_json_object( string $body ): array {
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			throw new PayloadValidatorException(
				'invalid_payload',
				'Invalid request body.',
				400
			);
		}

		return $data;
	}

	/**
	 * Validate that every top-level key in the payload is allow-listed.
	 *
	 * @param array<string, mixed> $data    Decoded payload.
	 * @param array<int, string>   $allowed Allow-listed top-level keys.
	 * @return array{0: string, 1: string, 2: int}|null Error tuple or null on success.
	 */
	public static function validate_allowed_keys( array $data, array $allowed ): ?array {
		$unknown = array_diff( array_keys( $data ), $allowed );

		if ( ! empty( $unknown ) ) {
			return [
				'invalid_payload',
				'Unknown fields in request.',
				400,
			];
		}

		return null;
	}
}
