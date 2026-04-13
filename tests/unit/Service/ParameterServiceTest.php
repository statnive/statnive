<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Entity\VisitorProfile;
use Statnive\Service\ParameterService;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Unit tests for ParameterService UTM extraction + profile mirroring.
 *
 * Regression coverage for the bug where ParameterService::record() ran
 * during enrich() (before persist()), so session_id was always 0 and no
 * UTM rows were ever inserted into statnive_parameters.
 *
 * The pipeline now uses two phases:
 * - apply_to_profile() — pure parse, mirrors UTMs onto the profile so
 *   referrer classification can use them. No DB writes.
 * - record() — DB persistence, called from the post-persist hook.
 */
#[CoversClass(ParameterService::class)]
final class ParameterServiceTest extends TestCase {

	public function test_extract_utm_returns_recognised_keys_only(): void {
		$utm = ParameterService::extract_utm(
			'utm_source=foo&utm_medium=cpc&utm_campaign=bar&utm_term=t&utm_content=c&unrelated=x'
		);

		$this->assertSame(
			[
				'utm_source'   => 'foo',
				'utm_medium'   => 'cpc',
				'utm_campaign' => 'bar',
				'utm_term'     => 't',
				'utm_content'  => 'c',
			],
			$utm
		);
	}

	public function test_extract_utm_returns_empty_array_for_blank_query(): void {
		$this->assertSame( [], ParameterService::extract_utm( '' ) );
	}

	public function test_apply_to_profile_mirrors_all_utm_keys_onto_profile(): void {
		$profile = new VisitorProfile();
		$profile->set( 'page_query', 'utm_source=marketingplatform.google.com&utm_medium=et&utm_campaign=launch' );

		ParameterService::apply_to_profile( $profile );

		$this->assertSame( 'marketingplatform.google.com', $profile->get( 'utm_source' ) );
		$this->assertSame( 'et', $profile->get( 'utm_medium' ) );
		$this->assertSame( 'launch', $profile->get( 'utm_campaign' ) );
	}

	public function test_apply_to_profile_is_safe_without_session_id(): void {
		// The whole point of the split: pre-persist this is called with no
		// session_id / view_id and must not blow up or touch $wpdb.
		$profile = new VisitorProfile();
		$profile->set( 'page_query', 'utm_source=foo&utm_medium=cpc' );

		ParameterService::apply_to_profile( $profile );

		$this->assertNull( $profile->get( 'session_id' ) );
		$this->assertSame( 'foo', $profile->get( 'utm_source' ) );
		$this->assertSame( 'cpc', $profile->get( 'utm_medium' ) );
	}

	public function test_apply_to_profile_is_a_noop_when_query_has_no_utms(): void {
		$profile = new VisitorProfile();
		$profile->set( 'page_query', 'foo=bar&baz=qux' );

		ParameterService::apply_to_profile( $profile );

		$this->assertNull( $profile->get( 'utm_source' ) );
		$this->assertNull( $profile->get( 'utm_medium' ) );
	}

	public function test_record_is_a_noop_when_session_id_is_zero(): void {
		// Defensive: even if the post-persist hook somehow fires before
		// Session::record(), record() must not insert orphaned rows.
		$profile = new VisitorProfile();
		$profile->set( 'page_query', 'utm_source=foo&utm_medium=cpc' );
		$profile->set( 'session_id', 0 );

		// No assertion on $wpdb because it isn't loaded under unit tests —
		// the early return guarantees we never reach the insert path.
		ParameterService::record( $profile );

		$this->assertSame( 0, $profile->get( 'session_id' ) );
	}
}
