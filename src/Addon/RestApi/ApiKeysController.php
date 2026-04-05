<?php

declare(strict_types=1);

namespace Statnive\Addon\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for API key management.
 *
 * Endpoints:
 * - GET    /wp-json/statnive/v1/api-keys       — list keys (metadata only)
 * - POST   /wp-json/statnive/v1/api-keys       — generate a new key
 * - DELETE  /wp-json/statnive/v1/api-keys/{id}  — revoke a key
 */
final class ApiKeysController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'api-keys';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-f0-9-]+)',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Permission check.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List API keys.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		return new WP_REST_Response( ApiKeyManager::list_keys(), 200 );
	}

	/**
	 * Generate a new API key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$name = sanitize_text_field( $body['name'] ?? '' );

		if ( empty( $name ) ) {
			return new WP_REST_Response( [ 'message' => 'Key name is required.' ], 400 );
		}

		$result = ApiKeyManager::generate_key( $name, get_current_user_id() );

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Revoke an API key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ): WP_REST_Response {
		$id      = sanitize_text_field( $request->get_param( 'id' ) );
		$revoked = ApiKeyManager::revoke_key( $id );

		if ( ! $revoked ) {
			return new WP_REST_Response( [ 'message' => 'Key not found.' ], 404 );
		}

		return new WP_REST_Response( null, 204 );
	}
}
