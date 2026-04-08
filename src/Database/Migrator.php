<?php

declare(strict_types=1);

namespace Statnive\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * Hook the runner into `plugins_loaded`.
	 *
	 * Idempotent — `add_action()` deduplicates by callable.
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', [ self::class, 'run' ], 20 );
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
		// Example for the next schema bump (intentionally commented out).
		// '0.4.0' => [ self::class, 'migrate_0_4_0' ].
		return [];
	}
}
