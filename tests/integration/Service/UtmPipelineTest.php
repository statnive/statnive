<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Api\UtmController;
use Statnive\Container\AnalyticsServiceProvider;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Entity\VisitorProfile;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

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
 * @covers \Statnive\Api\UtmController
 */
final class UtmPipelineTest extends WP_UnitTestCase {

	private const TEST_PAGE_URL = '/sample-page/';

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
	}

	public function test_full_pipeline_persists_utm_rows_with_session_and_view_ids(): void {
		global $wpdb;

		$profile = $this->build_utm_profile(
			'utm_source=marketingplatform.google.com&utm_medium=et&utm_campaign=marketingplatform.google.com',
			'203.0.113.42'
		);

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
		$profile = $this->build_utm_profile(
			'utm_source=marketingplatform.google.com&utm_medium=et',
			'203.0.113.42'
		);

		// Drive enrichment directly so we don't depend on persist() for this assertion.
		AnalyticsServiceProvider::enrich_profile( $profile );

		$this->assertNotSame(
			'Direct',
			$profile->get( 'referrer_channel' ),
			'Direct visits with utm_source must not fall through to the Direct bucket.'
		);
		$this->assertSame( 'marketingplatform.google.com', $profile->get( 'referrer_domain' ) );
	}

	/**
	 * Build a VisitorProfile populated with the boilerplate fields every UTM
	 * pipeline test needs. Caller decides whether to drive `enrich()`,
	 * `AnalyticsServiceProvider::enrich_profile()`, or assert pre-persist state.
	 */
	private function build_utm_profile( string $page_query, string $ip ): VisitorProfile {
		$profile = new VisitorProfile();
		$profile->set( 'resource_type', 'post' );
		$profile->set( 'resource_id', 1 );
		$profile->set( 'page_url', self::TEST_PAGE_URL );
		$profile->set( 'page_query', $page_query );
		$profile->set( 'referrer', '' );
		$profile->set( 'ip', $ip );
		$profile->set( 'user_agent', 'Mozilla/5.0 PipelineTest' );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'user_id', 0 );

		return $profile;
	}

	/**
	 * Drive a UTM-tagged hit through the full pipeline and return the
	 * resulting session_id so callers can correlate persisted rows.
	 */
	private function track_utm_hit( string $page_query, string $ip ): int {
		$profile = $this->build_utm_profile( $page_query, $ip );
		$profile->enrich();

		return (int) $profile->get( 'session_id', 0 );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function call_utm_endpoint( int $limit = 20 ): array {
		$today = gmdate( 'Y-m-d' );

		$request = new WP_REST_Request( 'GET', '/statnive/v1/utm' );
		$request->set_param( 'from', $today );
		$request->set_param( 'to', $today );
		$request->set_param( 'limit', $limit );

		$response = ( new UtmController() )->get_items( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'UTM endpoint must return an array.' );

		return $data;
	}

	public function test_utm_campaigns_endpoint_aggregates_across_sessions(): void {
		// Three distinct visitors hit the same campaign tuple. The endpoint
		// must collapse them into one row with visitors=3, sessions=3 — not
		// emit one row per session as the previous GROUP BY p.session_id did.
		$query = 'utm_source=foo&utm_medium=cpc&utm_campaign=launch';

		$session_ids = [
			$this->track_utm_hit( $query, '203.0.113.10' ),
			$this->track_utm_hit( $query, '203.0.113.11' ),
			$this->track_utm_hit( $query, '203.0.113.12' ),
		];

		foreach ( $session_ids as $sid ) {
			$this->assertGreaterThan( 0, $sid );
		}
		$this->assertSame( 3, count( array_unique( $session_ids ) ), 'Each hit must produce a distinct session.' );

		$rows = $this->call_utm_endpoint();

		$this->assertCount( 1, $rows, 'Three sessions sharing one campaign tuple must collapse to one row.' );
		$this->assertSame( 'launch', $rows[0]['campaign'] );
		$this->assertSame( 'foo', $rows[0]['source'] );
		$this->assertSame( 'cpc', $rows[0]['medium'] );
		$this->assertSame( 3, (int) $rows[0]['visitors'] );
		$this->assertSame( 3, (int) $rows[0]['sessions'] );
	}

	public function test_utm_campaigns_endpoint_aggregates_distinct_tuples(): void {
		// Two visitors share campaign A; one visitor lands on campaign B.
		// Endpoint must return two rows, ordered by visitors DESC.
		$this->track_utm_hit( 'utm_source=newsletter&utm_medium=email&utm_campaign=spring',  '203.0.113.20' );
		$this->track_utm_hit( 'utm_source=newsletter&utm_medium=email&utm_campaign=spring',  '203.0.113.21' );
		$this->track_utm_hit( 'utm_source=twitter&utm_medium=social&utm_campaign=launch',    '203.0.113.22' );

		$rows = $this->call_utm_endpoint();

		$this->assertCount( 2, $rows );
		$this->assertSame( 'spring', $rows[0]['campaign'], 'Higher-visitor campaign must come first.' );
		$this->assertSame( 2, (int) $rows[0]['visitors'] );
		$this->assertSame( 2, (int) $rows[0]['sessions'] );
		$this->assertSame( 'launch', $rows[1]['campaign'] );
		$this->assertSame( 1, (int) $rows[1]['visitors'] );
		$this->assertSame( 1, (int) $rows[1]['sessions'] );
	}
}
