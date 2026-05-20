<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Integration\WooCommerce;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Integration\WooCommerce\BackfillService;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 7 ) . '/' );

/**
 * Unit tests for {@see BackfillService}.
 *
 * Scope: state-machine + permission gates only. The Action Scheduler
 * chunk processing and SQL paths exercise WC + $wpdb and live in the
 * integration suite. WC is never active in the unit environment
 * (class_exists('WooCommerce') === false), so start() returns 404 there
 * unless we stub Detector::is_active. We exercise the other branches
 * via direct option-state manipulation.
 */
#[CoversClass(BackfillService::class)]
final class BackfillServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_options']    = [];
		$GLOBALS['statnive_test_transients'] = [];
	}

	public function test_read_state_returns_idle_defaults_when_option_missing(): void {
		$state = BackfillService::read_state();

		$this->assertSame( BackfillService::STATUS_IDLE, $state['status'] );
		$this->assertSame( 0, $state['total'] );
		$this->assertSame( 0, $state['processed'] );
		$this->assertSame( 0, $state['refunds'] );
		$this->assertNull( $state['started_at'] );
		$this->assertNull( $state['finished_at'] );
		$this->assertNull( $state['last_error'] );
	}

	public function test_read_state_normalises_a_pre_existing_row(): void {
		$GLOBALS['statnive_test_options']['statnive_wc_backfill_state'] = [
			'status'      => BackfillService::STATUS_RUNNING,
			'total'       => '1000',
			'processed'   => '250',
			'refunds'     => '7',
			'started_at'  => '2026-05-20T00:00:00Z',
			'finished_at' => null,
			'last_error'  => null,
		];

		$state = BackfillService::read_state();

		$this->assertSame( BackfillService::STATUS_RUNNING, $state['status'] );
		$this->assertSame( 1000, $state['total'] );
		$this->assertSame( 250, $state['processed'] );
		$this->assertSame( 7, $state['refunds'] );
		$this->assertSame( '2026-05-20T00:00:00Z', $state['started_at'] );
	}

	public function test_process_order_ids_returns_zeros_on_empty_input(): void {
		$result = BackfillService::process_order_ids( [] );

		$this->assertSame( [ 'processed' => 0, 'refunds' => 0 ], $result );
	}

	public function test_process_order_ids_skips_non_positive_ids(): void {
		// Without wc_get_order() stubbed, the loop body short-circuits
		// past the WC reads. The Recorder::on_paid_or_paying call is a
		// no-op when Detector::is_active() is false (which it is in
		// the unit environment).
		$result = BackfillService::process_order_ids( [ 0, -1 ] );

		$this->assertSame( [ 'processed' => 0, 'refunds' => 0 ], $result );
	}

	public function test_start_returns_404_when_woocommerce_inactive(): void {
		// WooCommerce class is not loaded in the unit environment, so
		// Detector::is_active() returns false.
		$result = BackfillService::start();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 404, $result['http_status'] );
		$this->assertSame( 'woocommerce_inactive', $result['reason'] );
		// State must remain idle since we never began.
		$this->assertSame( BackfillService::STATUS_IDLE, $result['state']['status'] );
	}

	public function test_auto_start_if_needed_short_circuits_when_woocommerce_inactive(): void {
		// Should not write any option, should not schedule anything.
		BackfillService::auto_start_if_needed();

		$this->assertArrayNotHasKey(
			'statnive_wc_backfill_state',
			$GLOBALS['statnive_test_options']
		);
	}

	public function test_auto_start_if_needed_short_circuits_when_state_not_idle(): void {
		// Even if WC were active, a non-idle state must block auto-start.
		$GLOBALS['statnive_test_options']['statnive_wc_backfill_state'] = [
			'status'    => BackfillService::STATUS_RUNNING,
			'total'     => 1000,
			'processed' => 500,
		];

		BackfillService::auto_start_if_needed();

		$this->assertSame(
			BackfillService::STATUS_RUNNING,
			$GLOBALS['statnive_test_options']['statnive_wc_backfill_state']['status'],
			'State should be unchanged when auto_start short-circuits.'
		);
	}

	public function test_invalidate_gap_transient_removes_cache_entry(): void {
		$GLOBALS['statnive_test_transients']['statnive_wc_backfill_gap'] = [
			'has_gap'            => true,
			'orders_in_wc'       => 12,
			'orders_in_statnive' => 5,
		];

		BackfillService::invalidate_gap_transient();

		$this->assertArrayNotHasKey(
			'statnive_wc_backfill_gap',
			$GLOBALS['statnive_test_transients']
		);
	}

	public function test_status_payload_round_trips_through_state(): void {
		$payload = BackfillService::status_payload();

		$this->assertArrayHasKey( 'has_gap', $payload );
		$this->assertArrayHasKey( 'orders_in_wc', $payload );
		$this->assertArrayHasKey( 'orders_in_statnive', $payload );
		$this->assertArrayHasKey( 'action_scheduler_available', $payload );
		$this->assertArrayHasKey( 'state', $payload );
		$this->assertSame( BackfillService::STATUS_IDLE, $payload['state']['status'] );
	}
}
