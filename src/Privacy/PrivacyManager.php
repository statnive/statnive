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
 */
final class PrivacyManager {

	/**
	 * Get the current consent mode.
	 *
	 * @return string One of ConsentMode constants.
	 */
	public static function get_consent_mode(): string {
		$mode = get_option( 'statnive_consent_mode', ConsentMode::COOKIELESS );
		return ConsentMode::is_valid( $mode ) ? $mode : ConsentMode::COOKIELESS;
	}

	/**
	 * Check if DNT header should be respected.
	 *
	 * @return bool True if DNT should be honored.
	 */
	public static function should_respect_dnt(): bool {
		return (bool) get_option( 'statnive_respect_dnt', true );
	}

	/**
	 * Check if GPC header should be respected.
	 *
	 * @return bool True if GPC should be honored.
	 */
	public static function should_respect_gpc(): bool {
		return (bool) get_option( 'statnive_respect_gpc', true );
	}

	/**
	 * Check if tracking is globally enabled.
	 *
	 * @return bool True if tracking is enabled.
	 */
	public static function is_tracking_enabled(): bool {
		return (bool) get_option( 'statnive_tracking_enabled', true );
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
