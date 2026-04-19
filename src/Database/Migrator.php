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
			// Drops the Email Reports subsystem and coerces legacy consent
			// values. Keyed at the current plugin version so pre-0.4.2
			// installs run it exactly once on their next page load.
			STATNIVE_VERSION => [ self::class, 'migrate_0_4_2_drop_email_and_full_mode' ],
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
