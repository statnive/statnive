<?php

declare(strict_types=1);

namespace Statnive\Feature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plan definition loader.
 *
 * Reads the JSON plan definitions file and provides lookup methods
 * for checking feature availability and limits per tier.
 */
final class PlanDefinition {

	/**
	 * Cached plan data.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $plans = null;

	/**
	 * Tier ordering for comparison operations.
	 *
	 * @var array<string, int>
	 */
	private const TIER_ORDER = [
		'free'         => 0,
		'starter'      => 1,
		'professional' => 2,
		'agency'       => 3,
	];

	/**
	 * Get all plan definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all_plans(): array {
		self::load();
		return self::$plans ?? [];
	}

	/**
	 * Get a single plan definition.
	 *
	 * @param string $tier Plan tier ID (free, starter, professional, agency).
	 * @return array<string, mixed> Plan data, or empty array if not found.
	 */
	public static function get_plan( string $tier ): array {
		self::load();
		return self::$plans[ $tier ] ?? [];
	}

	/**
	 * Check if a plan tier includes a specific feature.
	 *
	 * @param string $tier    Plan tier ID.
	 * @param string $feature Feature identifier.
	 * @return bool True if the plan includes the feature.
	 */
	public static function plan_has_feature( string $tier, string $feature ): bool {
		$plan = self::get_plan( $tier );
		if ( empty( $plan ) ) {
			return false;
		}

		$features = $plan['features'] ?? [];

		// Agency wildcard: "*" means all features.
		if ( in_array( '*', $features, true ) ) {
			return true;
		}

		return in_array( $feature, $features, true );
	}

	/**
	 * Get a numeric limit for a plan tier.
	 *
	 * @param string $tier      Plan tier ID.
	 * @param string $limit_key Limit key (e.g., 'retention_days', 'custom_events').
	 * @return int Limit value. -1 means unlimited.
	 */
	public static function get_limit( string $tier, string $limit_key ): int {
		$plan = self::get_plan( $tier );
		return (int) ( $plan['limits'][ $limit_key ] ?? 0 );
	}

	/**
	 * Compare two tiers. Returns negative if $a < $b, 0 if equal, positive if $a > $b.
	 *
	 * @param string $a First tier.
	 * @param string $b Second tier.
	 * @return int Comparison result.
	 */
	public static function compare_tiers( string $a, string $b ): int {
		$order_a = self::TIER_ORDER[ $a ] ?? -1;
		$order_b = self::TIER_ORDER[ $b ] ?? -1;
		return $order_a - $order_b;
	}

	/**
	 * Check if a tier is valid.
	 *
	 * @param string $tier Tier to validate.
	 * @return bool True if valid.
	 */
	public static function is_valid_tier( string $tier ): bool {
		return isset( self::TIER_ORDER[ $tier ] );
	}

	/**
	 * Load plan definitions from JSON file.
	 */
	private static function load(): void {
		if ( null !== self::$plans ) {
			return;
		}

		$path = dirname( __DIR__ ) . '/Data/plans.json';
		if ( ! file_exists( $path ) ) {
			self::$plans = [];
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $path );
		if ( false === $json ) {
			self::$plans = [];
			return;
		}

		$decoded     = json_decode( $json, true );
		self::$plans = is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Clear cached plans (for testing).
	 */
	public static function clear_cache(): void {
		self::$plans = null;
	}
}
