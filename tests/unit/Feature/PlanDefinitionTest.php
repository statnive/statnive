<?php
/**
 * Generated from BDD scenarios: features/12-licensing-feature-gates.feature
 * Scenario Outline: "Retention limit enforcement per plan tier" (4 tiers)
 *
 * Tests PlanDefinition retention day limits and tier comparison logic.
 * Pure logic — no WordPress dependencies.
 *
 * May need adjustment when source class API changes.
 */

declare(strict_types=1);

namespace Statnive\Tests\Unit\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Statnive\Feature\PlanDefinition;

#[CoversClass(PlanDefinition::class)]
final class PlanDefinitionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		PlanDefinition::clear_cache();
	}

	#[DataProvider('retention_limit_provider')]
	public function test_retention_limit_per_tier( string $tier, int $expected_days ): void {
		$days = PlanDefinition::get_limit( $tier, 'retention_days' );

		$this->assertSame( $expected_days, $days, sprintf(
			'Tier "%s" should have retention limit of %d days',
			$tier,
			$expected_days
		) );
	}

	/**
	 * BDD Scenario Outline: retention limits per tier.
	 *
	 * Note: The BDD feature file specifies professional=3650 and agency=3650.
	 * These values match the plans.json data file.
	 *
	 * @return array<string, array{string, int}>
	 */
	public static function retention_limit_provider(): array {
		return [
			'free: 30 days'           => [ 'free', 30 ],
			'starter: 365 days'       => [ 'starter', 365 ],
			'professional: 3650 days' => [ 'professional', 3650 ],
			'agency: 3650 days'       => [ 'agency', 3650 ],
		];
	}

	public function test_plan_names_match_display_labels(): void {
		$this->assertSame( 'Free', PlanDefinition::get_plan( 'free' )['name'] );
		$this->assertSame( 'Starter', PlanDefinition::get_plan( 'starter' )['name'] );
		$this->assertSame( 'Professional', PlanDefinition::get_plan( 'professional' )['name'] );
		$this->assertSame( 'Agency', PlanDefinition::get_plan( 'agency' )['name'] );
	}

	public function test_tier_ordering_is_ascending(): void {
		$this->assertLessThan( 0, PlanDefinition::compare_tiers( 'free', 'starter' ) );
		$this->assertLessThan( 0, PlanDefinition::compare_tiers( 'starter', 'professional' ) );
		$this->assertLessThan( 0, PlanDefinition::compare_tiers( 'professional', 'agency' ) );
	}

	public function test_same_tier_comparison_returns_zero(): void {
		$this->assertSame( 0, PlanDefinition::compare_tiers( 'free', 'free' ) );
		$this->assertSame( 0, PlanDefinition::compare_tiers( 'agency', 'agency' ) );
	}

	public function test_higher_tier_comparison_is_positive(): void {
		$this->assertGreaterThan( 0, PlanDefinition::compare_tiers( 'agency', 'free' ) );
	}

	public function test_is_valid_tier_accepts_known_tiers(): void {
		$this->assertTrue( PlanDefinition::is_valid_tier( 'free' ) );
		$this->assertTrue( PlanDefinition::is_valid_tier( 'starter' ) );
		$this->assertTrue( PlanDefinition::is_valid_tier( 'professional' ) );
		$this->assertTrue( PlanDefinition::is_valid_tier( 'agency' ) );
	}

	public function test_is_valid_tier_rejects_unknown_tiers(): void {
		$this->assertFalse( PlanDefinition::is_valid_tier( 'enterprise' ) );
		$this->assertFalse( PlanDefinition::is_valid_tier( '' ) );
	}

	public function test_nonexistent_tier_returns_empty_plan(): void {
		$plan = PlanDefinition::get_plan( 'nonexistent' );

		$this->assertSame( [], $plan );
	}

	public function test_nonexistent_limit_key_returns_zero(): void {
		$limit = PlanDefinition::get_limit( 'free', 'nonexistent_limit' );

		$this->assertSame( 0, $limit );
	}

	public function test_free_tier_has_zero_custom_events(): void {
		$limit = PlanDefinition::get_limit( 'free', 'custom_events' );

		$this->assertSame( 0, $limit );
	}
}
