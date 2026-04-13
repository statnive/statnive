<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Database\TableRegistry;
use Statnive\Http\PayloadValidator;
use Statnive\Http\PayloadValidatorException;
use Statnive\Security\HmacValidator;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for deferred engagement data.
 *
 * Endpoint: POST /wp-json/statnive/v1/engagement
 * Updates the most recent view's duration and scroll_depth.
 */
final class EngagementController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'engagement';

	/**
	 * Allowed top-level payload keys.
	 *
	 * Engagement intentionally omits 'consent_granted' — the tracker only
	 * sends engagement updates after consent has already been granted and
	 * a matching hit has been recorded.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_KEYS = [
		'signature',
		'resource_type',
		'resource_id',
		'engagement_time',
		'scroll_depth',
		'pvid',
		'page_url',
	];

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'signature'       => [
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'resource_type'   => [
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'resource_id'     => [
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						],
						'engagement_time' => [
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						],
						'scroll_depth'    => [
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						],
						'pvid'            => [
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'page_url'        => [
							'type'              => 'string',
							'format'            => 'uri',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'esc_url_raw',
						],
					],
				],
			]
		);
	}

	/**
	 * Handle engagement data.
	 *
	 * @param WP_REST_Request $request Full request object.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ): WP_REST_Response {
		$ct_error = PayloadValidator::validate_content_type( $request );
		if ( null !== $ct_error ) {
			return self::error_response( $ct_error );
		}

		$body = $request->get_body();

		$size_error = PayloadValidator::validate_body_size( $body );
		if ( null !== $size_error ) {
			return self::error_response( $size_error );
		}

		try {
			$data = PayloadValidator::decode_json_object( $body );
		} catch ( PayloadValidatorException $e ) {
			return self::error_response( $e->to_tuple() );
		}

		$keys_error = PayloadValidator::validate_allowed_keys( $data, self::ALLOWED_KEYS );
		if ( null !== $keys_error ) {
			return self::error_response( $keys_error );
		}

		// Validate page_url host against site origin.
		if ( ! empty( $data['page_url'] ) && ! HitController::validate_page_url_host( (string) $data['page_url'] ) ) {
			return self::error_response( [ 'invalid_host', 'page_url host does not match this site.', 400 ] );
		}

		$signature = sanitize_text_field( $data['signature'] ?? '' );
		$res_type  = sanitize_text_field( $data['resource_type'] ?? '' );
		$res_id    = absint( $data['resource_id'] ?? 0 );

		if ( ! HmacValidator::verify( $signature, $res_type, $res_id ) ) {
			return self::error_response( [ 'invalid_signature', 'Request signature is invalid.', 403 ] );
		}

		$engagement_time = absint( $data['engagement_time'] ?? 0 );
		$scroll_depth    = min( absint( $data['scroll_depth'] ?? 0 ), 100 );

		if ( 0 === $engagement_time && 0 === $scroll_depth ) {
			return new WP_REST_Response( null, 204 );
		}

		global $wpdb;
		$views_table = TableRegistry::get( 'views' );
		$uris_table  = TableRegistry::get( 'resource_uris' );

		$pvid     = sanitize_text_field( $data['pvid'] ?? '' );
		$page_url = sanitize_text_field( $data['page_url'] ?? '' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $pvid ) && strlen( $pvid ) <= 16 ) {
			// Exact match via page visit ID — each pageview gets its own duration.
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET duration = %d, scroll_depth = %d WHERE pvid = %s',
					$views_table,
					$engagement_time,
					$scroll_depth,
					$pvid
				)
			);
		} elseif ( ! empty( $page_url ) ) {
			// Fallback: URI-based lookup for tracker versions without pvid.
			$uri_hash = crc32( $page_url );
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i
					SET duration = %d, scroll_depth = %d
					WHERE ID = (
						SELECT max_id FROM (
							SELECT MAX(v.ID) AS max_id
							FROM %i v
							INNER JOIN %i ru ON v.resource_uri_id = ru.ID
							WHERE ru.uri_hash = %d AND ru.uri = %s
						) AS subq
					)',
					$views_table,
					$engagement_time,
					$scroll_depth,
					$views_table,
					$uris_table,
					$uri_hash,
					$page_url
				)
			);
		} else {
			// Legacy fallback: resource_id lookup.
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i
					SET duration = %d, scroll_depth = %d
					WHERE ID = (
						SELECT max_id FROM (
							SELECT MAX(ID) AS max_id FROM %i
							WHERE resource_uri_id = (
								SELECT ID FROM %i WHERE resource_id = %d LIMIT 1
							)
						) AS subq
					)',
					$views_table,
					$engagement_time,
					$scroll_depth,
					$views_table,
					$uris_table,
					$res_id
				)
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Translate an error tuple into a WP_REST_Response.
	 *
	 * @param array{0: string, 1: string, 2: int} $tuple [code, message, status].
	 * @return WP_REST_Response
	 */
	private static function error_response( array $tuple ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'code'    => $tuple[0],
				'message' => $tuple[1],
			],
			$tuple[2]
		);
	}
}
