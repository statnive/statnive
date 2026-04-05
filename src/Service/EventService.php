<?php

declare(strict_types=1);

namespace Statnive\Service;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom event recording service.
 *
 * Validates event names and properties, then inserts into the events table.
 * Properties are limited to 5 key-value pairs with string/number values only.
 */
final class EventService {

	/**
	 * Maximum number of properties per event.
	 *
	 * @var int
	 */
	private const MAX_PROPERTIES = 5;

	/**
	 * Maximum event name length.
	 *
	 * @var int
	 */
	private const MAX_NAME_LENGTH = 100;

	/**
	 * Record a custom event.
	 *
	 * @param string              $event_name  Raw event name.
	 * @param array<string,mixed> $properties  Event properties (max 5).
	 * @param int                 $session_id  Session ID.
	 * @param int                 $resource_uri_id Resource URI ID.
	 * @param int                 $user_id     WordPress user ID.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function record( string $event_name, array $properties, int $session_id = 0, int $resource_uri_id = 0, int $user_id = 0 ) {
		$clean_name = self::sanitize_name( $event_name );
		if ( empty( $clean_name ) ) {
			return false;
		}

		$clean_props = self::sanitize_properties( $properties );
		$event_data  = ! empty( $clean_props ) ? wp_json_encode( $clean_props ) : null;

		global $wpdb;
		$table = TableRegistry::get( 'events' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table,
			[
				'session_id'      => $session_id > 0 ? $session_id : null,
				'resource_uri_id' => $resource_uri_id > 0 ? $resource_uri_id : null,
				'user_id'         => $user_id > 0 ? $user_id : null,
				'event_name'      => $clean_name,
				'event_data'      => $event_data,
				'created_at'      => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s' ]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$insert_id = (int) $wpdb->insert_id;

		if ( $insert_id > 0 ) {
			/**
			 * Fires after a custom event is recorded.
			 *
			 * @param string $event_name  Sanitized event name.
			 * @param array  $properties  Sanitized properties.
			 * @param int    $insert_id   Database row ID.
			 */
			do_action( 'statnive_event_recorded', $clean_name, $clean_props, $insert_id );
		}

		return $insert_id > 0 ? $insert_id : false;
	}

	/**
	 * Sanitize an event name.
	 *
	 * Replaces non-alphanumeric characters with underscores, trims edges.
	 *
	 * @param string $name Raw event name.
	 * @return string Sanitized name (max 100 chars).
	 */
	public static function sanitize_name( string $name ): string {
		$clean = preg_replace( '/[^a-z0-9_]+/i', '_', trim( $name ) );
		$clean = trim( $clean ?? '', '_' );
		return substr( $clean, 0, self::MAX_NAME_LENGTH );
	}

	/**
	 * Sanitize event properties.
	 *
	 * Limits to MAX_PROPERTIES keys. Only string and numeric values allowed.
	 *
	 * @param array<string,mixed> $properties Raw properties.
	 * @return array<string,string|int|float> Sanitized properties.
	 */
	public static function sanitize_properties( array $properties ): array {
		$clean = [];
		$count = 0;

		foreach ( $properties as $key => $value ) {
			if ( $count >= self::MAX_PROPERTIES ) {
				break;
			}

			$clean_key = sanitize_text_field( (string) $key );
			if ( empty( $clean_key ) ) {
				continue;
			}

			if ( is_string( $value ) ) {
				$clean[ $clean_key ] = sanitize_text_field( $value );
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$clean[ $clean_key ] = $value;
			}
			// Skip objects, arrays, booleans, null.

			++$count;
		}

		return $clean;
	}
}
