<?php

declare(strict_types=1);

namespace Statnive\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Cron\DailyAggregationJob;
use Statnive\Cron\DataPurgeJob;
use Statnive\Cron\EmailReportJob;
use Statnive\Cron\SaltRotationJob;
use WP_CLI;

/**
 * WP-CLI command: `wp statnive cron`.
 *
 * Required by WordPress.org submission checklist §28.1.3 — sites with
 * `DISABLE_WP_CRON` need a way to run Statnive's scheduled work from the
 * command line so retention, aggregation and salt rotation can be driven
 * by a system cron job instead of WordPress's traffic-triggered scheduler.
 *
 * Loaded only when WP-CLI is available (`defined('WP_CLI') && WP_CLI`).
 */
final class CronCommand {

	/**
	 * Run one or all Statnive cron jobs immediately.
	 *
	 * ## OPTIONS
	 *
	 * [--job=<job>]
	 * : Which job to run. Default: all.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - data-purge
	 *   - aggregation
	 *   - salt-rotation
	 *   - email-report
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp statnive cron run
	 *   wp statnive cron run --job=data-purge
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function run( array $args, array $assoc_args ): void {
		unset( $args );
		$job = isset( $assoc_args['job'] ) ? (string) $assoc_args['job'] : 'all';

		$ran = [];
		$err = [];

		$jobs = [
			'salt-rotation' => [ SaltRotationJob::class, 'Daily salt rotation' ],
			'aggregation'   => [ DailyAggregationJob::class, 'Daily aggregation' ],
			'data-purge'    => [ DataPurgeJob::class, 'Data purge' ],
			'email-report'  => [ EmailReportJob::class, 'Email report' ],
		];

		foreach ( $jobs as $key => $info ) {
			if ( 'all' !== $job && $job !== $key ) {
				continue;
			}
			[ $class, $label ] = $info;
			try {
				$class::run();
				$ran[] = $label;
			} catch ( \Throwable $e ) {
				$err[] = $label . ': ' . $e->getMessage();
			}
		}

		if ( ! empty( $ran ) ) {
			WP_CLI::success( 'Ran: ' . implode( ', ', $ran ) );
		}

		if ( ! empty( $err ) ) {
			WP_CLI::error( implode( "\n", $err ) );
		}

		if ( empty( $ran ) && empty( $err ) ) {
			WP_CLI::warning( 'No matching job: ' . $job );
		}
	}
}
