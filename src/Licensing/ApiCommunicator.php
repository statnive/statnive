<?php

declare(strict_types=1);

namespace Statnive\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License API client.
 *
 * Communicates with the Statnive license server to validate,
 * activate, and deactivate license keys.
 * Uses wp_remote_post() — never cURL directly.
 */
final class ApiCommunicator {

	/**
	 * License API base URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://api.statnive.com/v1/licenses';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 5;

	/**
	 * Validate a license key against the API.
	 *
	 * @param string $key License key.
	 * @return LicenseStatus Validation result.
	 */
	public static function validate_license( string $key ): LicenseStatus {
		return self::api_request( '/validate', $key );
	}

	/**
	 * Activate a license key for this site.
	 *
	 * @param string $key License key.
	 * @return LicenseStatus Activation result.
	 */
	public static function activate_license( string $key ): LicenseStatus {
		return self::api_request( '/activate', $key );
	}

	/**
	 * Deactivate a license key for this site.
	 *
	 * @param string $key License key.
	 * @return LicenseStatus Deactivation result.
	 */
	public static function deactivate_license( string $key ): LicenseStatus {
		return self::api_request( '/deactivate', $key );
	}

	/**
	 * Make an API request to the license server.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param string $key      License key.
	 * @return LicenseStatus Parsed response.
	 */
	private static function api_request( string $endpoint, string $key ): LicenseStatus {
		$masked = strlen( $key ) >= 4 ? '****-' . substr( $key, -4 ) : '';

		$response = wp_remote_post(
			self::API_BASE . $endpoint,
			[
				'timeout' => self::TIMEOUT,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode(
					[
						'license_key' => $key,
						'site_url'    => get_site_url(),
						'plugin_ver'  => defined( 'STATNIVE_VERSION' ) ? STATNIVE_VERSION : '0.0.0',
					]
				),
			]
		);

		// Network error — graceful degradation.
		if ( is_wp_error( $response ) ) {
			self::log( 'API request failed: ' . $response->get_error_message() );
			return LicenseStatus::error();
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			self::log( 'API returned HTTP ' . $code );
			return LicenseStatus::error();
		}

		$status = $data['status'] ?? '';

		return match ( $status ) {
			'valid'   => LicenseStatus::valid(
				$data['plan'] ?? 'free',
				$data['expires_at'] ?? '',
				$masked
			),
			'expired' => LicenseStatus::expired( $masked ),
			'invalid' => LicenseStatus::invalid(),
			default   => LicenseStatus::error(),
		};
	}

	/**
	 * Log an API communication error.
	 *
	 * @param string $message Error message.
	 */
	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Statnive][License] ' . $message );
		}
	}
}
