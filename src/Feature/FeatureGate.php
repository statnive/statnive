<?php

declare(strict_types=1);

namespace Statnive\Feature;

use Statnive\Licensing\LicenseHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature gate service.
 *
 * Central orchestrator that combines PlanDefinition + LicenseHelper
 * to check whether features are available in the current plan.
 */
final class FeatureGate {

	/**
	 * Check if a feature is available in the current plan.
	 *
	 * @param string $feature Feature identifier (e.g., 'woocommerce_revenue').
	 * @return bool True if the feature is available.
	 */
	public static function can( string $feature ): bool {
		$tier = self::current_tier();
		return PlanDefinition::plan_has_feature( $tier, $feature );
	}

	/**
	 * Get a numeric limit for the current plan.
	 *
	 * @param string $limit_key Limit key (e.g., 'custom_events', 'retention_days').
	 * @return int Limit value. -1 means unlimited.
	 */
	public static function get_limit( string $limit_key ): int {
		$tier = self::current_tier();
		return PlanDefinition::get_limit( $tier, $limit_key );
	}

	/**
	 * Get the current plan tier.
	 *
	 * @return string Tier ID (free, starter, professional, agency).
	 */
	public static function current_tier(): string {
		return LicenseHelper::get_current_tier();
	}

	/**
	 * Check if the current tier meets or exceeds a minimum tier.
	 *
	 * @param string $minimum_tier Minimum required tier.
	 * @return bool True if current tier >= minimum.
	 */
	public static function has_tier( string $minimum_tier ): bool {
		return PlanDefinition::compare_tiers( self::current_tier(), $minimum_tier ) >= 0;
	}

	/**
	 * Get full capabilities map for the current plan (for React frontend).
	 *
	 * @return array{tier: string, plan_name: string, features: array<string, bool>, limits: array<string, int>}
	 */
	public static function get_capabilities(): array {
		$tier = self::current_tier();
		$plan = PlanDefinition::get_plan( $tier );

		// Build feature availability map.
		$all_features = [
			'dashboard',
			'basic_sources',
			'geo_data',
			'realtime',
			'device_detection',
			'languages',
			'form_tracking_cf7',
			'custom_events',
			'email_reports',
			'woocommerce_revenue',
			'all_forms',
			'rest_api',
			'wpml',
			'data_plus',
			'advanced_reporting',
			'realtime_stats',
			'marketing',
			'heatmaps',
			'meta_capi',
			'white_label',
			'ai_insights',
		];

		$features = [];
		foreach ( $all_features as $feature ) {
			$features[ $feature ] = PlanDefinition::plan_has_feature( $tier, $feature );
		}

		return [
			'tier'      => $tier,
			'plan_name' => $plan['name'] ?? 'Free',
			'features'  => $features,
			'limits'    => [
				'retention_days' => PlanDefinition::get_limit( $tier, 'retention_days' ),
				'custom_events'  => PlanDefinition::get_limit( $tier, 'custom_events' ),
			],
		];
	}
}
