<?php

declare(strict_types=1);

namespace Statnive\Tests\unit\Advisor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Advisor\UserPreferences;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

#[CoversClass( UserPreferences::class )]
final class UserPreferencesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_user_meta'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['statnive_test_user_meta'] );
		parent::tearDown();
	}

	public function test_default_pinned_returns_five_ids(): void {
		$this->assertCount( 5, UserPreferences::default_pinned() );
	}

	public function test_default_pinned_are_q2_q41_q23_q72_q81(): void {
		$this->assertSame(
			[ 'q2', 'q41', 'q23', 'q72', 'q81' ],
			UserPreferences::default_pinned()
		);
	}

	public function test_get_returns_defaults_when_no_meta(): void {
		$this->assertSame(
			UserPreferences::default_pinned(),
			UserPreferences::get( 7 )
		);
	}

	public function test_set_persists_and_get_reads(): void {
		$stored = UserPreferences::set( 7, [ 'q1', 'q41', 'q42' ] );
		$this->assertSame( [ 'q1', 'q41', 'q42' ], $stored );
		$this->assertSame( [ 'q1', 'q41', 'q42' ], UserPreferences::get( 7 ) );
	}

	public function test_set_drops_unknown_ids(): void {
		$stored = UserPreferences::set( 7, [ 'q1', 'not_a_real_id', 'q41' ] );
		$this->assertSame( [ 'q1', 'q41' ], $stored );
	}

	public function test_set_dedupes_ids(): void {
		$stored = UserPreferences::set( 7, [ 'q1', 'q1', 'q41' ] );
		$this->assertSame( [ 'q1', 'q41' ], $stored );
	}

	public function test_set_enforces_max_pins_cap(): void {
		$over_cap = array_slice( \Statnive\Advisor\Questions::valid_ids(), 0, UserPreferences::MAX_PINS + 5 );
		$stored   = UserPreferences::set( 7, $over_cap );
		$this->assertCount( UserPreferences::MAX_PINS, $stored );
		$this->assertSame( array_slice( $over_cap, 0, UserPreferences::MAX_PINS ), $stored );
	}

	public function test_get_filters_orphan_ids_after_schema_churn(): void {
		// Simulate a stale meta entry containing IDs that no longer exist.
		$GLOBALS['statnive_test_user_meta'][7]['statnive_pinned_questions'] =
			json_encode( [ 'q1', 'q9999', 'q41' ] );
		$this->assertSame( [ 'q1', 'q41' ], UserPreferences::get( 7 ) );
	}

	public function test_get_falls_back_to_defaults_when_filtering_empties_list(): void {
		$GLOBALS['statnive_test_user_meta'][7]['statnive_pinned_questions'] =
			json_encode( [ 'q9999', 'q8888' ] );
		$this->assertSame( UserPreferences::default_pinned(), UserPreferences::get( 7 ) );
	}

	public function test_pin_appends_idempotently(): void {
		// Start below cap so there's room to actually pin a new id.
		UserPreferences::set( 7, [ 'q2', 'q41' ] );
		$first  = UserPreferences::pin( 7, 'q42' );
		$second = UserPreferences::pin( 7, 'q42' );
		$this->assertContains( 'q42', $first );
		$this->assertSame( $first, $second );
	}

	public function test_pin_respects_cap(): void {
		// Fill to max.
		$all_ids = array_slice( \Statnive\Advisor\Questions::valid_ids(), 0, UserPreferences::MAX_PINS );
		UserPreferences::set( 7, $all_ids );

		// Try to pin one more — should still be at MAX_PINS, no-op write.
		$next_id = \Statnive\Advisor\Questions::valid_ids()[ UserPreferences::MAX_PINS ];
		$result  = UserPreferences::pin( 7, $next_id );
		$this->assertCount( UserPreferences::MAX_PINS, $result );
		$this->assertNotContains( $next_id, $result );
	}

	public function test_unpin_removes_id(): void {
		UserPreferences::set( 7, [ 'q1', 'q41', 'q72' ] );
		$result = UserPreferences::unpin( 7, 'q41' );
		$this->assertSame( [ 'q1', 'q72' ], $result );
	}

	public function test_unpin_idempotent_for_missing_id(): void {
		UserPreferences::set( 7, [ 'q1', 'q41' ] );
		$result = UserPreferences::unpin( 7, 'q72' );
		$this->assertSame( [ 'q1', 'q41' ], $result );
	}

	public function test_unpin_to_empty_persists_empty_list_not_defaults(): void {
		UserPreferences::set( 7, [ 'q1' ] );
		$result = UserPreferences::unpin( 7, 'q1' );
		$this->assertSame( [], $result );
		// Subsequent get() must honor the explicit empty list — confirm via
		// raw meta inspection rather than get() since get() falls back to
		// defaults on a JSON-decoded empty array.
		$raw = $GLOBALS['statnive_test_user_meta'][7]['statnive_pinned_questions'];
		$this->assertSame( '[]', $raw );
	}

	public function test_erase_removes_the_meta_key(): void {
		UserPreferences::set( 7, [ 'q1', 'q41' ] );
		UserPreferences::erase( 7 );
		$this->assertArrayNotHasKey( 'statnive_pinned_questions', $GLOBALS['statnive_test_user_meta'][7] ?? [] );
	}

	public function test_per_user_isolation(): void {
		UserPreferences::set( 7, [ 'q1' ] );
		UserPreferences::set( 9, [ 'q41', 'q42' ] );
		$this->assertSame( [ 'q1' ], UserPreferences::get( 7 ) );
		$this->assertSame( [ 'q41', 'q42' ], UserPreferences::get( 9 ) );
	}
}
