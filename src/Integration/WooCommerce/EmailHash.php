<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer-email hashing for first-purchase detection.
 *
 * Uses a per-site pepper (seeded once by the v1.0.0 migration) so the
 * same email always hashes to the same value within a site, but the
 * hash is unrecoverable without DB access. The pepper is NEVER rotated.
 *
 * The hash is the only customer identifier Statnive stores. Raw emails
 * are never written. The pepper option is autoload=no and never exposed
 * via REST.
 */
final class EmailHash {

	private const PEPPER_OPTION = 'statnive_email_pepper';

	/**
	 * Hash an email for storage in `statnive_orders.customer_email_hash`.
	 *
	 * Returns null for empty / unparseable emails so callers don't store
	 * a "hash of nothing" by accident.
	 *
	 * @param string $email Raw billing email; lowercased + trimmed before hashing.
	 */
	public static function hash( string $email ): ?string {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return null;
		}
		$pepper = (string) get_option( self::PEPPER_OPTION, '' );
		if ( '' === $pepper ) {
			return null;
		}
		return hash( 'sha256', $email . '|' . $pepper );
	}
}
