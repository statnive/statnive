<?php

declare(strict_types=1);

namespace Statnive\Addon\RestApi;

use Statnive\Feature\FeatureGate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API add-on module.
 *
 * Provides external API access with API key authentication
 * and rate limiting for headless/external integrations.
 * Gated by 'rest_api' feature — requires Professional tier or above.
 */
final class RestApiModule {

	/**
	 * Initialize the module if the feature is available.
	 */
	public static function init(): void {
		if ( ! FeatureGate::can( 'rest_api' ) ) {
			return;
		}

		add_filter( 'determine_current_user', [ self::class, 'authenticate_api_key' ], 20 );
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Authenticate requests using Bearer API key.
	 *
	 * @param int|false $user_id Current user ID or false.
	 * @return int|false User ID if authenticated, or unchanged.
	 */
	public static function authenticate_api_key( $user_id ) {
		// Don't override if already authenticated.
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		// Only handle statnive API requests.
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( ! str_contains( $request_uri, 'statnive/v1/' ) ) {
			return $user_id;
		}

		// Check for Bearer token.
		$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ?? '' ) );
		if ( empty( $auth_header ) || ! str_starts_with( $auth_header, 'Bearer ' ) ) {
			return $user_id;
		}

		$api_key = substr( $auth_header, 7 );
		return ApiKeyManager::validate_key( $api_key );
	}

	/**
	 * Register API key management routes.
	 */
	public static function register_routes(): void {
		$controller = new ApiKeysController();
		$controller->register_routes();
	}
}
