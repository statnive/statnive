<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Service\GeoIPDownloader;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for plugin settings.
 *
 * Endpoints:
 * - GET  /wp-json/statnive/v1/settings
 * - PUT  /wp-json/statnive/v1/settings
 */
final class SettingsController extends WP_REST_Controller {

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
	protected $rest_base = 'settings';

	/**
	 * Allowed option keys for settings.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_KEYS = [
		'statnive_tracking_enabled',
		'statnive_respect_dnt',
		'statnive_respect_gpc',
		'statnive_consent_mode',
		'statnive_retention_days',
		'statnive_retention_mode',
		'statnive_excluded_ips',
		'statnive_excluded_roles',
		'statnive_email_reports',
		'statnive_email_frequency',
		'statnive_geoip_enabled',
		'statnive_maxmind_license_key',
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'permissions_check' ],
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
	public function permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all plugin settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$has_license_key = '' !== get_option( 'statnive_maxmind_license_key', '' );

		return new WP_REST_Response(
			[
				'tracking_enabled'    => (bool) get_option( 'statnive_tracking_enabled', true ),
				'respect_dnt'         => (bool) get_option( 'statnive_respect_dnt', true ),
				'respect_gpc'         => (bool) get_option( 'statnive_respect_gpc', true ),
				'consent_mode'        => get_option( 'statnive_consent_mode', 'cookieless' ),
				'retention_days'      => (int) get_option( 'statnive_retention_days', 90 ),
				'retention_mode'      => get_option( 'statnive_retention_mode', 'delete' ),
				'excluded_ips'        => get_option( 'statnive_excluded_ips', '' ),
				'excluded_roles'      => get_option( 'statnive_excluded_roles', [] ),
				'email_reports'       => (bool) get_option( 'statnive_email_reports', false ),
				'email_frequency'     => get_option( 'statnive_email_frequency', 'weekly' ),
				'geoip_enabled'       => (bool) get_option( 'statnive_geoip_enabled', false ),
				'maxmind_license_key' => $has_license_key ? '********' : '',
			],
			200
		);
	}

	/**
	 * Update plugin settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( [ 'message' => 'Invalid body.' ], 400 );
		}

		$map = [
			'tracking_enabled'    => 'statnive_tracking_enabled',
			'respect_dnt'         => 'statnive_respect_dnt',
			'respect_gpc'         => 'statnive_respect_gpc',
			'consent_mode'        => 'statnive_consent_mode',
			'retention_days'      => 'statnive_retention_days',
			'retention_mode'      => 'statnive_retention_mode',
			'excluded_ips'        => 'statnive_excluded_ips',
			'excluded_roles'      => 'statnive_excluded_roles',
			'email_reports'       => 'statnive_email_reports',
			'email_frequency'     => 'statnive_email_frequency',
			'geoip_enabled'       => 'statnive_geoip_enabled',
			'maxmind_license_key' => 'statnive_maxmind_license_key',
		];

		// Process maxmind_license_key first so geoip_enabled can check it.
		if ( isset( $body['maxmind_license_key'] ) && '********' !== $body['maxmind_license_key'] ) {
			update_option( 'statnive_maxmind_license_key', sanitize_text_field( (string) $body['maxmind_license_key'] ) );
		}

		// Validate GeoIP enable requires a license key.
		if ( isset( $body['geoip_enabled'] ) && $body['geoip_enabled'] ) {
			$key = get_option( 'statnive_maxmind_license_key', '' );
			if ( '' === $key ) {
				return new WP_REST_Response(
					[
						'code'    => 'missing_license_key',
						'message' => 'A MaxMind license key is required to enable GeoIP.',
					],
					400
				);
			}
		}

		foreach ( $body as $key => $value ) {
			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}

			// License key already processed above.
			if ( 'maxmind_license_key' === $key ) {
				continue;
			}

			$option_key = $map[ $key ];
			if ( ! in_array( $option_key, self::ALLOWED_KEYS, true ) ) {
				continue;
			}

			$sanitized = $this->sanitize_setting( $key, $value );

			// Trigger GeoIP enable/disable side effects.
			if ( 'geoip_enabled' === $key ) {
				$was_enabled = (bool) get_option( 'statnive_geoip_enabled', false );
				if ( $sanitized && ! $was_enabled ) {
					GeoIPDownloader::enable();
					continue; // enable() already sets the option.
				} elseif ( ! $sanitized && $was_enabled ) {
					GeoIPDownloader::disable();
					continue; // disable() already sets the option.
				}
			}

			update_option( $option_key, $sanitized );
		}

		return $this->get_settings( $request );
	}

	/**
	 * Sanitize a setting value by key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_setting( string $key, mixed $value ): mixed {
		return match ( $key ) {
			'tracking_enabled', 'respect_dnt', 'respect_gpc', 'email_reports', 'geoip_enabled' => (bool) $value,
			'retention_days' => max( 30, min( absint( $value ), 3650 ) ),
			'excluded_ips' => sanitize_textarea_field( (string) $value ),
			'excluded_roles' => is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [],
			'consent_mode' => in_array( $value, [ 'full', 'cookieless', 'disabled-until-consent' ], true ) ? $value : 'cookieless',
			'retention_mode' => in_array( $value, [ 'forever', 'delete', 'archive' ], true ) ? $value : 'delete',
			'email_frequency' => in_array( $value, [ 'weekly', 'monthly' ], true ) ? $value : 'weekly',
			'maxmind_license_key' => sanitize_text_field( (string) $value ),
			default => sanitize_text_field( (string) $value ),
		};
	}
}
