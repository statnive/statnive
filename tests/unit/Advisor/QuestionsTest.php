<?php

declare(strict_types=1);

namespace Statnive\Tests\unit\Advisor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Advisor\Categories;
use Statnive\Advisor\Questions;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

#[CoversClass( Questions::class )]
final class QuestionsTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['statnive_test_filters']['statnive_advisor_questions'] );
		parent::tearDown();
	}

	public function test_inventory_has_exactly_118_questions(): void {
		// 120 originally; q1 + q5 were folded into the dynamic-window
		// variants q2 + q6 in the May 2026 consolidation.
		$this->assertCount( 118, Questions::all() );
	}

	public function test_every_question_id_is_unique(): void {
		$ids = array_column( Questions::all(), 'id' );
		$this->assertSame(
			$ids,
			array_values( array_unique( $ids ) ),
			'Duplicate question IDs in inventory'
		);
	}

	public function test_every_question_has_required_fields(): void {
		foreach ( Questions::all() as $q ) {
			foreach ( [ 'id', 'category_id', 'question', 'question_en', 'keywords', 'plan', 'surface', 'viz_hint', 'confidence' ] as $key ) {
				$this->assertArrayHasKey( $key, $q, "Question {$q['id']} missing field {$key}" );
			}
		}
	}

	public function test_every_category_id_is_known(): void {
		$known = Categories::ordered_ids();
		foreach ( Questions::all() as $q ) {
			$this->assertContains( $q['category_id'], $known, "Question {$q['id']} has unknown category {$q['category_id']}" );
		}
	}

	public function test_every_plan_is_free_or_paid(): void {
		foreach ( Questions::all() as $q ) {
			$this->assertContains( $q['plan'], [ Questions::PLAN_FREE, Questions::PLAN_PAID ] );
		}
	}

	public function test_every_confidence_is_known_tier(): void {
		foreach ( Questions::all() as $q ) {
			$this->assertContains(
				$q['confidence'],
				[ Questions::CONF_DIRECT, Questions::CONF_CALCULATED, Questions::CONF_PROXY ]
			);
		}
	}

	public function test_keywords_are_non_empty_string_arrays(): void {
		foreach ( Questions::all() as $q ) {
			$this->assertIsArray( $q['keywords'] );
			$this->assertNotEmpty( $q['keywords'] );
			foreach ( $q['keywords'] as $kw ) {
				$this->assertIsString( $kw );
				$this->assertNotSame( '', $kw );
			}
		}
	}

	public function test_question_en_matches_question_when_no_translation(): void {
		// Under the test stub `__()` returns the original string, so for v1
		// the translated and English fields are byte-identical for every Q.
		foreach ( Questions::all() as $q ) {
			$this->assertSame( $q['question'], $q['question_en'], "Q {$q['id']} translated/English mismatch under stub" );
		}
	}

	public function test_with_searchable_attaches_bilingual_searchable_array(): void {
		foreach ( Questions::with_searchable() as $q ) {
			$this->assertArrayHasKey( 'searchable', $q );
			$this->assertArrayHasKey( 'category', $q );
			$this->assertArrayHasKey( 'category_en', $q );

			// Must contain question + category + each keyword.
			$searchable = $q['searchable'];
			$this->assertContains( $q['question_en'], $searchable );
			$this->assertContains( $q['category_en'], $searchable );
			foreach ( $q['keywords'] as $kw ) {
				$this->assertContains( $kw, $searchable );
			}
		}
	}

	public function test_searchable_is_deduplicated(): void {
		foreach ( Questions::with_searchable() as $q ) {
			$this->assertSame(
				array_values( array_unique( $q['searchable'] ) ),
				$q['searchable'],
				"Q {$q['id']} searchable[] has duplicates"
			);
		}
	}

	public function test_valid_ids_returns_118_ids(): void {
		$this->assertCount( 118, Questions::valid_ids() );
	}

	public function test_find_returns_row_for_known_id(): void {
		$q = Questions::find( 'q41' );
		$this->assertNotNull( $q );
		$this->assertSame( 'q41', $q['id'] );
		$this->assertSame( Categories::REFERRERS_AND_CHANNELS, $q['category_id'] );
	}

	public function test_find_returns_null_for_unknown_id(): void {
		$this->assertNull( Questions::find( 'q9999' ) );
	}

	public function test_default_pinned_ids_resolve_to_known_questions(): void {
		// Plan §"Default pinned set" — these 5 must be answerable today.
		foreach ( [ 'q2', 'q41', 'q23', 'q72', 'q81' ] as $id ) {
			$this->assertNotNull( Questions::find( $id ), "Default pinned ID {$id} missing from inventory" );
		}
	}

	public function test_filter_can_inject_synthetic_question(): void {
		// Forward-compat per plan §G.2: future PRs add questions via the
		// `statnive_advisor_questions` filter, no core inventory edit required.
		$GLOBALS['statnive_test_filters']['statnive_advisor_questions'] = static function ( array $rows ): array {
			$rows[] = [
				'id'           => 'q9999',
				'category_id'  => Categories::TRAFFIC_OVERVIEW,
				'category'     => 'Traffic Overview',
				'category_en'  => 'Traffic Overview',
				'question'     => 'synthetic test question',
				'question_en'  => 'synthetic test question',
				'keywords'     => [ 'synthetic' ],
				'plan'         => Questions::PLAN_FREE,
				'surface'      => '/synthetic',
				'viz_hint'     => 'kpi_tile',
				'confidence'   => Questions::CONF_DIRECT,
				'searchable'   => [ 'synthetic' ],
			];
			return $rows;
		};

		$ids = array_column( Questions::with_searchable(), 'id' );
		$this->assertContains( 'q9999', $ids );
		$this->assertContains( 'q9999', array_column( Questions::with_searchable(), 'id' ) );
		// `find()` reads `with_searchable()` so the synthetic Q surfaces there too.
		$this->assertNotNull( Questions::find( 'q9999' ) );
	}

	public function test_schema_gap_questions_carry_depends_on_schema_field(): void {
		// Spot-check: q27 needs entry_count, q29 needs exit_count.
		$q27 = Questions::find( 'q27' );
		$this->assertNotNull( $q27 );
		$this->assertSame( Questions::SCHEMA_ENTRY_COUNT, $q27['depends_on_schema'] ?? null );

		$q29 = Questions::find( 'q29' );
		$this->assertNotNull( $q29 );
		$this->assertSame( Questions::SCHEMA_EXIT_COUNT, $q29['depends_on_schema'] ?? null );
	}

	public function test_revenue_questions_are_all_paid(): void {
		foreach ( Questions::all() as $q ) {
			if ( Categories::REVENUE === $q['category_id'] ) {
				$this->assertSame( Questions::PLAN_PAID, $q['plan'], "Revenue Q {$q['id']} must be Paid" );
			}
		}
	}

	public function test_events_and_privacy_questions_are_all_paid(): void {
		foreach ( Questions::all() as $q ) {
			if ( Categories::EVENTS_AND_PRIVACY === $q['category_id'] ) {
				$this->assertSame( Questions::PLAN_PAID, $q['plan'], "Events Q {$q['id']} must be Paid" );
			}
		}
	}
}
