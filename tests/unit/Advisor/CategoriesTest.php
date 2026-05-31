<?php

declare(strict_types=1);

namespace Statnive\Tests\unit\Advisor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Advisor\Categories;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

#[CoversClass( Categories::class )]
final class CategoriesTest extends TestCase {

	public function test_ordered_ids_has_ten_entries(): void {
		$this->assertCount( 10, Categories::ordered_ids() );
	}

	public function test_ordered_ids_is_unique(): void {
		$ids = Categories::ordered_ids();
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
	}

	public function test_labels_en_covers_every_ordered_id(): void {
		$labels = Categories::labels_en();
		foreach ( Categories::ordered_ids() as $id ) {
			$this->assertArrayHasKey( $id, $labels, "Missing English label for category {$id}" );
			$this->assertNotEmpty( $labels[ $id ] );
		}
	}

	public function test_all_returns_shape_with_label_and_label_en(): void {
		$cats = Categories::all();
		$this->assertCount( 10, $cats );
		foreach ( $cats as $cat ) {
			$this->assertArrayHasKey( 'id', $cat );
			$this->assertArrayHasKey( 'label', $cat );
			$this->assertArrayHasKey( 'label_en', $cat );
			$this->assertIsString( $cat['id'] );
			$this->assertIsString( $cat['label'] );
			$this->assertIsString( $cat['label_en'] );
		}
	}

	public function test_all_preserves_ordered_ids_sequence(): void {
		$ordered = Categories::ordered_ids();
		$all     = Categories::all();
		foreach ( $ordered as $i => $expected_id ) {
			$this->assertSame( $expected_id, $all[ $i ]['id'] );
		}
	}

	public function test_label_en_round_trips_through_all(): void {
		$en_labels = Categories::labels_en();
		foreach ( Categories::all() as $row ) {
			$this->assertSame( $en_labels[ $row['id'] ], $row['label_en'] );
		}
	}
}
