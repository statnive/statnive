<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Api\Concerns\CachesResponses;
use Statnive\Api\Concerns\ValidatesDateRange;
use Statnive\Database\TableRegistry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for dimension breakdowns.
 *
 * Endpoint: GET /wp-json/statnive/v1/dimensions/{type}
 * Types: countries, cities, browsers, oss, devices, languages
 */
final class DimensionsController extends WP_REST_Controller {

	use CachesResponses;
	use ValidatesDateRange;

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'statnive/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'dimensions';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<type>[a-z]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => [
						'type'  => [
							'required'          => true,
							'validate_callback' => [ $this, 'validate_type' ],
							'sanitize_callback' => 'sanitize_text_field',
						],
						'from'  => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => [ $this, 'validate_date' ],
							'sanitize_callback' => 'sanitize_text_field',
						],
						'to'    => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => [ $this, 'validate_date' ],
							'sanitize_callback' => 'sanitize_text_field',
						],
						'limit' => [
							'default'           => 20,
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check — requires manage_options.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get dimension breakdown for the date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$type   = $request->get_param( 'type' );
		$from   = $request->get_param( 'from' );
		$to     = $request->get_param( 'to' );
		$limit  = min( (int) $request->get_param( 'limit' ), 100 );
		$params = [
			'from'  => $from,
			'to'    => $to,
			'limit' => $limit,
			'type'  => $type,
		];

		$cached = $this->get_cached_response( 'dimensions', $params );
		if ( null !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$rows   = $this->query_dimension( $type, $from, $to, $limit );
		$result = is_array( $rows ) ? $rows : [];

		$this->set_cached_response( 'dimensions', $params, $result, $from, $to );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Validate the dimension type parameter.
	 *
	 * @param string $value Type string.
	 * @return bool
	 */
	public function validate_type( $value ): bool {
		return in_array( $value, [ 'countries', 'cities', 'browsers', 'oss', 'devices', 'languages' ], true );
	}

	/**
	 * Query a dimension table joined to sessions.
	 *
	 * @param string $type  Dimension type.
	 * @param string $from  Start date.
	 * @param string $to    End date.
	 * @param int    $limit Max rows.
	 * @return array<int, object>|null
	 */
	private function query_dimension( string $type, string $from, string $to, int $limit ): ?array {
		global $wpdb;

		$sessions = TableRegistry::get( 'sessions' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		switch ( $type ) {
			case 'countries':
				$dim = TableRegistry::get( 'countries' );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT d.code, d.name, d.continent_code,
							COUNT(DISTINCT s.visitor_id) AS visitors,
							COUNT(DISTINCT s.ID) AS sessions
						FROM %i s
						INNER JOIN %i d ON s.country_id = d.ID
						WHERE s.started_at BETWEEN %s AND %s
						GROUP BY d.code, d.name, d.continent_code
						ORDER BY visitors DESC LIMIT %d',
						$sessions,
						$dim,
						$from . ' 00:00:00',
						$to . ' 23:59:59',
						$limit
					),
					ARRAY_A
				);

			case 'cities':
				$dim     = TableRegistry::get( 'cities' );
				$country = TableRegistry::get( 'countries' );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT d.city_name, c.name AS country,
							COUNT(DISTINCT s.visitor_id) AS visitors,
							COUNT(DISTINCT s.ID) AS sessions
						FROM %i s
						INNER JOIN %i d ON s.city_id = d.ID
						LEFT JOIN %i c ON d.country_id = c.ID
						WHERE s.started_at BETWEEN %s AND %s
						GROUP BY d.city_name, c.name
						ORDER BY visitors DESC LIMIT %d',
						$sessions,
						$dim,
						$country,
						$from . ' 00:00:00',
						$to . ' 23:59:59',
						$limit
					),
					ARRAY_A
				);

			case 'browsers':
				$dim = TableRegistry::get( 'device_browsers' );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT d.name,
							COUNT(DISTINCT s.visitor_id) AS visitors,
							COUNT(DISTINCT s.ID) AS sessions
						FROM %i s
						INNER JOIN %i d ON s.device_browser_id = d.ID
						WHERE s.started_at BETWEEN %s AND %s
						GROUP BY d.name
						ORDER BY visitors DESC LIMIT %d',
						$sessions,
						$dim,
						$from . ' 00:00:00',
						$to . ' 23:59:59',
						$limit
					),
					ARRAY_A
				);

			case 'oss':
				$dim = TableRegistry::get( 'device_oss' );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT d.name,
							COUNT(DISTINCT s.visitor_id) AS visitors,
							COUNT(DISTINCT s.ID) AS sessions
						FROM %i s
						INNER JOIN %i d ON s.device_os_id = d.ID
						WHERE s.started_at BETWEEN %s AND %s
						GROUP BY d.name
						ORDER BY visitors DESC LIMIT %d',
						$sessions,
						$dim,
						$from . ' 00:00:00',
						$to . ' 23:59:59',
						$limit
					),
					ARRAY_A
				);

			case 'devices':
				$dim = TableRegistry::get( 'device_types' );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT d.name,
							COUNT(DISTINCT s.visitor_id) AS visitors,
							COUNT(DISTINCT s.ID) AS sessions
						FROM %i s
						INNER JOIN %i d ON s.device_type_id = d.ID
						WHERE s.started_at BETWEEN %s AND %s
						GROUP BY d.name
						ORDER BY visitors DESC LIMIT %d',
						$sessions,
						$dim,
						$from . ' 00:00:00',
						$to . ' 23:59:59',
						$limit
					),
					ARRAY_A
				);

			case 'languages':
				$dim = TableRegistry::get( 'languages' );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT d.code AS name,
							COUNT(DISTINCT s.visitor_id) AS visitors,
							COUNT(DISTINCT s.ID) AS sessions
						FROM %i s
						INNER JOIN %i d ON s.language_id = d.ID
						WHERE s.started_at BETWEEN %s AND %s
						GROUP BY d.code
						ORDER BY visitors DESC LIMIT %d',
						$sessions,
						$dim,
						$from . ' 00:00:00',
						$to . ' 23:59:59',
						$limit
					),
					ARRAY_A
				);

			default:
				return [];
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
