<?php

declare(strict_types=1);

namespace Statnive\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Service\SourceDetector;

/**
 * Database schema migration runner.
 *
 * Compares the stored `statnive_db_version` option with the running plugin
 * version on every `plugins_loaded` and runs any pending migrations in order.
 *
 * v0.3.x ships with the initial schema; the migration list is empty. The
 * runner is wired up so future schema changes (0.4.0+) can land via a single
 * static method call without retrofitting the bootstrap.
 *
 * Per WordPress.org submission checklist §27 (`[RELEASE BLOCKER]`):
 *  - Migrations must be resumable (re-running an interrupted migration must
 *    not corrupt data).
 *  - Long-running backfills must be chunked.
 *  - The runner must be safe to call on every request — bail fast when no
 *    migration is pending.
 */
final class Migrator {

	/**
	 * The option that records the schema version this site has run up to.
	 */
	public const OPTION = 'statnive_db_version';

	/**
	 * Cursor for the chunked referrer-reclassify backfill. Holds the highest
	 * referrer-row ID processed so far. Absent option = backfill complete.
	 */
	public const RECLASSIFY_CURSOR_OPTION = 'statnive_reclassify_cursor';

	/**
	 * Per-batch and per-request bounds for the reclassify backfill.
	 *
	 * Bounded so the first post-upgrade request stays under the §27 long-running
	 * threshold even on sites with very large `statnive_referrers` cardinality.
	 * Remaining rows resume on the next request via `continue_reclassify_if_pending`.
	 */
	private const RECLASSIFY_BATCH_SIZE          = 500;
	private const RECLASSIFY_BATCHES_PER_REQUEST = 4;

	/**
	 * Hook the runner into `plugins_loaded`.
	 *
	 * Idempotent — `add_action()` deduplicates by callable.
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', [ self::class, 'run' ], 20 );
		add_action( 'plugins_loaded', [ self::class, 'continue_reclassify_if_pending' ], 21 );
	}

	/**
	 * Compare the stored schema version with the running version and run any
	 * migrations needed to bring the site up to date.
	 *
	 * Bails fast (single `get_option()` call + `version_compare()`) when no
	 * migration is pending, so this is safe to run on every request.
	 */
	public static function run(): void {
		$current = (string) get_option( self::OPTION, '0.0.0' );
		$target  = STATNIVE_VERSION;

		// Downgrade detection (§27): warn if the stored schema version is
		// newer than the running plugin version (user downgraded).
		if ( version_compare( $current, $target, '>' ) ) {
			add_action( 'admin_notices', [ self::class, 'downgrade_notice' ] );
			return;
		}

		if ( version_compare( $current, $target, '>=' ) ) {
			return;
		}

		// No migrations are registered for the v0.3.x line — every install
		// already runs the latest schema. Future schema bumps will register
		// migration callbacks here, ordered by version.
		$migrations = self::registered_migrations();

		foreach ( $migrations as $version => $migration ) {
			if ( version_compare( $current, $version, '>=' ) ) {
				continue;
			}
			$migration();
			update_option( self::OPTION, $version );
			$current = $version;
		}

		// Even if no migration ran (e.g. an install whose option lagged the
		// activation hook), bring the option in sync with the running version
		// so we don't re-evaluate on every request.
		if ( version_compare( $current, $target, '<' ) ) {
			update_option( self::OPTION, $target );
		}
	}

	/**
	 * Map of `version => callable` migrations to run when upgrading.
	 *
	 * Add new entries here when bumping the schema. Each callable must be
	 * idempotent and resumable.
	 *
	 * @return array<string, callable>
	 */
	private static function registered_migrations(): array {
		return [
			// Keyed at the running plugin version so any older install runs
			// each pending migration step exactly once on its next page load.
			STATNIVE_VERSION => static function (): void {
				self::migrate_0_4_2_drop_email_and_full_mode();
				self::migrate_reclassify_referrers();
			},
		];
	}

	/**
	 * Upgrade migration.
	 *
	 * - Drops dead options left over from the Email Reports subsystem.
	 * - Coerces any legacy `statnive_consent_mode = 'full'` to `'cookieless'`
	 *   so the value matches the reduced CONSENT_MODES allow-list.
	 * - Backfills `statnive_retention_mode = 'forever'` for sites whose stored
	 *   days value already matches the new "Forever" default, so purge does
	 *   not silently start deleting data after upgrade.
	 *
	 * Idempotent: `delete_option` is a no-op when the key is absent, and the
	 * `get_option` checks only write when the stored value actually needs
	 * updating.
	 */
	public static function migrate_0_4_2_drop_email_and_full_mode(): void {
		delete_option( 'statnive_email_reports' );
		delete_option( 'statnive_email_frequency' );
		delete_option( 'statnive_email_recipients' );

		if ( 'full' === get_option( 'statnive_consent_mode' ) ) {
			update_option( 'statnive_consent_mode', 'cookieless' );
		}

		if ( 3650 === (int) get_option( 'statnive_retention_days', 3650 )
			&& false === get_option( 'statnive_retention_mode', false )
		) {
			update_option( 'statnive_retention_mode', 'forever' );
		}

		wp_clear_scheduled_hook( 'statnive_email_report' );
	}

	/**
	 * Re-classify rows in `statnive_referrers` whose `channel` / `name` were
	 * computed by the pre-0.4.6 substring-matching `SourceDetector::classify()`.
	 *
	 * The substring rule mis-classified any host containing a brand fragment
	 * (e.g. every `*t.com` matched `t.co` and was tagged Twitter/X). The fixed
	 * suffix-match classifier flips these rows back to their correct channel.
	 *
	 * Initialises the cursor option then drains as many batches as the
	 * per-request bound allows. Remaining rows continue across subsequent
	 * requests via `continue_reclassify_if_pending` until the cursor reaches
	 * the end and the option is deleted.
	 */
	public static function migrate_reclassify_referrers(): void {
		if ( false === get_option( self::RECLASSIFY_CURSOR_OPTION, false ) ) {
			update_option( self::RECLASSIFY_CURSOR_OPTION, 0, false );
		}
		self::process_reclassify_batches();
	}

	/**
	 * Resume the reclassify backfill on later requests until the cursor is
	 * cleared. Bails fast (single get_option call) when no backfill is pending
	 * so this is safe to register on every request.
	 */
	public static function continue_reclassify_if_pending(): void {
		if ( false === get_option( self::RECLASSIFY_CURSOR_OPTION, false ) ) {
			return;
		}
		self::process_reclassify_batches();
	}

	/**
	 * Drain up to RECLASSIFY_BATCHES_PER_REQUEST batches starting at the
	 * persisted cursor. Idempotent on already-correct rows; resumable across
	 * requests; per-row UPDATE so a mid-loop crash leaves earlier rows fixed.
	 */
	private static function process_reclassify_batches(): void {
		global $wpdb;

		if ( ! $wpdb ) {
			return;
		}

		$table  = TableRegistry::get( 'referrers' );
		$cursor = (int) get_option( self::RECLASSIFY_CURSOR_OPTION, 0 );

		for ( $batch = 0; $batch < self::RECLASSIFY_BATCHES_PER_REQUEST; $batch++ ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ID, channel, name, domain FROM %i WHERE ID > %d ORDER BY ID ASC LIMIT %d',
					$table,
					$cursor,
					self::RECLASSIFY_BATCH_SIZE
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! is_array( $rows ) || empty( $rows ) ) {
				delete_option( self::RECLASSIFY_CURSOR_OPTION );
				return;
			}

			foreach ( $rows as $row ) {
				$cursor = (int) $row->ID;
				$domain = (string) ( $row->domain ?? '' );
				if ( '' === $domain ) {
					continue;
				}

				$classified = SourceDetector::classify( $domain, '', '' );

				if ( $row->channel === $classified['channel'] && ( $row->name ?? '' ) === $classified['name'] ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					[
						'channel' => $classified['channel'],
						'name'    => $classified['name'],
					],
					[ 'ID' => (int) $row->ID ],
					[ '%s', '%s' ],
					[ '%d' ]
				);
			}

			update_option( self::RECLASSIFY_CURSOR_OPTION, $cursor, false );
		}
	}

	/**
	 * Show admin notice when a downgrade is detected.
	 */
	public static function downgrade_notice(): void {
		$current = (string) get_option( self::OPTION, '0.0.0' );
		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%s</strong></p><p>%s</p></div>',
			esc_html__( 'Statnive: plugin version mismatch detected.', 'statnive' ),
			esc_html(
				sprintf(
					/* translators: 1: stored schema version, 2: running plugin version */
					__( 'Your database schema is at version %1$s but the running plugin is version %2$s. This may happen if you downgraded the plugin. Please re-install the latest version to avoid data inconsistencies.', 'statnive' ),
					$current,
					STATNIVE_VERSION
				)
			)
		);
	}
}
