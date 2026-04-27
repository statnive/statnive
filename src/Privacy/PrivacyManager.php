<?php

declare(strict_types=1);

namespace Statnive\Privacy;

use Statnive\Service\ExclusionMatcher;

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
		ExclusionMatcher::reset_cache();
	}

	/**
	 * Evaluate whether a request should be tracked.
	 *
	 * Checks (in priority order): tracking enabled → GPC (primary) → DNT (legacy
	 * fallback) → consent mode → final filter.
	 *
	 * GPC (`Sec-GPC: 1`) is the W3C-track Global Privacy Control signal and is
	 * the primary opt-out we honour. DNT (`DNT: 1`) is checked second for
	 * legacy compatibility — it has no W3C standard status and is treated as
	 * a best-effort fallback.
	 *
	 * @param array<string, string> $server_vars Subset of $_SERVER (HTTP_SEC_GPC, HTTP_DNT).
	 * @param bool                  $consent_granted Whether consent was granted via banner.
	 * @param string                $client_ip       Extracted client IP for exclusion matching. Skipped when empty.
	 * @return PrivacyDecision
	 */
	public static function check_request_privacy( array $server_vars, bool $consent_granted = false, string $client_ip = '' ): PrivacyDecision {
		$mode = self::get_consent_mode();

		// Global kill switch.
		if ( ! self::is_tracking_enabled() ) {
			return PrivacyDecision::block( 'tracking_disabled', $mode );
		}

		// Excluded IPs / CIDR ranges — admin-configured block list.
		if ( '' !== $client_ip && ExclusionMatcher::is_excluded_ip( $client_ip ) ) {
			return PrivacyDecision::block( 'excluded_ip', $mode );
		}

		// Excluded user roles (logged-in visitors only).
		if ( ExclusionMatcher::is_excluded_role() ) {
			return PrivacyDecision::block( 'excluded_role', $mode );
		}

		/**
		 * Filter whether GPC (Global Privacy Control) should be honored for this request.
		 *
		 * GPC is the primary opt-out signal — checked before DNT.
		 * Defaults to the `statnive_respect_gpc` option (true).
		 *
		 * @param bool                  $respect     Whether GPC should be honored.
		 * @param array<string, string> $server_vars Subset of $_SERVER (HTTP_SEC_GPC, HTTP_DNT).
		 */
		$respect_gpc = (bool) apply_filters( 'statnive_respect_gpc', self::should_respect_gpc(), $server_vars );

		// GPC header check (primary signal).
		if ( $respect_gpc && ! empty( $server_vars['HTTP_SEC_GPC'] ) && '1' === $server_vars['HTTP_SEC_GPC'] ) {
			return PrivacyDecision::block( 'gpc', $mode );
		}

		/**
		 * Filter whether DNT (Do Not Track) should be honored for this request.
		 *
		 * DNT is treated as a legacy fallback after GPC. It has no W3C
		 * standard status; honour it for backward compatibility but do not
		 * rely on it for compliance — prefer GPC.
		 * Defaults to the `statnive_respect_dnt` option (true).
		 *
		 * @param bool                  $respect     Whether DNT should be honored.
		 * @param array<string, string> $server_vars Subset of $_SERVER (HTTP_SEC_GPC, HTTP_DNT).
		 */
		$respect_dnt = (bool) apply_filters( 'statnive_respect_dnt', self::should_respect_dnt(), $server_vars );

		// DNT header check (legacy fallback).
		if ( $respect_dnt && ! empty( $server_vars['HTTP_DNT'] ) && '1' === $server_vars['HTTP_DNT'] ) {
			return PrivacyDecision::block( 'dnt', $mode );
		}

		// Consent mode logic.
		$behaviors = ConsentMode::behaviors( $mode );

		/**
		 * Filter whether the current consent mode requires explicit visitor consent
		 * before tracking is allowed.
		 *
		 * @param bool   $required Whether consent is required.
		 * @param string $mode     Active consent mode (full, cookieless, disabled-until-consent).
		 */
		$consent_required = (bool) apply_filters( 'statnive_require_consent', ! $behaviors['allows_tracking'], $mode );

		/**
		 * Filter whether the current visitor has granted consent.
		 *
		 * @param bool                  $has_consent Whether consent has been granted.
		 * @param array<string, string> $server_vars Subset of $_SERVER (HTTP_DNT, HTTP_SEC_GPC).
		 * @param string                $mode        Active consent mode.
		 */
		$has_consent = (bool) apply_filters( 'statnive_has_visitor_consent', $consent_granted, $server_vars, $mode );

		if ( $consent_required && ! $has_consent ) {
			return PrivacyDecision::block( 'consent_required', $mode );
		}

		/**
		 * Filter the final tracking decision before it is returned.
		 *
		 * Themes/plugins can use this to short-circuit tracking based on
		 * arbitrary conditions (e.g., role, geography, custom opt-out).
		 *
		 * @param bool                  $should_track Whether tracking should proceed.
		 * @param array<string, string> $server_vars  Subset of $_SERVER (HTTP_DNT, HTTP_SEC_GPC).
		 * @param string                $mode         Active consent mode.
		 */
		$should_track = (bool) apply_filters( 'statnive_should_track', true, $server_vars, $mode );

		if ( ! $should_track ) {
			return PrivacyDecision::block( 'filter_blocked', $mode );
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
			'days' => (int) get_option( 'statnive_retention_days', 3650 ),
			'mode' => get_option( 'statnive_retention_mode', 'forever' ),
		];
	}
}
