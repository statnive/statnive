<?php

declare(strict_types=1);

namespace Statnive\Entity;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View entity.
 *
 * Represents a single page view within a session.
 * Links to a resource URI and optionally chains to the next view (linked list).
 */
final class View {

	/**
	 * Record a page view.
	 *
	 * Resolves the resource URI, inserts the view record, and updates
	 * the session's last_view_id for linked-list path reconstruction.
	 *
	 * Stores the resolved view_id back into the VisitorProfile.
	 *
	 * @param VisitorProfile $profile The visitor profile data bus.
	 */
	public static function record( VisitorProfile $profile ): void {
		global $wpdb;

		$session_id = $profile->get( 'session_id' );
		if ( empty( $session_id ) ) {
			return;
		}

		// Resolve or create resource URI.
		$resource_uri_id = self::resolve_resource_uri( $profile );
		$resource_id     = self::resolve_resource( $profile );
		$timestamp       = $profile->get( 'timestamp' );
		$pvid            = $profile->get( 'pvid', '' );

		$view_table = TableRegistry::get( 'views' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (session_id, resource_uri_id, resource_id, viewed_at, pvid) VALUES (%d, %d, %d, %s, %s)',
				$view_table,
				$session_id,
				$resource_uri_id,
				$resource_id,
				$timestamp,
				$pvid
			)
		);

		$view_id = (int) $wpdb->insert_id;
		$profile->set( 'view_id', $view_id );
		$profile->set( 'resource_uri_id', $resource_uri_id );

		// Update session's last_view_id and initial_view_id if first view.
		$session_table = TableRegistry::get( 'sessions' );
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET last_view_id = %d, initial_view_id = COALESCE(initial_view_id, %d) WHERE ID = %d',
				$session_table,
				$view_id,
				$view_id,
				$session_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Resolve or create a resource URI record.
	 *
	 * Uses CRC32 hash for efficient deduplication lookups (D-03).
	 *
	 * @param VisitorProfile $profile The visitor profile data bus.
	 * @return int Resource URI ID.
	 */
	private static function resolve_resource_uri( VisitorProfile $profile ): int {
		global $wpdb;

		$resource_type = $profile->get( 'resource_type', 'post' );
		$resource_id   = $profile->get( 'resource_id', 0 );

		// Use actual page path from tracker if available, fall back to synthetic URI.
		$page_url = $profile->get( 'page_url', '' );
		$uri      = ! empty( $page_url ) ? $page_url : "/{$resource_type}/{$resource_id}";
		$uri_hash = crc32( $uri );

		$table = TableRegistry::get( 'resource_uris' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ID FROM %i WHERE uri_hash = %d AND uri = %s LIMIT 1',
				$table,
				$uri_hash,
				$uri
			)
		);

		if ( null !== $existing_id ) {
			return (int) $existing_id;
		}

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (uri, uri_hash, resource_id) VALUES (%s, %d, %d)',
				$table,
				$uri,
				$uri_hash,
				$resource_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $wpdb->insert_id;
	}

	/**
	 * Resolve or create a resource metadata record.
	 *
	 * @param VisitorProfile $profile The visitor profile data bus.
	 * @return int Resource ID in statnive_resources table.
	 */
	private static function resolve_resource( VisitorProfile $profile ): int {
		global $wpdb;

		$resource_type = $profile->get( 'resource_type', 'post' );
		$resource_id   = $profile->get( 'resource_id', 0 );

		$table = TableRegistry::get( 'resources' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ID FROM %i WHERE resource_type = %s AND resource_id = %d LIMIT 1',
				$table,
				$resource_type,
				$resource_id
			)
		);

		if ( null !== $existing_id ) {
			return (int) $existing_id;
		}

		// Get post title for caching.
		$post  = get_post( $resource_id );
		$title = ( null !== $post ) ? $post->post_title : '';

		// Use INSERT IGNORE to prevent duplicate rows from concurrent requests.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (resource_type, resource_id, cached_title) VALUES (%s, %d, %s)',
				$table,
				$resource_type,
				$resource_id,
				$title
			)
		);

		$new_id = (int) $wpdb->insert_id;

		// Race condition: another request inserted first. Re-query to get the ID.
		if ( 0 === $new_id ) {
			$new_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT ID FROM %i WHERE resource_type = %s AND resource_id = %d LIMIT 1',
					$table,
					$resource_type,
					$resource_id
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return $new_id;
	}
}
