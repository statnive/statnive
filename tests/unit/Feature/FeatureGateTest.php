<?php
/**
 * Generated from BDD scenarios: features/12-licensing-feature-gates.feature
 * Scenario Outline: "Feature gate evaluates access per tier" (9 combinations)
 * Plus: "Free tier blocks WooCommerce", "Agency tier grants all via wildcard"
 *
 * Tests PlanDefinition::plan_has_feature() directly — this is the pure logic
 * behind FeatureGate::can() without requiring LicenseHelper (WordPress options).
 *
 * May need adjustment when source class API changes.
 */

declare(strict_types=1);

namespace Statnive\Tests\Unit\Feature;

use PHPUnit\Framework\TestCase;
use Statnive\Feature\PlanDefinition;

/**
 * @covers \Statnive\Feature\FeatureGate
 * @covers \Statnive\Feature\PlanDefinition
 */
final class FeatureGateTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Ensure fresh plan data on each test.
		PlanDefinition::clear_cache();
	}

	/**
	 * @dataProvider tier_feature_provider
	 */
	public function test_feature_gate_evaluates_access_per_tier( string $tier, string $feature, bool $expected ): void {
		$result = PlanDefinition::plan_has_feature( $tier, $feature );

		$this->assertSame( $expected, $result, sprintf(
			'Tier "%s" should %s feature "%s"',
			$tier,
			$expected ? 'have' : 'not have',
			$feature
		) );
	}

	/**
	 * BDD Scenario Outline examples from feature 12.
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public static function tier_feature_provider(): array {
		return [
			'free: dashboard=true'               => [ 'free', 'dashboard', true ],
			'free: form_tracking_cf7=false'      => [ 'free', 'form_tracking_cf7', false ],
			'free: custom_events=false'          => [ 'free', 'custom_events', false ],
			'starter: form_tracking_cf7=true'    => [ 'starter', 'form_tracking_cf7', true ],
			'starter: woocommerce_revenue=false' => [ 'starter', 'woocommerce_revenue', false ],
			'professional: woocommerce=true'     => [ 'professional', 'woocommerce_revenue', true ],
			'professional: heatmaps=false'       => [ 'professional', 'heatmaps', false ],
			'agency: white_label=true'           => [ 'agency', 'white_label', true ],
			'agency: meta_capi=true'             => [ 'agency', 'meta_capi', true ],
		];
	}

	public function test_free_tier_provides_basic_dashboard_features(): void {
		$basic_features = [ 'dashboard', 'basic_sources', 'geo_data', 'realtime', 'device_detection', 'languages' ];

		foreach ( $basic_features as $feature ) {
			$this->assertTrue(
				PlanDefinition::plan_has_feature( 'free', $feature ),
				sprintf( 'Free tier should include "%s"', $feature )
			);
		}
	}

	public function test_free_tier_blocks_woocommerce_revenue(): void {
		$this->assertFalse( PlanDefinition::plan_has_feature( 'free', 'woocommerce_revenue' ) );
	}

	public function test_starter_tier_grants_form_tracking(): void {
		$this->assertTrue( PlanDefinition::plan_has_feature( 'starter', 'form_tracking_cf7' ) );
	}

	public function test_professional_tier_unlocks_woocommerce(): void {
		$this->assertTrue( PlanDefinition::plan_has_feature( 'professional', 'woocommerce_revenue' ) );
	}

	public function test_agency_tier_grants_all_features_via_wildcard(): void {
		// Agency uses ["*"] which means any feature should return true.
		$this->assertTrue( PlanDefinition::plan_has_feature( 'agency', 'ai_insights' ) );
		$this->assertTrue( PlanDefinition::plan_has_feature( 'agency', 'heatmaps' ) );
		$this->assertTrue( PlanDefinition::plan_has_feature( 'agency', 'white_label' ) );
		$this->assertTrue( PlanDefinition::plan_has_feature( 'agency', 'any_future_feature' ) );
	}

	public function test_starter_custom_events_limit_is_5(): void {
		$limit = PlanDefinition::get_limit( 'starter', 'custom_events' );

		$this->assertSame( 5, $limit );
	}

	public function test_professional_custom_events_unlimited(): void {
		$limit = PlanDefinition::get_limit( 'professional', 'custom_events' );

		$this->assertSame( -1, $limit );
	}

	public function test_invalid_tier_denies_all_features(): void {
		$this->assertFalse( PlanDefinition::plan_has_feature( 'nonexistent', 'dashboard' ) );
	}
}
