<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Container\AnalyticsServiceProvider;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Entity\VisitorProfile;
use WP_UnitTestCase;

/**
 * Integration test for the end-to-end UTM persistence pipeline.
 *
 * Regression coverage for: visits with UTM params (e.g.
 * `?utm_source=marketingplatform.google.com&utm_medium=et&utm_campaign=...`)
 * appeared in real-time tracking but never landed in `statnive_parameters`,
 * leaving the UTM Campaigns report empty.
 *
 * Root cause was ordering: ParameterService::record() ran inside the
 * `statnive_enrich_profile` action, which fires BEFORE persist(), so
 * `session_id` was always 0 and the insert was skipped.
 *
 * The fix:
 * - `apply_to_profile()` runs during enrichment (no DB writes), mirroring
 *   UTM keys onto the profile so referrer classification can use them.
 * - `record()` runs from a new `statnive_profile_persisted` action fired
 *   at the end of `VisitorProfile::persist()`, when session/view IDs exist.
 *
 * @covers \Statnive\Service\ParameterService
 * @covers \Statnive\Container\AnalyticsServiceProvider
 * @covers \Statnive\Entity\VisitorProfile
 */
final class UtmPipelineTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	public function test_full_pipeline_persists_utm_rows_with_session_and_view_ids(): void {
		global $wpdb;

		$profile = new VisitorProfile();
		$profile->set( 'resource_type', 'post' );
		$profile->set( 'resource_id', 1 );
		$profile->set( 'page_url', '/sample-page/' );
		$profile->set( 'page_query', 'utm_source=marketingplatform.google.com&utm_medium=et&utm_campaign=marketingplatform.google.com' );
		$profile->set( 'referrer', '' );
		$profile->set( 'ip', '203.0.113.42' );
		$profile->set( 'user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36' );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'user_id', 0 );

		// Run the full pipeline: enrichment hook fires apply_to_profile,
		// then persist() runs Visitor → Session → View → fires the new
		// statnive_profile_persisted hook which calls ParameterService::record().
		$profile->enrich();

		$session_id = (int) $profile->get( 'session_id', 0 );
		$view_id    = (int) $profile->get( 'view_id', 0 );

		$this->assertGreaterThan( 0, $session_id, 'Session must be persisted before UTM rows are written.' );
		$this->assertGreaterThan( 0, $view_id, 'View must be persisted before UTM rows are written.' );

		$parameters = TableRegistry::get( 'parameters' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT param_key, param_value, session_id, view_id FROM `{$parameters}` WHERE session_id = %d ORDER BY param_key",
				$session_id
			),
			ARRAY_A
		);

		$this->assertCount( 3, $rows, 'Expected one row per UTM key (source, medium, campaign).' );

		$by_key = [];
		foreach ( $rows as $row ) {
			$by_key[ $row['param_key'] ] = $row;
			$this->assertSame( $session_id, (int) $row['session_id'] );
			$this->assertSame( $view_id, (int) $row['view_id'], 'view_id must be populated, not 0.' );
		}

		$this->assertSame( 'marketingplatform.google.com', $by_key['utm_source']['param_value'] );
		$this->assertSame( 'et', $by_key['utm_medium']['param_value'] );
		$this->assertSame( 'marketingplatform.google.com', $by_key['utm_campaign']['param_value'] );
	}

	public function test_direct_visit_with_utm_source_is_not_classified_as_direct(): void {
		// Reproduces the All Sources symptom: a direct URL hit (no HTTP
		// referrer) carrying utm_source should surface that source instead
		// of being silently bucketed as "Direct".
		$profile = new VisitorProfile();
		$profile->set( 'resource_type', 'post' );
		$profile->set( 'resource_id', 1 );
		$profile->set( 'page_url', '/sample-page/' );
		$profile->set( 'page_query', 'utm_source=marketingplatform.google.com&utm_medium=et' );
		$profile->set( 'referrer', '' );
		$profile->set( 'ip', '203.0.113.42' );
		$profile->set( 'user_agent', 'Mozilla/5.0' );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'user_id', 0 );

		// Drive enrichment directly so we don't depend on persist() for this assertion.
		AnalyticsServiceProvider::enrich_profile( $profile );

		$this->assertNotSame(
			'Direct',
			$profile->get( 'referrer_channel' ),
			'Direct visits with utm_source must not fall through to the Direct bucket.'
		);
		$this->assertSame( 'marketingplatform.google.com', $profile->get( 'referrer_domain' ) );
	}
}
