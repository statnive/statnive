<?php

declare(strict_types=1);

namespace Statnive\Entity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Database\TableRegistry;
use Statnive\Service\DimensionService;

/**
 * Session entity.
 *
 * Represents a browsing session. Reuses existing session if the same visitor
 * returns within the session timeout window (30 minutes).
 * Populates all dimension FK columns via DimensionService.
 */
final class Session {

	/**
	 * Session timeout in seconds (30 minutes).
	 *
	 * @var int
	 */
	private const SESSION_TIMEOUT = 1800;

	/**
	 * Record a session — reuse existing within timeout window, or create new.
	 *
	 * Stores the resolved session_id back into the VisitorProfile.
	 *
	 * @param VisitorProfile $profile The visitor profile data bus.
	 */
	public static function record( VisitorProfile $profile ): void {
		global $wpdb;

		$visitor_id = $profile->get( 'visitor_id' );
		if ( empty( $visitor_id ) ) {
			return;
		}

		$table     = TableRegistry::get( 'sessions' );
		$timestamp = $profile->get( 'timestamp' );

		// Look for an active session from this visitor within timeout window.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, total_views FROM `{$table}`
				WHERE visitor_id = %d
				AND started_at >= DATE_SUB(%s, INTERVAL %d SECOND)
				ORDER BY started_at DESC
				LIMIT 1",
				$visitor_id,
				$timestamp,
				self::SESSION_TIMEOUT
			)
		);

		if ( null !== $existing ) {
			// Reuse existing session — increment view count and update end time.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET total_views = total_views + 1, ended_at = %s WHERE ID = %d",
					$timestamp,
					$existing->ID
				)
			);

			$profile->set( 'session_id', (int) $existing->ID );
			return;
		}

		// Resolve all dimension FKs.
		$dimensions = DimensionService::resolve_all( $profile );

		// Create new session with all dimension FKs.
		$wpdb->insert(
			$table,
			[
				'visitor_id'                => $visitor_id,
				'ip_hash'                   => $profile->get( 'visitor_hash', '' ),
				'started_at'                => $timestamp,
				'total_views'               => 1,
				'user_id'                   => $profile->get( 'user_id', 0 ),
				'referrer_id'               => $dimensions['referrer_id'] > 0 ? $dimensions['referrer_id'] : null,
				'country_id'                => $dimensions['country_id'] > 0 ? $dimensions['country_id'] : null,
				'city_id'                   => $dimensions['city_id'] > 0 ? $dimensions['city_id'] : null,
				'device_type_id'            => $dimensions['device_type_id'] > 0 ? $dimensions['device_type_id'] : null,
				'device_browser_id'         => $dimensions['device_browser_id'] > 0 ? $dimensions['device_browser_id'] : null,
				'device_browser_version_id' => $dimensions['device_browser_version_id'] > 0 ? $dimensions['device_browser_version_id'] : null,
				'device_os_id'              => $dimensions['device_os_id'] > 0 ? $dimensions['device_os_id'] : null,
				'resolution_id'             => $dimensions['resolution_id'] > 0 ? $dimensions['resolution_id'] : null,
				'language_id'               => $dimensions['language_id'] > 0 ? $dimensions['language_id'] : null,
				'timezone_id'               => $dimensions['timezone_id'] > 0 ? $dimensions['timezone_id'] : null,
			],
			[
				'%d', // visitor_id.
				'%s', // ip_hash.
				'%s', // started_at.
				'%d', // total_views.
				'%d', // user_id.
				'%d', // referrer_id.
				'%d', // country_id.
				'%d', // city_id.
				'%d', // device_type_id.
				'%d', // device_browser_id.
				'%d', // device_browser_version_id.
				'%d', // device_os_id.
				'%d', // resolution_id.
				'%d', // language_id.
				'%d', // timezone_id.
			]
		);

		$profile->set( 'session_id', (int) $wpdb->insert_id );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
