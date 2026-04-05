<?php

declare(strict_types=1);

namespace Statnive\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License key storage and retrieval.
 *
 * Encrypts license keys using sodium_crypto_secretbox (PHP 8.1+ bundled libsodium).
 * Stores an HMAC hash for integrity verification.
 * Caches license status as a transient (7-day TTL).
 */
final class LicenseHelper {

	private const OPT_KEY_ENC   = 'statnive_license_key_enc';
	private const OPT_KEY_NONCE = 'statnive_license_key_nonce';
	private const OPT_KEY_HASH  = 'statnive_license_hash';
	private const TRANSIENT_KEY = 'statnive_license_status';
	private const CACHE_TTL     = 7 * DAY_IN_SECONDS;

	/**
	 * Store a license key (encrypted + HMAC).
	 *
	 * @param string $key Raw license key.
	 */
	public static function store_license( string $key ): void {
		$secret = self::get_encryption_key();
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$encrypted = sodium_crypto_secretbox( $key, $nonce, $secret );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		update_option( self::OPT_KEY_ENC, base64_encode( $encrypted ), false );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		update_option( self::OPT_KEY_NONCE, base64_encode( $nonce ), false );
		update_option( self::OPT_KEY_HASH, hash_hmac( 'sha256', $key, $secret ), false );
	}

	/**
	 * Retrieve and decrypt the stored license key.
	 *
	 * @return string|null Decrypted key, or null if not stored or decryption fails.
	 */
	public static function get_license_key(): ?string {
		$enc_b64   = get_option( self::OPT_KEY_ENC, '' );
		$nonce_b64 = get_option( self::OPT_KEY_NONCE, '' );

		if ( empty( $enc_b64 ) || empty( $nonce_b64 ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$encrypted = base64_decode( $enc_b64, true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$nonce = base64_decode( $nonce_b64, true );

		if ( false === $encrypted || false === $nonce ) {
			return null;
		}

		$secret    = self::get_encryption_key();
		$decrypted = sodium_crypto_secretbox_open( $encrypted, $nonce, $secret );

		return ( false !== $decrypted ) ? $decrypted : null;
	}

	/**
	 * Check if a license key is stored.
	 *
	 * @return bool
	 */
	public static function has_license(): bool {
		return ! empty( get_option( self::OPT_KEY_ENC, '' ) );
	}

	/**
	 * Remove all license data.
	 */
	public static function remove_license(): void {
		delete_option( self::OPT_KEY_ENC );
		delete_option( self::OPT_KEY_NONCE );
		delete_option( self::OPT_KEY_HASH );
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Get the masked license key (last 4 characters).
	 *
	 * @return string Masked key like '****-ABCD', or empty string.
	 */
	public static function get_masked_key(): string {
		$key = self::get_license_key();
		if ( null === $key || strlen( $key ) < 4 ) {
			return '';
		}
		return '****-' . substr( $key, -4 );
	}

	/**
	 * Get cached license status.
	 *
	 * @return LicenseStatus
	 */
	public static function get_cached_status(): LicenseStatus {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( false === $cached || ! is_array( $cached ) ) {
			return self::has_license() ? LicenseStatus::error() : LicenseStatus::free();
		}

		return LicenseStatus::from_array( $cached );
	}

	/**
	 * Cache a license status.
	 *
	 * @param LicenseStatus $status Status to cache.
	 */
	public static function cache_status( LicenseStatus $status ): void {
		set_transient( self::TRANSIENT_KEY, $status->to_array(), self::CACHE_TTL );
	}

	/**
	 * Get the current plan tier from cached status.
	 *
	 * @return string Tier ID (free, starter, professional, agency).
	 */
	public static function get_current_tier(): string {
		return self::get_cached_status()->plan_tier;
	}

	/**
	 * Derive the encryption key from WordPress auth salt.
	 *
	 * @return string 32-byte key for sodium_crypto_secretbox.
	 */
	private static function get_encryption_key(): string {
		$salt = wp_salt( 'auth' );
		return sodium_crypto_generichash( $salt, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
