<?php

declare(strict_types=1);

namespace Statnive\Advisor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-user pinned-question preferences for the Ask me! tab.
 *
 * Storage: `wp_usermeta` key `statnive_pinned_questions` (JSON-encoded
 * array of question IDs). Per-user, per-site (multisite-safe — WP user
 * meta naturally scopes via `wp_usermeta.user_id`).
 *
 * Defaults: when a user has no pins yet, `get()` returns the 5 "killer
 * questions" from research #71 §6 row 1-5 — the highest-confidence
 * questions that work today against existing Statnive endpoints.
 */
final class UserPreferences {

	/**
	 * The user-meta key holding pinned question IDs.
	 */
	public const META_KEY = 'statnive_pinned_questions';

	/**
	 * Maximum number of pinned questions per user (Free tier).
	 *
	 * Caps the home tab at 10 pins in v1. The Growth-tier unlock in
	 * roadmap Phase 14 raises the ceiling to 50 alongside the Paid
	 * question library — see jaan-to/outputs/ROADMAP-WP-STATNIVE.md.
	 */
	public const MAX_PINS = 10;

	/**
	 * Default pinned set applied when a user has no existing meta entry.
	 *
	 * Order: the 5 killer questions from research #71 §6 — all 🟢 Direct,
	 * all Free, all answerable against existing Statnive endpoints today.
	 *
	 * @return array<int, string>
	 */
	public static function default_pinned(): array {
		return [ 'q2', 'q41', 'q23', 'q72', 'q81' ];
	}

	/**
	 * Read the user's pinned IDs. Returns the defaults when no entry exists.
	 *
	 * @param int $user_id WP user ID.
	 * @return array<int, string>
	 */
	public static function get( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );

		if ( '' === $raw || false === $raw ) {
			return self::default_pinned();
		}

		$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );

		if ( ! is_array( $decoded ) ) {
			return self::default_pinned();
		}

		// Defensive: filter to known IDs only (drops orphans from older versions).
		$valid_ids = Questions::valid_ids();
		$filtered  = array_values(
			array_filter(
				$decoded,
				static fn( $id ) => is_string( $id ) && in_array( $id, $valid_ids, true )
			)
		);

		// If filtering wiped everything (e.g., schema churn), fall back to defaults.
		if ( empty( $filtered ) ) {
			return self::default_pinned();
		}

		return array_slice( $filtered, 0, self::MAX_PINS );
	}

	/**
	 * Persist a new pinned-IDs array. Drops unknown IDs and enforces the cap.
	 *
	 * @param int                $user_id WP user ID.
	 * @param array<int, string> $ids     Question IDs to pin.
	 * @return array<int, string> Sanitized + truncated list actually stored.
	 */
	public static function set( int $user_id, array $ids ): array {
		$valid_ids = Questions::valid_ids();

		$sanitized = [];
		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) ) {
				continue;
			}
			if ( ! in_array( $id, $valid_ids, true ) ) {
				continue;
			}
			if ( in_array( $id, $sanitized, true ) ) {
				continue; // De-dupe.
			}
			$sanitized[] = $id;
			if ( count( $sanitized ) >= self::MAX_PINS ) {
				break;
			}
		}

		update_user_meta(
			$user_id,
			self::META_KEY,
			wp_json_encode( $sanitized )
		);

		return $sanitized;
	}

	/**
	 * Remove the user's pinned-questions meta entry entirely. Used by the
	 * WP Privacy eraser hook.
	 *
	 * @param int $user_id WP user ID.
	 */
	public static function erase( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Pin a single question (idempotent, respects cap).
	 *
	 * @param int    $user_id WP user ID.
	 * @param string $id      Question ID to pin.
	 * @return array<int, string> Updated full list.
	 */
	public static function pin( int $user_id, string $id ): array {
		$current = self::get( $user_id );
		if ( in_array( $id, $current, true ) ) {
			return $current;
		}
		$current[] = $id;
		return self::set( $user_id, $current );
	}

	/**
	 * Unpin a single question (idempotent).
	 *
	 * @param int    $user_id WP user ID.
	 * @param string $id      Question ID to unpin.
	 * @return array<int, string> Updated full list.
	 */
	public static function unpin( int $user_id, string $id ): array {
		$current  = self::get( $user_id );
		$filtered = array_values( array_filter( $current, static fn( $x ) => $x !== $id ) );

		if ( count( $filtered ) === count( $current ) ) {
			return $current; // Nothing to do.
		}

		// If unpinning would empty the list, the user explicitly cleared their pins
		// — preserve that by writing an empty array rather than reverting to defaults.
		if ( empty( $filtered ) ) {
			update_user_meta( $user_id, self::META_KEY, wp_json_encode( [] ) );
			return [];
		}

		return self::set( $user_id, $filtered );
	}
}
