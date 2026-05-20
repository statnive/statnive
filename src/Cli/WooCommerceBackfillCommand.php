<?php

declare(strict_types=1);

namespace Statnive\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Integration\WooCommerce\BackfillService;
use Statnive\Integration\WooCommerce\Detector;
use WP_CLI;

/**
 * WP-CLI command: `wp statnive wc-backfill`.
 *
 * Pre-existing WooCommerce orders aren't visible to Statnive's Revenue
 * Report by default — the Recorder only listens on order-creation /
 * status-transition hooks, so orders placed BEFORE the plugin was
 * installed have no row in `statnive_orders`.
 *
 * This command sweeps every WooCommerce order (oldest first so the
 * `is_first_purchase` flag computes correctly) and runs the Recorder
 * over each one. The Recorder is idempotent on `wc_order_id` so the
 * sweep can be re-run safely.
 *
 * READ-ONLY against WooCommerce — same invariant as the live recorder.
 *
 * Loaded only when WP-CLI is available.
 */
final class WooCommerceBackfillCommand {

	/**
	 * Backfill the Statnive WC tables from existing WooCommerce orders.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Stop after this many orders (useful for testing).
	 *
	 * [--days=<number>]
	 * : Only backfill orders created in the last N days.
	 *
	 * [--status=<list>]
	 * : Comma-separated WC statuses to include. Default: all that
	 *   contribute to revenue (`processing,completed,refunded`).
	 *
	 * [--batch=<number>]
	 * : Orders per chunk. Default: 500.
	 *
	 * [--dry-run]
	 * : Report what would be backfilled without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp statnive wc-backfill
	 *     wp statnive wc-backfill --days=90
	 *     wp statnive wc-backfill --status=processing,completed --batch=200
	 *     wp statnive wc-backfill --dry-run
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Flag args.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );

		if ( ! Detector::is_active() ) {
			WP_CLI::error( 'WooCommerce is not active or below the minimum required version (' . Detector::MIN_WC_VERSION . ').' );
			return;
		}

		$limit   = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 0;
		$days    = isset( $assoc_args['days'] ) ? max( 1, (int) $assoc_args['days'] ) : 0;
		$batch   = isset( $assoc_args['batch'] ) ? max( 50, (int) $assoc_args['batch'] ) : 500;
		$dry_run = isset( $assoc_args['dry-run'] );
		$status  = isset( $assoc_args['status'] )
			? array_map( 'trim', explode( ',', (string) $assoc_args['status'] ) )
			: [ 'processing', 'completed', 'refunded' ];

		$query_args = [
			'status'  => $status,
			'orderby' => 'date',
			'order'   => 'ASC',
			'limit'   => $batch,
			'paged'   => 1,
			'return'  => 'ids',
		];
		if ( $days > 0 ) {
			$query_args['date_created'] = '>' . gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		}

		// First pass: count.
		$total_query          = $query_args;
		$total_query['limit'] = -1;
		$total_query['paged'] = 1;
		$total_ids            = wc_get_orders( $total_query );
		$total                = is_array( $total_ids ) ? count( $total_ids ) : 0;
		if ( $limit > 0 && $total > $limit ) {
			$total = $limit;
		}

		WP_CLI::log(
			sprintf(
				'Backfilling %d order(s) — status=%s%s%s',
				$total,
				implode( ',', $status ),
				$days > 0 ? ", days={$days}" : '',
				$dry_run ? ', DRY RUN' : ''
			)
		);

		if ( 0 === $total ) {
			WP_CLI::success( 'Nothing to backfill.' );
			return;
		}

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry-run: would backfill %d order(s).', $total ) );
			return;
		}

		$progress  = \WP_CLI\Utils\make_progress_bar( 'Backfilling orders', $total );
		$processed = 0;
		$refunds   = 0;
		$page      = 1;

		while ( true ) {
			$query_args['paged'] = $page;
			$ids                 = wc_get_orders( $query_args );
			if ( ! is_array( $ids ) || empty( $ids ) ) {
				break;
			}

			// Honor the --limit flag by trimming the batch BEFORE handing it
			// to the shared service (which doesn't know about that flag).
			if ( $limit > 0 ) {
				$remaining = $limit - $processed;
				if ( $remaining <= 0 ) {
					break;
				}
				if ( count( $ids ) > $remaining ) {
					$ids = array_slice( $ids, 0, $remaining );
				}
			}

			$result     = BackfillService::process_order_ids( array_map( 'intval', $ids ) );
			$processed += (int) $result['processed'];
			$refunds   += (int) $result['refunds'];
			for ( $tick = 0; $tick < (int) $result['processed']; $tick++ ) {
				$progress->tick();
			}

			if ( $limit > 0 && $processed >= $limit ) {
				break;
			}

			++$page;
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Backfilled %d orders and %d refunds.', $processed, $refunds ) );
	}
}
