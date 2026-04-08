<?php

declare(strict_types=1);

namespace Statnive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notices for GeoIP configuration.
 *
 * Shows dismissible notices when:
 * - GeoIP is enabled but no MaxMind license key is configured.
 * - WP-Cron is disabled while GeoIP is active.
 */
final class GeoIPNotice {

	/**
	 * Hook into WordPress admin notices.
	 */
	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'maybe_show_notices' ] );
	}

	/**
	 * Display GeoIP-related admin notices on Statnive pages only.
	 */
	public static function maybe_show_notices(): void {
		$screen = get_current_screen();
		if ( null === $screen || ReactHandler::HOOK_SUFFIX !== $screen->id ) {
			return;
		}

		if ( ! (bool) get_option( 'statnive_geoip_enabled', false ) ) {
			return;
		}

		self::maybe_show_license_notice();
		self::maybe_show_cron_notice();
	}

	/**
	 * Show notice when GeoIP is enabled without a MaxMind license key.
	 */
	private static function maybe_show_license_notice(): void {
		$license_key = get_option( 'statnive_maxmind_license_key', '' );
		if ( '' !== $license_key ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p><p>%s</p></div>',
			esc_html__( 'Statnive: GeoIP is enabled but no MaxMind license key is configured. Visitor geolocation will not work until a key is added.', 'statnive' ),
			wp_kses(
				sprintf(
					/* translators: 1: MaxMind signup URL, 2: MaxMind EULA URL */
					__( '<a href="%1$s" target="_blank" rel="noopener">Get a free MaxMind license key</a> (requires accepting the <a href="%2$s" target="_blank" rel="noopener">GeoLite2 EULA</a>).', 'statnive' ),
					'https://www.maxmind.com/en/geolite2/signup',
					'https://www.maxmind.com/en/geolite2/eula'
				),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			)
		);
	}

	/**
	 * Show notice when WP-Cron is disabled and GeoIP is active.
	 */
	private static function maybe_show_cron_notice(): void {
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html__( 'Statnive: WordPress cron is disabled on this site (DISABLE_WP_CRON). GeoIP database updates will not run automatically. Set up a system cron job or use WP-CLI to update the database manually.', 'statnive' )
		);
	}
}
