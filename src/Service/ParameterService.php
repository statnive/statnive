<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Database\TableRegistry;
use Statnive\Entity\VisitorProfile;

/**
 * UTM parameter extraction and storage service.
 *
 * Extracts standard UTM parameters from the page query string
 * and stores them in the parameters table linked to the session/view.
 */
final class ParameterService {

	/**
	 * Standard UTM parameter keys.
	 *
	 * @var string[]
	 */
	private const UTM_KEYS = [
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
	];

	/**
	 * Extract UTM parameters from a query string.
	 *
	 * @param string $query_string URL query string (without leading ?).
	 * @return array<string, string> UTM parameters found (key => value).
	 */
	public static function extract_utm( string $query_string ): array {
		if ( empty( $query_string ) ) {
			return [];
		}

		$params = [];
		parse_str( $query_string, $parsed );

		foreach ( self::UTM_KEYS as $key ) {
			if ( ! empty( $parsed[ $key ] ) ) {
				$params[ $key ] = sanitize_text_field( $parsed[ $key ] );
			}
		}

		return $params;
	}

	/**
	 * Record UTM parameters from the visitor profile into the parameters table.
	 *
	 * @param VisitorProfile $profile The visitor profile with session_id and view_id.
	 */
	public static function record( VisitorProfile $profile ): void {
		$query_string = $profile->get( 'page_query', '' );
		$utm_params   = self::extract_utm( $query_string );

		if ( empty( $utm_params ) ) {
			return;
		}

		$session_id      = $profile->get( 'session_id', 0 );
		$view_id         = $profile->get( 'view_id', 0 );
		$resource_uri_id = $profile->get( 'resource_uri_id', 0 );

		if ( 0 === $session_id ) {
			return;
		}

		global $wpdb;
		$table = TableRegistry::get( 'parameters' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $utm_params as $key => $value ) {
			$wpdb->insert(
				$table,
				[
					'session_id'      => $session_id,
					'resource_uri_id' => $resource_uri_id,
					'view_id'         => $view_id,
					'param_key'       => $key,
					'param_value'     => $value,
				],
				[ '%d', '%d', '%d', '%s', '%s' ]
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Store utm_medium on the profile for SourceDetector UTM override.
		if ( ! empty( $utm_params['utm_medium'] ) ) {
			$profile->set( 'utm_medium', $utm_params['utm_medium'] );
		}
		if ( ! empty( $utm_params['utm_source'] ) ) {
			$profile->set( 'utm_source', $utm_params['utm_source'] );
		}
	}
}
