<?php

declare(strict_types=1);

namespace Statnive\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consent mode definitions and behavior flags.
 *
 * Three modes control how tracking behaves:
 * - FULL: Track everything (requires explicit opt-in if consent banners used).
 * - COOKIELESS: Track without cookies (default, privacy-safe for most jurisdictions).
 * - DISABLED_UNTIL_CONSENT: No tracking until user grants consent via banner.
 */
final class ConsentMode {

	public const FULL                   = 'full';
	public const COOKIELESS             = 'cookieless';
	public const DISABLED_UNTIL_CONSENT = 'disabled-until-consent';

	/**
	 * Valid consent mode values.
	 *
	 * @var string[]
	 */
	public const VALID_MODES = [
		self::FULL,
		self::COOKIELESS,
		self::DISABLED_UNTIL_CONSENT,
	];

	/**
	 * Get behavior flags for a consent mode.
	 *
	 * @param string $mode Consent mode.
	 * @return array{allows_tracking: bool, requires_consent_signal: bool, allows_geo: bool, allows_device: bool}
	 */
	public static function behaviors( string $mode ): array {
		return match ( $mode ) {
			self::FULL => [
				'allows_tracking'         => true,
				'requires_consent_signal' => false,
				'allows_geo'              => true,
				'allows_device'           => true,
			],
			self::COOKIELESS => [
				'allows_tracking'         => true,
				'requires_consent_signal' => false,
				'allows_geo'              => true,
				'allows_device'           => true,
			],
			self::DISABLED_UNTIL_CONSENT => [
				'allows_tracking'         => false,
				'requires_consent_signal' => true,
				'allows_geo'              => true,
				'allows_device'           => true,
			],
			default => [
				'allows_tracking'         => true,
				'requires_consent_signal' => false,
				'allows_geo'              => true,
				'allows_device'           => true,
			],
		};
	}

	/**
	 * Check if a mode value is valid.
	 *
	 * @param string $mode Mode to check.
	 * @return bool True if valid.
	 */
	public static function is_valid( string $mode ): bool {
		return in_array( $mode, self::VALID_MODES, true );
	}
}
