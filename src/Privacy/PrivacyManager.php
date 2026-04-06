<?php

declare(strict_types=1);

namespace Statnive\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central privacy decision service.
 *
 * Reads privacy settings from wp_options and evaluates whether
 * a request should be tracked based on consent mode, DNT, and GPC headers.
 *
 * The four configuration getters memoise their values per request so that
 * repeated calls during a single tracking hit do not hit the options cache
 * more than once. Call {@see self::reset_cache()} from test setUp() and
 * from activation/deactivation flows to clear the memoisation.
 */
final class PrivacyManager {

	/**
	 * Per-request cache for the consent mode string.
	 *
	 * @var string|null
	 */
	private static $cached_consent_mode = null;

	/**
	 * Per-request cache for the DNT respect flag.
	 *
	 * @var bool|null
	 */
	private static $cached_respect_dnt = null;

	/**
	 * Per-request cache for the GPC respect flag.
	 *
	 * @var bool|null
	 */
	private static $cached_respect_gpc = null;

	/**
	 * Per-request cache for the tracking-enabled flag.
	 *
	 * @var bool|null
	 */
	private static $cached_tracking_enabled = null;

	/**
	 * Get the current consent mode.
	 *
	 * @return string One of ConsentMode constants.
	 */
	public static function get_consent_mode(): string {
		if ( null === self::$cached_consent_mode ) {
			$mode                      = get_option( 'statnive_consent_mode', ConsentMode::COOKIELESS );
			self::$cached_consent_mode = ConsentMode::is_valid( $mode ) ? $mode : ConsentMode::COOKIELESS;
		}

		return self::$cached_consent_mode;
	}

	/**
	 * Check if DNT header should be respected.
	 *
	 * @return bool True if DNT should be honored.
	 */
	public static function should_respect_dnt(): bool {
		if ( null === self::$cached_respect_dnt ) {
			self::$cached_respect_dnt = (bool) get_option( 'statnive_respect_dnt', true );
		}

		return self::$cached_respect_dnt;
	}

	/**
	 * Check if GPC header should be respected.
	 *
	 * @return bool True if GPC should be honored.
	 */
	public static function should_respect_gpc(): bool {
		if ( null === self::$cached_respect_gpc ) {
			self::$cached_respect_gpc = (bool) get_option( 'statnive_respect_gpc', true );
		}

		return self::$cached_respect_gpc;
	}

	/**
	 * Check if tracking is globally enabled.
	 *
	 * @return bool True if tracking is enabled.
	 */
	public static function is_tracking_enabled(): bool {
		if ( null === self::$cached_tracking_enabled ) {
			self::$cached_tracking_enabled = (bool) get_option( 'statnive_tracking_enabled', true );
		}

		return self::$cached_tracking_enabled;
	}

	/**
	 * Clear the per-request memoisation.
	 *
	 * Call from unit-test setUp(), from activation/deactivation hooks, and
	 * any time the underlying options are updated within the same request.
	 */
	public static function reset_cache(): void {
		self::$cached_consent_mode     = null;
		self::$cached_respect_dnt      = null;
		self::$cached_respect_gpc      = null;
		self::$cached_tracking_enabled = null;
	}

	/**
	 * Evaluate whether a request should be tracked.
	 *
	 * Checks: tracking enabled → DNT → GPC → consent mode.
	 *
	 * @param array<string, string> $server_vars Subset of $_SERVER (HTTP_DNT, HTTP_SEC_GPC).
	 * @param bool                  $consent_granted Whether consent was granted via banner.
	 * @return PrivacyDecision
	 */
	public static function check_request_privacy( array $server_vars, bool $consent_granted = false ): PrivacyDecision {
		$mode = self::get_consent_mode();

		// Global kill switch.
		if ( ! self::is_tracking_enabled() ) {
			return PrivacyDecision::block( 'tracking_disabled', $mode );
		}

		// DNT header check.
		if ( self::should_respect_dnt() && ! empty( $server_vars['HTTP_DNT'] ) && '1' === $server_vars['HTTP_DNT'] ) {
			return PrivacyDecision::block( 'dnt', $mode );
		}

		// GPC header check.
		if ( self::should_respect_gpc() && ! empty( $server_vars['HTTP_SEC_GPC'] ) && '1' === $server_vars['HTTP_SEC_GPC'] ) {
			return PrivacyDecision::block( 'gpc', $mode );
		}

		// Consent mode logic.
		$behaviors = ConsentMode::behaviors( $mode );

		if ( ! $behaviors['allows_tracking'] && ! $consent_granted ) {
			return PrivacyDecision::block( 'consent_required', $mode );
		}

		return PrivacyDecision::allow( $mode );
	}

	/**
	 * Get the data retention configuration.
	 *
	 * @return array{days: int, mode: string}
	 */
	public static function get_retention_config(): array {
		return [
			'days' => (int) get_option( 'statnive_retention_days', 90 ),
			'mode' => get_option( 'statnive_retention_mode', 'delete' ),
		];
	}
}
