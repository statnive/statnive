<?php

declare(strict_types=1);

namespace Statnive\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema drift detection and auto-repair.
 *
 * Runs on admin_init, compares stored DB version against plugin version.
 * Uses a transient guard to avoid running on every admin page load.
 */
final class SchemaMaintainer {

	/**
	 * Transient key for throttling schema checks.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'statnive_schema_check';

	/**
	 * How long to cache schema check results (in seconds).
	 *
	 * @var int
	 */
	private const CHECK_TTL = HOUR_IN_SECONDS;

	/**
	 * Initialize the schema maintainer.
	 *
	 * Hooks into admin_init to check for schema drift.
	 */
	public static function init(): void {
		add_action( 'admin_init', [ self::class, 'maybe_update_schema' ] );
	}

	/**
	 * Check if schema needs updating and run dbDelta if so.
	 *
	 * Skips check if:
	 * - DB version matches plugin version, and
	 * - Transient guard is still valid.
	 */
	public static function maybe_update_schema(): void {
		$db_version = get_option( 'statnive_db_version', '0' );

		// If DB version matches and transient guard is active, skip.
		if ( STATNIVE_VERSION === $db_version && false !== get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}

		// Run dbDelta to create/update tables.
		$results = DatabaseFactory::create_tables();

		// Check for missing tables and log if found.
		$check = DatabaseFactory::check_tables();
		if ( ! empty( $check['missing'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[Statnive] Schema maintenance: %d missing tables detected: %s',
					count( $check['missing'] ),
					implode( ', ', $check['missing'] )
				)
			);
		}

		// Update stored version and set transient guard.
		update_option( 'statnive_db_version', STATNIVE_VERSION );
		set_transient( self::TRANSIENT_KEY, '1', self::CHECK_TTL );
	}

	/**
	 * Force a schema check (bypasses transient guard).
	 *
	 * Useful for admin tools or after manual DB changes.
	 *
	 * @return array{missing: string[], existing: string[]} Table status.
	 */
	public static function force_check(): array {
		delete_transient( self::TRANSIENT_KEY );
		delete_option( 'statnive_db_version' );

		self::maybe_update_schema();

		return DatabaseFactory::check_tables();
	}
}
