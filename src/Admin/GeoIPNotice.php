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
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong></p><p>%s</p><p><em>%s</em></p><p>%s</p></div>',
			esc_html__( 'Statnive: GeoIP is enabled but no MaxMind license key is configured.', 'statnive' ),
			esc_html__( 'Impact: visitor country/region data will not appear in your reports. Page tracking, sources, devices and all other metrics continue to work normally.', 'statnive' ),
			esc_html__( 'What Statnive will do: retry the GeoIP database download every week. No additional alerts will be raised until the key is added or GeoIP is disabled.', 'statnive' ),
			wp_kses(
				sprintf(
					/* translators: 1: MaxMind signup URL, 2: MaxMind EULA URL */
					__( '<strong>To fix:</strong> <a href="%1$s" target="_blank" rel="noopener">get a free MaxMind license key</a> (requires accepting the <a href="%2$s" target="_blank" rel="noopener">GeoLite2 EULA</a>) and paste it into Settings → GeoIP. Or disable GeoIP in Settings to dismiss this notice.', 'statnive' ),
					'https://www.maxmind.com/en/geolite2/signup',
					'https://www.maxmind.com/en/geolite2/eula'
				),
				[
					'a'      => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
					'strong' => [],
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
