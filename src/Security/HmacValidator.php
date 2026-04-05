<?php

declare(strict_types=1);

namespace Statnive\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HMAC-SHA256 request signature validation.
 *
 * Prevents request spoofing by signing tracker payloads with a server-side secret.
 * Uses constant-time comparison to prevent timing attacks.
 */
final class HmacValidator {

	/**
	 * Option key for the HMAC secret.
	 *
	 * @var string
	 */
	private const SECRET_OPTION = 'statnive_hmac_secret';

	/**
	 * Generate an HMAC signature for a resource.
	 *
	 * @param string $resource_type Resource type (e.g., 'post', 'page').
	 * @param int    $resource_id   Resource ID.
	 * @return string Hex-encoded HMAC-SHA256 signature.
	 */
	public static function generate( string $resource_type, int $resource_id ): string {
		$secret  = self::get_secret();
		$message = $resource_type . '|' . $resource_id;

		return hash_hmac( 'sha256', $message, $secret );
	}

	/**
	 * Verify an HMAC signature against expected values.
	 *
	 * Uses hash_equals() for constant-time comparison (S-06).
	 *
	 * @param string $signature     The signature to verify.
	 * @param string $resource_type Resource type.
	 * @param int    $resource_id   Resource ID.
	 * @return bool True if signature is valid.
	 */
	public static function verify( string $signature, string $resource_type, int $resource_id ): bool {
		$expected = self::generate( $resource_type, $resource_id );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Get or create the HMAC secret.
	 *
	 * @return string The HMAC secret key.
	 */
	public static function get_secret(): string {
		$secret = get_option( self::SECRET_OPTION, '' );

		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( self::SECRET_OPTION, $secret, false );
		}

		return $secret;
	}

	/**
	 * Rotate the HMAC secret.
	 *
	 * Generates a new secret. Existing signed requests will become invalid.
	 *
	 * @return string The new secret.
	 */
	public static function rotate_secret(): string {
		$secret = wp_generate_password( 64, true, true );
		update_option( self::SECRET_OPTION, $secret, false );
		return $secret;
	}
}
