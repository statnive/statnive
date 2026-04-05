<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Database\TableRegistry;
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
		$body = $request->get_body();
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( null, 400 );
		}

		$signature = sanitize_text_field( $data['signature'] ?? '' );
		$res_type  = sanitize_text_field( $data['resource_type'] ?? '' );
		$res_id    = absint( $data['resource_id'] ?? 0 );

		if ( ! HmacValidator::verify( $signature, $res_type, $res_id ) ) {
			return new WP_REST_Response( null, 403 );
		}

		$engagement_time = absint( $data['engagement_time'] ?? 0 );
		$scroll_depth    = min( absint( $data['scroll_depth'] ?? 0 ), 100 );

		if ( 0 === $engagement_time && 0 === $scroll_depth ) {
			return new WP_REST_Response( null, 204 );
		}

		global $wpdb;
		$views_table = TableRegistry::get( 'views' );
		$uris_table  = TableRegistry::get( 'resource_uris' );

		$page_url = sanitize_text_field( $data['page_url'] ?? '' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $page_url ) ) {
			// URI-based lookup — unique per path, avoids resource_id=0 collisions.
			$uri_hash = crc32( $page_url );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$views_table}`
					SET duration = %d, scroll_depth = %d
					WHERE ID = (
						SELECT max_id FROM (
							SELECT MAX(v.ID) AS max_id
							FROM `{$views_table}` v
							INNER JOIN `{$uris_table}` ru ON v.resource_uri_id = ru.ID
							WHERE ru.uri_hash = %d AND ru.uri = %s
						) AS subq
					)",
					$engagement_time,
					$scroll_depth,
					$uri_hash,
					$page_url
				)
			);
		} else {
			// Fallback: resource_id lookup (backward compat with old tracker versions).
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$views_table}`
					SET duration = %d, scroll_depth = %d
					WHERE ID = (
						SELECT max_id FROM (
							SELECT MAX(ID) AS max_id FROM `{$views_table}`
							WHERE resource_uri_id = (
								SELECT ID FROM `{$uris_table}` WHERE resource_id = %d LIMIT 1
							)
						) AS subq
					)",
					$engagement_time,
					$scroll_depth,
					$res_id
				)
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return new WP_REST_Response( null, 204 );
	}
}
