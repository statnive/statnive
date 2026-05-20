<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

use Statnive\Cron\CronRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports pre-existing WooCommerce orders into the Statnive tables.
 *
 * The Recorder is forward-only — its hooks only fire on NEW order events.
 * A fresh install on a store with existing orders or a v0.4.x → v1.0.0
 * upgrade therefore leaves Statnive's tables empty. This service closes
 * that gap by chunking through `wc_get_orders()` and routing each order
 * through the same idempotent `Recorder::on_paid_or_paying()` path.
 *
 * The job is auto-started on the first admin pageview where a gap is
 * detected (see `auto_start_if_needed()`). The CLI command at
 * {@see \Statnive\Cli\WooCommerceBackfillCommand} also calls
 * {@see process_order_ids()} so both entry points share one body.
 *
 * Invariants:
 *   - READ-ONLY against WooCommerce (only `wc_get_orders()` + `$order->get_*()`).
 *   - Idempotent on `wc_order_id` via the Recorder's UPSERT.
 *   - Per-chunk Detector guard means WC deactivation mid-run is safe.
 *   - Every Throwable inside a chunk is caught and recorded in the
 *     state option's `last_error` — never re-thrown into Action Scheduler.
 */
final class BackfillService {

	/**
	 * Action Scheduler hook fired once per 500-order chunk.
	 */
	public const HOOK = 'statnive/wc/backfill/chunk';

	/**
	 * Action Scheduler group so the queue UI in WC → Tools → Scheduled
	 * Actions filters cleanly.
	 */
	public const GROUP = 'statnive';

	/**
	 * Default chunk size — same as the CLI default.
	 */
	public const BATCH_SIZE = 500;

	/**
	 * Order statuses counted as "needs to be in statnive_orders".
	 * Matches the CLI default + WC Analytics revenue policy.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_STATUSES = [ 'processing', 'completed', 'refunded' ];

	/**
	 * Option that stores the live backfill state (autoload=no).
	 */
	private const STATE_OPTION = 'statnive_wc_backfill_state';

	/**
	 * Transient that caches the gap-detection result for 5 minutes.
	 * Cleared by Recorder::upsert_order_row() on every successful write.
	 */
	private const GAP_TRANSIENT = 'statnive_wc_backfill_gap';

	/**
	 * Cap on the stored last_error string to keep the option row small.
	 */
	private const LAST_ERROR_CAP = 500;

	/**
	 * Status enum values.
	 */
	public const STATUS_IDLE    = 'idle';
	public const STATUS_PENDING = 'pending';
	public const STATUS_RUNNING = 'running';
	public const STATUS_DONE    = 'done';
	public const STATUS_FAILED  = 'failed';

	/**
	 * Hook into WordPress.
	 *
	 * Called once from {@see \Statnive\Container\WooCommerceServiceProvider}.
	 * The AS callback must be registered on every request so AS can find
	 * it when invoking from its own request context.
	 */
	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'handle_chunk' ], 10, 3 );
		add_action( 'admin_init', [ self::class, 'auto_start_if_needed' ] );
	}

	/**
	 * Auto-start a backfill on the first admin pageview where a gap is
	 * detected and no job is already running.
	 *
	 * Idempotent on concurrent admin tabs: a second pageview that races
	 * past the idle guard still gets a 409 from `start()` because that
	 * method re-checks state under the option's own atomicity.
	 *
	 * Guards (all must pass):
	 *   1. WooCommerce active (Detector::is_active).
	 *   2. Action Scheduler available.
	 *   3. Current state is idle (no in-flight job).
	 *   4. Detection reports has_gap = true.
	 */
	public static function auto_start_if_needed(): void {
		if ( ! Detector::is_active() ) {
			return;
		}
		if ( ! self::has_action_scheduler() ) {
			return;
		}
		$state = self::read_state();
		if ( self::STATUS_IDLE !== $state['status'] ) {
			return;
		}

		$gap = self::detect_gap();
		if ( ! $gap['has_gap'] ) {
			return;
		}

		self::start();
	}

	/**
	 * Schedule a new backfill run.
	 *
	 * @return array{ok: bool, http_status: int, reason?: string, state: array<string, mixed>}
	 */
	public static function start(): array {
		if ( ! Detector::is_active() ) {
			return [
				'ok'          => false,
				'http_status' => 404,
				'reason'      => 'woocommerce_inactive',
				'state'       => self::read_state(),
			];
		}
		if ( ! self::has_action_scheduler() ) {
			$state               = self::read_state();
			$state['status']     = self::STATUS_FAILED;
			$state['last_error'] = 'Action Scheduler is not available on this host.';
			self::write_state( $state );
			return [
				'ok'          => false,
				'http_status' => 503,
				'reason'      => 'action_scheduler_unavailable',
				'state'       => $state,
			];
		}

		$state = self::read_state();
		if ( in_array( $state['status'], [ self::STATUS_PENDING, self::STATUS_RUNNING ], true ) ) {
			return [
				'ok'          => false,
				'http_status' => 409,
				'reason'      => 'backfill_in_progress',
				'state'       => $state,
			];
		}

		$query = self::build_query();
		$total = self::count_orders( $query );

		$now   = gmdate( 'Y-m-d\TH:i:s\Z' );
		$state = [
			'status'      => self::STATUS_PENDING,
			'total'       => $total,
			'processed'   => 0,
			'refunds'     => 0,
			'started_at'  => $now,
			'finished_at' => null,
			'last_error'  => null,
		];
		self::write_state( $state );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, [ 1, self::BATCH_SIZE, $query ], self::GROUP );
		}

		return [
			'ok'          => true,
			'http_status' => 202,
			'state'       => $state,
		];
	}

	/**
	 * Action Scheduler callback — one chunk per invocation.
	 *
	 * @param int                  $page  Pagination index (1-based).
	 * @param int                  $batch Chunk size.
	 * @param array<string, mixed> $query Frozen `wc_get_orders()` args.
	 */
	public static function handle_chunk( int $page, int $batch, array $query ): void {
		try {
			if ( ! Detector::is_active() ) {
				self::mark_failed( 'WooCommerce became inactive during backfill.' );
				return;
			}

			$state = self::read_state();
			if ( self::STATUS_PENDING === $state['status'] || self::STATUS_RUNNING === $state['status'] ) {
				$state['status'] = self::STATUS_RUNNING;
				self::write_state( $state );
			}

			$query['paged']  = $page;
			$query['limit']  = $batch;
			$query['return'] = 'ids';

			if ( ! function_exists( 'wc_get_orders' ) ) {
				self::mark_failed( 'wc_get_orders() is not available.' );
				return;
			}

			$ids = wc_get_orders( $query );
			$ids = is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];

			if ( [] === $ids ) {
				// No more orders → finished.
				$state['status']      = self::STATUS_DONE;
				$state['finished_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
				self::write_state( $state );
				return;
			}

			$result = self::process_order_ids( $ids );

			$state['processed'] = (int) $state['processed'] + (int) $result['processed'];
			$state['refunds']   = (int) $state['refunds'] + (int) $result['refunds'];
			self::write_state( $state );

			if ( count( $ids ) < $batch ) {
				$state['status']      = self::STATUS_DONE;
				$state['finished_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
				self::write_state( $state );
				return;
			}

			// Schedule the next page.
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::HOOK, [ $page + 1, $batch, $query ], self::GROUP );
			}
		} catch ( \Throwable $e ) {
			self::mark_failed( $e->getMessage() );
		}
	}

	/**
	 * Per-order capture loop. Reused by both the CLI and the AS hook.
	 *
	 * @param array<int, int> $ids WooCommerce order IDs.
	 * @return array{processed: int, refunds: int}
	 */
	public static function process_order_ids( array $ids ): array {
		$processed = 0;
		$refunds   = 0;

		foreach ( $ids as $order_id ) {
			$order_id = (int) $order_id;
			if ( $order_id <= 0 ) {
				continue;
			}

			Recorder::on_paid_or_paying( $order_id );

			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order instanceof \WC_Order ) {
					foreach ( $order->get_refunds() as $refund ) {
						if ( method_exists( $refund, 'get_id' ) ) {
							Recorder::on_refund( $order_id, (int) $refund->get_id() );
							++$refunds;
						}
					}
				}
			}

			++$processed;
		}

		return [
			'processed' => $processed,
			'refunds'   => $refunds,
		];
	}

	/**
	 * Build the payload returned under `data.backfill` on /wc-status.
	 *
	 * @return array<string, mixed>
	 */
	public static function status_payload(): array {
		$gap = self::detect_gap();
		return [
			'has_gap'                    => (bool) $gap['has_gap'],
			'orders_in_wc'               => $gap['orders_in_wc'],
			'orders_in_statnive'         => (int) $gap['orders_in_statnive'],
			'action_scheduler_available' => self::has_action_scheduler(),
			'state'                      => self::read_state(),
		];
	}

	/**
	 * Detect whether there are WC orders that aren't yet in statnive_orders.
	 *
	 * Result is cached in a 5-minute transient so repeated admin pageviews
	 * don't slam the database. The transient is invalidated on every
	 * Recorder write via {@see invalidate_gap_transient()}.
	 *
	 * @return array{has_gap: bool, orders_in_wc: ?int, orders_in_statnive: int}
	 */
	public static function detect_gap(): array {
		$cached = get_transient( self::GAP_TRANSIENT );
		if ( is_array( $cached ) && array_key_exists( 'has_gap', $cached ) ) {
			$wc = array_key_exists( 'orders_in_wc', $cached ) ? $cached['orders_in_wc'] : null;
			return [
				'has_gap'            => (bool) $cached['has_gap'],
				'orders_in_wc'       => null === $wc ? null : (int) $wc,
				'orders_in_statnive' => isset( $cached['orders_in_statnive'] ) ? (int) $cached['orders_in_statnive'] : 0,
			];
		}

		$orders_in_wc       = self::count_wc_orders();
		$orders_in_statnive = self::count_statnive_orders();

		$has_gap = null !== $orders_in_wc && $orders_in_wc > 0 && $orders_in_statnive < $orders_in_wc;

		$result = [
			'has_gap'            => $has_gap,
			'orders_in_wc'       => $orders_in_wc,
			'orders_in_statnive' => $orders_in_statnive,
		];

		set_transient( self::GAP_TRANSIENT, $result, 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Invalidate the gap-detection cache. Called by the Recorder after
	 * every successful order write so the admin sees progress quickly.
	 */
	public static function invalidate_gap_transient(): void {
		delete_transient( self::GAP_TRANSIENT );
	}

	/**
	 * Read the persisted state (or a zeroed idle state if absent).
	 *
	 * @return array{status: string, total: int, processed: int, refunds: int, started_at: ?string, finished_at: ?string, last_error: ?string}
	 */
	public static function read_state(): array {
		$raw = get_option( self::STATE_OPTION, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		return [
			'status'      => isset( $raw['status'] ) && is_string( $raw['status'] ) ? $raw['status'] : self::STATUS_IDLE,
			'total'       => isset( $raw['total'] ) ? (int) $raw['total'] : 0,
			'processed'   => isset( $raw['processed'] ) ? (int) $raw['processed'] : 0,
			'refunds'     => isset( $raw['refunds'] ) ? (int) $raw['refunds'] : 0,
			'started_at'  => isset( $raw['started_at'] ) && is_string( $raw['started_at'] ) ? $raw['started_at'] : null,
			'finished_at' => isset( $raw['finished_at'] ) && is_string( $raw['finished_at'] ) ? $raw['finished_at'] : null,
			'last_error'  => isset( $raw['last_error'] ) && is_string( $raw['last_error'] ) ? $raw['last_error'] : null,
		];
	}

	/**
	 * Whether Action Scheduler is loaded on this site.
	 */
	public static function has_action_scheduler(): bool {
		return CronRegistrar::has_action_scheduler();
	}

	/**
	 * Build the `wc_get_orders()` args used for both counting and chunking.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_query(): array {
		return [
			'status'  => self::DEFAULT_STATUSES,
			'orderby' => 'date',
			'order'   => 'ASC',
		];
	}

	/**
	 * Count orders matching the backfill criteria.
	 *
	 * @param array<string, mixed> $query Base query — `paginate=true` is added here.
	 */
	private static function count_orders( array $query ): int {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		$query['limit']    = -1;
		$query['paged']    = 1;
		$query['return']   = 'ids';
		$query['paginate'] = false;
		$ids               = wc_get_orders( $query );
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/**
	 * Lightweight count of orders in WC's tables (HPOS-aware via wc_get_orders).
	 */
	private static function count_wc_orders(): ?int {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}
		$ids = wc_get_orders(
			[
				'status' => self::DEFAULT_STATUSES,
				'limit'  => -1,
				'return' => 'ids',
			]
		);
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/**
	 * Count of rows already captured in statnive_orders (excluding soft-deleted).
	 * Returns 0 when $wpdb is unavailable (defensive — unit-test environment).
	 */
	private static function count_statnive_orders(): int {
		global $wpdb;
		if ( null === $wpdb ) {
			return 0;
		}
		$table = $wpdb->prefix . 'statnive_orders';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL"
		);
		// phpcs:enable
		return null === $count ? 0 : (int) $count;
	}

	/**
	 * Persist the state option (autoload=no so it never bloats alloptions).
	 *
	 * @param array<string, mixed> $state Backfill state row.
	 */
	private static function write_state( array $state ): void {
		// Existing option may have autoload=yes from a malformed manual edit;
		// update_option preserves whatever autoload it has, so we add_option
		// first with autoload=no when missing.
		if ( false === get_option( self::STATE_OPTION, false ) ) {
			add_option( self::STATE_OPTION, $state, '', false );
			return;
		}
		update_option( self::STATE_OPTION, $state );
	}

	/**
	 * Mark the current run as failed and stash the (capped) error.
	 *
	 * @param string $message Human-readable failure cause; capped at 500 chars.
	 */
	private static function mark_failed( string $message ): void {
		$state                = self::read_state();
		$state['status']      = self::STATUS_FAILED;
		$state['finished_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		$state['last_error']  = substr( $message, 0, self::LAST_ERROR_CAP );
		self::write_state( $state );
	}
}
