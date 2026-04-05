<?php

declare(strict_types=1);

namespace Statnive\Frontend;

use Statnive\Security\HmacValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend script handler.
 *
 * Enqueues the tracker script on frontend pages with SRI hash,
 * localized configuration, and HMAC signature.
 */
final class FrontendHandler {

	/**
	 * Script handle for the tracker.
	 *
	 * @var string
	 */
	private const SCRIPT_HANDLE = 'statnive-tracker';

	/**
	 * Initialize the frontend handler.
	 *
	 * Hooks into wp_enqueue_scripts to load the tracker.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_tracker' ] );
		add_filter( 'script_loader_tag', [ self::class, 'add_sri_attribute' ], 10, 2 );
	}

	/**
	 * Enqueue the tracker script on frontend pages.
	 */
	public static function enqueue_tracker(): void {
		// Don't track if tracking is disabled.
		if ( ! get_option( 'statnive_tracking_enabled', true ) ) {
			return;
		}

		// Don't track admin pages.
		if ( is_admin() ) {
			return;
		}

		$tracker_url  = plugins_url( 'public/tracker/statnive.js', STATNIVE_FILE );
		$tracker_path = STATNIVE_PATH . 'public/tracker/statnive.js';

		// Only enqueue if the built file exists.
		if ( ! file_exists( $tracker_path ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$tracker_url,
			[],
			STATNIVE_VERSION,
			[ 'in_footer' => true ]
		);

		// Build and inject configuration.
		$config = self::build_config();
		wp_localize_script( self::SCRIPT_HANDLE, 'StatniveConfig', $config );
	}

	/**
	 * Add SRI integrity attribute to the tracker script tag.
	 *
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public static function add_sri_attribute( string $tag, string $handle ): string {
		if ( self::SCRIPT_HANDLE !== $handle ) {
			return $tag;
		}

		$tracker_path = STATNIVE_PATH . 'public/tracker/statnive.js';
		if ( ! file_exists( $tracker_path ) ) {
			return $tag;
		}

		// Compute SRI hash.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $tracker_path );
		if ( false === $content ) {
			return $tag;
		}

		$hash = 'sha256-' . base64_encode( hash( 'sha256', $content, true ) );

		// Add integrity and crossorigin attributes.
		$tag = str_replace(
			' src=',
			' integrity="' . esc_attr( $hash ) . '" crossorigin="anonymous" src=',
			$tag
		);

		return $tag;
	}

	/**
	 * Build the localized configuration for the tracker script.
	 *
	 * @return array<string, mixed> Configuration array.
	 */
	private static function build_config(): array {
		// Determine the current resource context.
		$resource_type = 'page';
		$resource_id   = 0;

		$queried_object = get_queried_object();
		if ( $queried_object instanceof \WP_Post ) {
			$resource_type = $queried_object->post_type;
			$resource_id   = $queried_object->ID;
		}

		// Generate HMAC signature for this resource.
		$signature = HmacValidator::generate( $resource_type, $resource_id );

		return [
			'restUrl'       => esc_url_raw( rest_url( 'statnive/v1/hit' ) ),
			'eventUrl'      => esc_url_raw( rest_url( 'statnive/v1/event' ) ),
			'engagementUrl' => esc_url_raw( rest_url( 'statnive/v1/engagement' ) ),
			'ajaxUrl'       => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'hitParams'     => [
				'resource_type' => $resource_type,
				'resource_id'   => (string) $resource_id,
				'signature'     => $signature,
			],
			'options'       => [
				'dntEnabled'  => (bool) get_option( 'statnive_respect_dnt', true ),
				'gpcEnabled'  => (bool) get_option( 'statnive_respect_gpc', true ),
				'useAjax'     => (bool) get_option( 'statnive_use_ajax_fallback', false ),
				'consentMode' => get_option( 'statnive_consent_mode', 'cookieless' ),
				'autoTrack'   => true,
			],
		];
	}
}
