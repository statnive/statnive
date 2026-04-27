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
	 *
	 * Two-stage loading architecture for optimal Web Vitals:
	 *
	 * Stage 1 (inline, ~500B): Fires the pageview hit immediately from an inline
	 *   script in wp_footer. Zero external requests in the critical rendering path.
	 *
	 * Stage 2 (async external): Loads the full tracker for engagement tracking,
	 *   auto-tracking, custom events, and consent management. Downloads in parallel,
	 *   doesn't affect FCP/LCP.
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

		$tracker_path = STATNIVE_PATH . 'public/tracker/statnive.js';
		$core_path    = STATNIVE_PATH . 'public/tracker/statnive-core.js';

		// Only enqueue if the built file exists.
		if ( ! file_exists( $tracker_path ) ) {
			return;
		}

		// Build configuration (shared between both stages).
		$config = self::build_config();
		$json   = wp_json_encode( $config, JSON_UNESCAPED_SLASHES );

		// Stage 1: Inline core tracker in wp_footer for immediate pageview.
		// Zero external requests — pageview fires from inline script.
		if ( file_exists( $core_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$core_js = file_get_contents( $core_path );
			if ( false !== $core_js ) {
				add_action(
					'wp_footer',
					static function () use ( $json, $core_js ): void {
						wp_print_inline_script_tag(
							'window.StatniveConfig=' . $json . ';' . $core_js
						);
					},
					20
				);
			}
		}

		// Stage 2: Full tracker (async) for engagement, events, auto-tracking.
		// The full tracker detects window.statnive_hit_sent and skips the pageview,
		// only initializing deferred modules.
		$tracker_url = plugins_url( 'public/tracker/statnive.js', STATNIVE_FILE );

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$tracker_url,
			[],
			STATNIVE_VERSION,
			[
				'in_footer' => true,
				'strategy'  => 'async',
			]
		);

		// Inject config via inline 'before' for the async tracker too.
		// MUST use 'before' position — 'after' cascades to blocking.
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.StatniveConfig=window.StatniveConfig||' . $json . ';',
			'before'
		);
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

		// Compute SRI hash (cached in transient, keyed by file modification time).
		$mtime     = (int) filemtime( $tracker_path );
		$cache_key = 'statnive_sri_' . $mtime;
		$hash      = get_transient( $cache_key );

		if ( false === $hash ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $tracker_path );
			if ( false === $content ) {
				return $tag;
			}
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Subresource Integrity hashes must be base64 encoded per the SRI spec.
			$hash = 'sha256-' . base64_encode( hash( 'sha256', $content, true ) );
			set_transient( $cache_key, $hash, MONTH_IN_SECONDS );
		}

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
