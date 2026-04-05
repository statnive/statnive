<?php

declare(strict_types=1);

namespace Statnive\Entity;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor entity.
 *
 * Represents a unique visitor identified by a privacy-safe hash.
 * Hash is computed from IP + User-Agent + daily salt, truncated to BINARY(8).
 */
final class Visitor {

	/**
	 * Record a visitor — lookup existing by hash, or insert new.
	 *
	 * Stores the resolved visitor_id back into the VisitorProfile.
	 *
	 * @param VisitorProfile $profile The visitor profile data bus.
	 */
	public static function record( VisitorProfile $profile ): void {
		global $wpdb;

		// Compute visitor hash if not already set.
		$hash = $profile->get( 'visitor_hash' );
		if ( empty( $hash ) ) {
			$hash = $profile->compute_visitor_hash();
		}

		$table = TableRegistry::get( 'visitors' );

		// Lookup existing visitor by hash.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$visitor_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM `{$table}` WHERE hash = %s LIMIT 1",
				$hash
			)
		);

		if ( null !== $visitor_id ) {
			$profile->set( 'visitor_id', (int) $visitor_id );
			return;
		}

		// Insert new visitor.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$table}` (hash, created_at) VALUES (%s, %s)",
				$hash,
				$profile->get( 'timestamp' )
			)
		);

		$profile->set( 'visitor_id', (int) $wpdb->insert_id );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
