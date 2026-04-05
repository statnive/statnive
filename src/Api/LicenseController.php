<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Licensing\ApiCommunicator;
use Statnive\Licensing\LicenseHelper;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for license management.
 *
 * Endpoints:
 * - POST   /wp-json/statnive/v1/license         — activate a license key
 * - DELETE  /wp-json/statnive/v1/license         — deactivate the current license
 * - GET    /wp-json/statnive/v1/license/status   — get cached license status
 */
final class LicenseController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'license';

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
					'callback'            => [ $this, 'activate' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deactivate' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'status' ],
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
	 * Activate a license key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function activate( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$key  = sanitize_text_field( $body['license_key'] ?? '' );

		if ( empty( $key ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'missing_key',
					'message' => 'License key is required.',
				],
				400
			);
		}

		// Validate with API.
		$result = ApiCommunicator::activate_license( $key );

		if ( $result->is_active() ) {
			LicenseHelper::store_license( $key );
			LicenseHelper::cache_status( $result );
		}

		return new WP_REST_Response( $result->to_array(), $result->is_active() ? 200 : 400 );
	}

	/**
	 * Deactivate the current license.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function deactivate( WP_REST_Request $request ): WP_REST_Response {
		$key = LicenseHelper::get_license_key();

		if ( null !== $key ) {
			ApiCommunicator::deactivate_license( $key );
		}

		LicenseHelper::remove_license();

		return new WP_REST_Response( LicenseHelper::get_cached_status()->to_array(), 200 );
	}

	/**
	 * Get current license status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( LicenseHelper::get_cached_status()->to_array(), 200 );
	}
}
