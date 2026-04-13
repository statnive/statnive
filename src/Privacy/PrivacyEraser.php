<?php

declare(strict_types=1);

namespace Statnive\Privacy;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Personal Data Eraser for GDPR compliance.
 *
 * Anonymizes (not deletes) user data to preserve aggregate analytics accuracy.
 * NULLs user_id and visitor_id on sessions, keeping counts intact.
 */
final class PrivacyEraser {

	/**
	 * Batch size for paginated erasure.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Register the eraser with WordPress.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'add_eraser' ] );
	}

	/**
	 * Add Statnive eraser to the list.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_eraser( array $erasers ): array {
		$erasers['statnive'] = [
			'eraser_friendly_name' => __( 'Statnive Analytics', 'statnive' ),
			'callback'             => [ self::class, 'erase' ],
		];
		return $erasers;
	}

	/**
	 * Erase (anonymize) personal data for a user.
	 *
	 * @param string $email_address User's email address.
	 * @param int    $page          Page number for batch processing.
	 * @return array{items_removed: int, items_retained: int, messages: array<string>, done: bool}
	 */
	public static function erase( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );

		if ( false === $user ) {
			return [
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => [],
				'done'           => true,
			];
		}

		global $wpdb;

		$sessions_table = TableRegistry::get( 'sessions' );
		$offset         = ( $page - 1 ) * self::BATCH_SIZE;

		// Count sessions for this user in current batch.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT ID FROM %i
				WHERE user_id = %d
				ORDER BY ID ASC
				LIMIT %d OFFSET %d',
				$sessions_table,
				$user->ID,
				self::BATCH_SIZE,
				$offset
			)
		);

		if ( empty( $session_ids ) ) {
			return [
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => [],
				'done'           => true,
			];
		}

		// Anonymize: NULL-ify user_id and visitor_id to preserve aggregates.
		$ids_placeholder = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
		// $ids_placeholder is built from a count of validated integer IDs;
		// $sessions_table is from TableRegistry. Both safe to interpolate.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET user_id = NULL, visitor_id = NULL, ip_hash = ''
				WHERE ID IN ({$ids_placeholder})",
				$sessions_table,
				...$session_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$removed = ( false !== $updated ) ? (int) $updated : 0;

		return [
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => [
				sprintf(
					/* translators: %d: number of sessions anonymized */
					__( 'Anonymized %d analytics session(s). Aggregate statistics preserved.', 'statnive' ),
					$removed
				),
			],
			'done'           => count( $session_ids ) < self::BATCH_SIZE,
		];
	}
}
