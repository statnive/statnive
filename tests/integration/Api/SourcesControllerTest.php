<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Api;

use Statnive\Api\SourcesController;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Service\DimensionService;
use WP_REST_Request;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the SourcesController REST endpoint.
 *
 * Covers the v0.4.5 mis-grouping bug where multiple Google ccTLDs
 * (`www.google.com`, `www.google.de`, `gemini.google.com`) rendered as
 * separate "Google" rows because the SQL grouped by `r.domain`.
 *
 * @covers \Statnive\Api\SourcesController
 */
final class SourcesControllerTest extends WP_UnitTestCase {

	private SourcesController $controller;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		DimensionService::clear_cache();

		$this->controller = new SourcesController();

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Insert one session for a referrer with the given (channel, name, domain).
	 *
	 * Each call creates one visitor + one session linked to a freshly resolved
	 * referrer row. Used to seed the (channel, name) aggregation tests.
	 */
	private function insert_session_for_referrer( string $channel, string $name, string $domain, string $datetime ): void {
		global $wpdb;

		$visitors_table = TableRegistry::get( 'visitors' );
		$sessions_table = TableRegistry::get( 'sessions' );

		$referrer_id = DimensionService::resolve_referrer( $channel, $name, $domain );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $visitors_table, [
			'hash'       => random_bytes( 8 ),
			'created_at' => $datetime,
		] );
		$visitor_id = (int) $wpdb->insert_id;

		$wpdb->insert( $sessions_table, [
			'visitor_id'  => $visitor_id,
			'started_at'  => $datetime,
			'total_views' => 1,
			'duration'    => 30,
			'referrer_id' => $referrer_id,
		] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	private function build_request( string $from, string $to, string $group_by = 'channel' ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/statnive/v1/sources' );
		$request->set_param( 'from', $from );
		$request->set_param( 'to', $to );
		$request->set_param( 'group_by', $group_by );
		$request->set_param( 'per_channel', 10 );
		$request->set_param( 'limit', 20 );
		return $request;
	}

	/**
	 * @testdox Multiple Google ccTLDs collapse into one Organic Search → Google row
	 */
	public function test_multiple_google_cctlds_aggregate_into_one_row(): void {
		$today = gmdate( 'Y-m-d' );
		$at    = $today . ' 14:00:00';

		// Three distinct domains, all classified as Google by the write path.
		$this->insert_session_for_referrer( 'Organic Search', 'Google', 'www.google.com', $at );
		$this->insert_session_for_referrer( 'Organic Search', 'Google', 'www.google.de', $at );
		$this->insert_session_for_referrer( 'Organic Search', 'Google', 'gemini.google.com', $at );

		$response = $this->controller->get_items( $this->build_request( $today, $today ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		$organic = $this->find_channel( $data, 'Organic Search' );
		$this->assertNotNull( $organic, 'Organic Search channel group must be present' );
		$this->assertSame( 3, (int) $organic['visitors'], 'Channel-level visitors must aggregate the three sessions' );

		$google_rows = array_values( array_filter( $organic['sources'], fn( $s ) => $s['name'] === 'Google' ) );
		$this->assertCount( 1, $google_rows, 'All three Google ccTLDs must collapse into one source row' );
		$this->assertSame( 3, (int) $google_rows[0]['visitors'], 'The merged Google row must sum visitors across ccTLDs' );
	}

	/**
	 * @testdox Different brands within the same channel stay separate
	 */
	public function test_different_brands_in_same_channel_stay_separate(): void {
		$today = gmdate( 'Y-m-d' );
		$at    = $today . ' 14:00:00';

		$this->insert_session_for_referrer( 'Organic Search', 'Google', 'www.google.com', $at );
		$this->insert_session_for_referrer( 'Organic Search', 'Bing', 'www.bing.com', $at );
		$this->insert_session_for_referrer( 'Organic Search', 'DuckDuckGo', 'duckduckgo.com', $at );

		$response = $this->controller->get_items( $this->build_request( $today, $today ) );
		$organic  = $this->find_channel( $response->get_data(), 'Organic Search' );

		$this->assertNotNull( $organic );
		$names = array_column( $organic['sources'], 'name' );
		sort( $names );
		$this->assertSame( [ 'Bing', 'DuckDuckGo', 'Google' ], $names, 'Each distinct brand must remain a separate row' );
	}

	/**
	 * @testdox Ungrouped flat-list endpoint also collapses by (channel, name)
	 */
	public function test_flat_endpoint_collapses_by_channel_and_name(): void {
		$today = gmdate( 'Y-m-d' );
		$at    = $today . ' 14:00:00';

		$this->insert_session_for_referrer( 'Organic Search', 'Google', 'www.google.com', $at );
		$this->insert_session_for_referrer( 'Organic Search', 'Google', 'www.google.de', $at );

		$request  = $this->build_request( $today, $today, '' ); // no group_by → flat list
		$response = $this->controller->get_items( $request );
		$rows     = $response->get_data();

		$google_rows = array_values( array_filter(
			$rows,
			fn( $r ) => ( $r['channel'] ?? '' ) === 'Organic Search' && ( $r['name'] ?? '' ) === 'Google'
		) );
		$this->assertCount( 1, $google_rows, 'Flat-list endpoint must also collapse Google ccTLDs into one row' );
		$this->assertSame( 2, (int) $google_rows[0]['visitors'] );
	}

	/**
	 * @param array<int, array<string, mixed>> $data
	 * @return array<string, mixed>|null
	 */
	private function find_channel( array $data, string $channel ): ?array {
		foreach ( $data as $row ) {
			if ( ( $row['channel'] ?? '' ) === $channel ) {
				return $row;
			}
		}
		return null;
	}
}
