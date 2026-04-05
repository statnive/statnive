<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily rotating two-salt system for privacy-safe visitor hashing.
 *
 * Maintains two salts (current + previous) with a 48-hour overlap window
 * to prevent visitor double-counting during rotation boundaries.
 *
 * Uses CSPRNG via random_bytes() — never wp_generate_password().
 */
final class SaltManager {

	/**
	 * Salt length in bytes (128-bit entropy).
	 *
	 * @var int
	 */
	private const SALT_LENGTH = 16;

	/**
	 * Option keys.
	 *
	 * @var string
	 */
	private const OPT_CURRENT    = 'statnive_salt_current';
	private const OPT_PREVIOUS   = 'statnive_salt_previous';
	private const OPT_ROTATED_AT = 'statnive_salt_rotated_at';

	/**
	 * Get the current salt for visitor hashing.
	 *
	 * Initializes salts on first call if none exist.
	 *
	 * @return string Binary salt (16 bytes).
	 */
	public static function get_current_salt(): string {
		$salt = get_option( self::OPT_CURRENT, '' );

		if ( empty( $salt ) ) {
			self::initialize();
			$salt = get_option( self::OPT_CURRENT, '' );
		}

		// Salts are stored as hex strings in wp_options for safe serialization.
		$binary = hex2bin( $salt );
		return ( false !== $binary ) ? $binary : random_bytes( self::SALT_LENGTH );
	}

	/**
	 * Get the previous salt for overlap-window session matching.
	 *
	 * @return string|null Binary salt, or null if no previous salt exists.
	 */
	public static function get_previous_salt(): ?string {
		$salt = get_option( self::OPT_PREVIOUS, '' );

		if ( empty( $salt ) ) {
			return null;
		}

		$binary = hex2bin( $salt );
		return ( false !== $binary ) ? $binary : null;
	}

	/**
	 * Check if salt rotation is needed (older than 24 hours).
	 *
	 * @return bool True if rotation is needed.
	 */
	public static function should_rotate(): bool {
		$rotated_at = get_option( self::OPT_ROTATED_AT, '' );

		if ( empty( $rotated_at ) ) {
			return true;
		}

		$last_rotation = strtotime( $rotated_at );
		if ( false === $last_rotation ) {
			return true;
		}

		// Rotate if more than 24 hours have passed.
		return ( time() - $last_rotation ) >= DAY_IN_SECONDS;
	}

	/**
	 * Rotate salts: current becomes previous, new salt is generated.
	 */
	public static function rotate(): void {
		$current = get_option( self::OPT_CURRENT, '' );

		// Move current to previous (48h overlap window).
		if ( ! empty( $current ) ) {
			update_option( self::OPT_PREVIOUS, $current, false );
		}

		// Generate new current salt via CSPRNG.
		$new_salt = bin2hex( random_bytes( self::SALT_LENGTH ) );
		update_option( self::OPT_CURRENT, $new_salt, false );
		update_option( self::OPT_ROTATED_AT, gmdate( 'Y-m-d H:i:s' ), false );
	}

	/**
	 * Initialize salts for first use.
	 */
	private static function initialize(): void {
		$current  = bin2hex( random_bytes( self::SALT_LENGTH ) );
		$previous = bin2hex( random_bytes( self::SALT_LENGTH ) );

		add_option( self::OPT_CURRENT, $current, '', false );
		add_option( self::OPT_PREVIOUS, $previous, '', false );
		add_option( self::OPT_ROTATED_AT, gmdate( 'Y-m-d H:i:s' ), '', false );
	}

	/**
	 * Get the rotation timestamp.
	 *
	 * @return string|null ISO datetime of last rotation, or null.
	 */
	public static function get_rotated_at(): ?string {
		$val = get_option( self::OPT_ROTATED_AT, '' );
		return ! empty( $val ) ? $val : null;
	}
}
