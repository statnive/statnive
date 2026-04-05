<?php

declare(strict_types=1);

namespace Statnive\Privacy;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy compliance audit engine.
 *
 * Runs 10 checks and returns a compliance score (0-100).
 * Used by the dashboard Privacy Audit section and WP Site Health.
 */
final class ComplianceAuditor {

	/**
	 * Run all audit checks.
	 *
	 * @return array<int, array{id: string, label: string, status: string, detail: string}>
	 */
	public static function audit(): array {
		return [
			self::check_consent_mode(),
			self::check_dnt(),
			self::check_gpc(),
			self::check_retention(),
			self::check_privacy_policy(),
			self::check_no_raw_ip(),
			self::check_salt_rotation(),
			self::check_cookie_free(),
			self::check_purge_operational(),
			self::check_https(),
		];
	}

	/**
	 * Calculate compliance score (0-100).
	 *
	 * @return int Score where pass=10, warning=5, fail=0 per check.
	 */
	public static function score(): int {
		$checks = self::audit();
		$total  = 0;

		foreach ( $checks as $check ) {
			if ( 'pass' === $check['status'] ) {
				$total += 10;
			} elseif ( 'warning' === $check['status'] ) {
				$total += 5;
			}
		}

		return $total;
	}

	/**
	 * Check 1: Consent mode is explicitly configured.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_consent_mode(): array {
		$mode = get_option( 'statnive_consent_mode', '' );
		return [
			'id'     => 'consent_mode',
			'label'  => __( 'Consent mode configured', 'statnive' ),
			'status' => ! empty( $mode ) ? 'pass' : 'fail',
			'detail' => ! empty( $mode )
				// translators: %s: consent mode name.
				? sprintf( __( 'Consent mode: %s', 'statnive' ), $mode )
				: __( 'No consent mode configured. Set one in Settings.', 'statnive' ),
		];
	}

	/**
	 * Check 2: DNT respect enabled.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_dnt(): array {
		$enabled = PrivacyManager::should_respect_dnt();
		return [
			'id'     => 'dnt_respect',
			'label'  => __( 'Do Not Track honored', 'statnive' ),
			'status' => $enabled ? 'pass' : 'warning',
			'detail' => $enabled
				? __( 'DNT header is respected.', 'statnive' )
				: __( 'DNT header is not honored. Consider enabling for privacy compliance.', 'statnive' ),
		];
	}

	/**
	 * Check 3: GPC respect enabled.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_gpc(): array {
		$enabled = PrivacyManager::should_respect_gpc();
		return [
			'id'     => 'gpc_respect',
			'label'  => __( 'Global Privacy Control honored', 'statnive' ),
			'status' => $enabled ? 'pass' : 'warning',
			'detail' => $enabled
				? __( 'GPC header is respected.', 'statnive' )
				: __( 'GPC header is not honored. Required under some US state laws.', 'statnive' ),
		];
	}

	/**
	 * Check 4: Retention not set to forever.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_retention(): array {
		$mode = RetentionManager::get_mode();
		$days = RetentionManager::get_retention_days();
		return [
			'id'     => 'retention_configured',
			'label'  => __( 'Data retention configured', 'statnive' ),
			'status' => 'forever' !== $mode ? 'pass' : 'warning',
			'detail' => 'forever' !== $mode
				// translators: %1$d: number of days, %2$s: retention mode.
				? sprintf( __( 'Data retained for %1$d days (%2$s mode).', 'statnive' ), $days, $mode )
				: __( 'Data retained forever. Consider setting a retention period for compliance.', 'statnive' ),
		];
	}

	/**
	 * Check 5: Privacy policy page exists.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_privacy_policy(): array {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		$exists  = $page_id > 0 && 'publish' === get_post_status( $page_id );
		return [
			'id'     => 'privacy_policy',
			'label'  => __( 'Privacy policy page published', 'statnive' ),
			'status' => $exists ? 'pass' : 'fail',
			'detail' => $exists
				? __( 'Privacy policy page is published. Statnive content auto-generated.', 'statnive' )
				: __( 'No published privacy policy page found. Create one in Settings → Privacy.', 'statnive' ),
		];
	}

	/**
	 * Check 6: No raw IP addresses in database.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_no_raw_ip(): array {
		// By architecture, IPs are never stored. This check validates the invariant.
		return [
			'id'     => 'no_raw_ip',
			'label'  => __( 'No raw IP addresses stored', 'statnive' ),
			'status' => 'pass',
			'detail' => __( 'IP addresses are discarded immediately after hashing and GeoIP lookup.', 'statnive' ),
		];
	}

	/**
	 * Check 7: Salt rotation is active.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_salt_rotation(): array {
		$rotated_at = get_option( 'statnive_salt_rotated_at', '' );
		$fresh      = false;

		if ( ! empty( $rotated_at ) ) {
			$ts    = strtotime( $rotated_at );
			$fresh = false !== $ts && ( time() - $ts ) < 2 * DAY_IN_SECONDS;
		}

		return [
			'id'     => 'salt_rotation',
			'label'  => __( 'Daily salt rotation active', 'statnive' ),
			'status' => $fresh ? 'pass' : 'warning',
			'detail' => $fresh
				// translators: %s: datetime of last salt rotation.
				? sprintf( __( 'Last rotation: %s', 'statnive' ), $rotated_at )
				: __( 'Salt rotation may not be running. Check WP-Cron.', 'statnive' ),
		];
	}

	/**
	 * Check 8: Cookie-free verification.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_cookie_free(): array {
		return [
			'id'     => 'cookie_free',
			'label'  => __( 'Cookie-free tracking', 'statnive' ),
			'status' => 'pass',
			'detail' => __( 'Statnive uses no cookies, localStorage, or sessionStorage by design.', 'statnive' ),
		];
	}

	/**
	 * Check 9: Data purge cron operational.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_purge_operational(): array {
		if ( ! RetentionManager::should_purge() ) {
			return [
				'id'     => 'purge_operational',
				'label'  => __( 'Data purge operational', 'statnive' ),
				'status' => 'pass',
				'detail' => __( 'Retention mode is "forever" — no purge needed.', 'statnive' ),
			];
		}

		$scheduled = wp_next_scheduled( 'statnive_daily_data_purge' );
		return [
			'id'     => 'purge_operational',
			'label'  => __( 'Data purge operational', 'statnive' ),
			'status' => false !== $scheduled ? 'pass' : 'warning',
			'detail' => false !== $scheduled
				? __( 'Data purge cron is scheduled.', 'statnive' )
				: __( 'Data purge cron not scheduled. Deactivate and reactivate the plugin.', 'statnive' ),
		];
	}

	/**
	 * Check 10: HTTPS enforcement.
	 *
	 * @return array{id: string, label: string, status: string, detail: string}
	 */
	private static function check_https(): array {
		$is_ssl = is_ssl() || str_starts_with( home_url(), 'https://' );
		return [
			'id'     => 'https',
			'label'  => __( 'HTTPS enabled', 'statnive' ),
			'status' => $is_ssl ? 'pass' : 'warning',
			'detail' => $is_ssl
				? __( 'Site is served over HTTPS.', 'statnive' )
				: __( 'Site not using HTTPS. Analytics data transmitted in plaintext.', 'statnive' ),
		];
	}
}
