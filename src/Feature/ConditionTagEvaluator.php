<?php

declare(strict_types=1);

namespace Statnive\Feature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Condition tag evaluator for UI-level feature gating.
 *
 * Evaluates condition strings like:
 * - "feature:woocommerce_revenue" — checks if feature is enabled
 * - "tier:>=professional" — checks if tier meets minimum
 * - "limit:custom_events>0" — checks if limit is above threshold
 */
final class ConditionTagEvaluator {

	/**
	 * Evaluate a condition string.
	 *
	 * @param string $condition Condition string to evaluate.
	 * @return bool True if condition is met.
	 */
	public static function evaluate( string $condition ): bool {
		$parts = explode( ':', $condition, 2 );
		if ( count( $parts ) < 2 ) {
			return false;
		}

		$type  = $parts[0];
		$value = $parts[1];

		return match ( $type ) {
			'feature' => FeatureGate::can( $value ),
			'tier'    => self::evaluate_tier( $value ),
			'limit'   => self::evaluate_limit( $value ),
			default   => false,
		};
	}

	/**
	 * Evaluate a tier condition (e.g., ">=professional", "==starter").
	 *
	 * @param string $expression Tier comparison expression.
	 * @return bool True if condition is met.
	 */
	private static function evaluate_tier( string $expression ): bool {
		$current = FeatureGate::current_tier();

		// Parse operator and tier.
		if ( str_starts_with( $expression, '>=' ) ) {
			$target = substr( $expression, 2 );
			return PlanDefinition::compare_tiers( $current, $target ) >= 0;
		}

		if ( str_starts_with( $expression, '>' ) ) {
			$target = substr( $expression, 1 );
			return PlanDefinition::compare_tiers( $current, $target ) > 0;
		}

		if ( str_starts_with( $expression, '==' ) ) {
			$target = substr( $expression, 2 );
			return $current === $target;
		}

		// Default: exact match.
		return $current === $expression;
	}

	/**
	 * Evaluate a limit condition (e.g., "custom_events>0", "retention_days>=365").
	 *
	 * @param string $expression Limit comparison expression.
	 * @return bool True if condition is met.
	 */
	private static function evaluate_limit( string $expression ): bool {
		// Parse: "key>value" or "key>=value".
		if ( preg_match( '/^(\w+)(>=|>|==)(-?\d+)$/', $expression, $matches ) ) {
			$key      = $matches[1];
			$operator = $matches[2];
			$target   = (int) $matches[3];
			$actual   = FeatureGate::get_limit( $key );

			// -1 means unlimited — always passes positive comparisons.
			if ( -1 === $actual && $target >= 0 ) {
				return true;
			}

			return match ( $operator ) {
				'>='    => $actual >= $target,
				'>'     => $actual > $target,
				'=='    => $actual === $target,
				default => false,
			};
		}

		return false;
	}
}
