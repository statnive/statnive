<?php

declare(strict_types=1);

namespace Statnive\Admin;

use Statnive\Capability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin menu registration for the React SPA shell.
 */
final class AdminMenuManager {

	/** First submenu reuses parent slug to suppress WP's duplicated parent label. */
	public const MENU_SLUG_OVERVIEW = 'statnive';
	public const MENU_SLUG_ASK      = 'statnive-ask';
	public const MENU_SLUG_REVENUE  = 'statnive-revenue';
	public const MENU_SLUG_SETTINGS = 'statnive-settings';

	/** SPA hash-routes — match TanStack Router paths in resources/react/app.tsx. */
	private const ROUTE_OVERVIEW = '/';
	private const ROUTE_ASK      = '/ask';
	private const ROUTE_REVENUE  = '/revenue';
	private const ROUTE_SETTINGS = '/settings';

	private const MENU_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
		. '<path d="M 10 82 L 50 24" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>'
		. '<path d="M 50 24 L 92 82" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>'
		. '<circle cx="50" cy="22" r="7.5" fill="currentColor"/>'
		. '</svg>';

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_menu_icon_style' ] );
	}

	/** Register the Statnive admin menu pages. */
	public static function register_menu(): void {
		add_menu_page(
			__( 'Statnive Analytics', 'statnive' ),
			__( 'Statnive', 'statnive' ),
			Capability::VIEW_REPORTS,
			self::MENU_SLUG_OVERVIEW,
			[ self::class, 'render_page' ],
			self::menu_icon_data_uri(),
			26
		);

		add_submenu_page(
			self::MENU_SLUG_OVERVIEW,
			__( 'Overview', 'statnive' ),
			__( 'Overview', 'statnive' ),
			Capability::VIEW_REPORTS,
			self::MENU_SLUG_OVERVIEW,
			[ self::class, 'render_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG_OVERVIEW,
			__( 'Ask me!', 'statnive' ),
			__( 'Ask me!', 'statnive' ),
			Capability::VIEW_REPORTS,
			self::MENU_SLUG_ASK,
			[ self::class, 'render_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG_OVERVIEW,
			__( 'Revenue Report', 'statnive' ),
			__( 'Revenue Report', 'statnive' ),
			Capability::VIEW_REPORTS,
			self::MENU_SLUG_REVENUE,
			[ self::class, 'render_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG_OVERVIEW,
			__( 'Settings', 'statnive' ),
			__( 'Settings', 'statnive' ),
			Capability::VIEW_REPORTS,
			self::MENU_SLUG_SETTINGS,
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Paint both the admin-menu icon and the admin-bar icon through a
	 * CSS mask so each picks up its host link's `currentColor`
	 * (matching Dashboard / Posts / other bar items). `currentColor`
	 * inside a data-URI SVG used as `background-image` doesn't inherit
	 * from the host element; mask-image + `background-color:
	 * currentColor` gives us that inheritance. Selectors are
	 * statnive-prefixed so this is safe against the admin asset-scoping
	 * rule.
	 */
	public static function enqueue_menu_icon_style(): void {
		if ( ! Capability::can_view_reports() ) {
			return;
		}

		$handle = 'statnive-admin-menu-icon';
		wp_register_style( $handle, false, [], STATNIVE_VERSION );
		wp_enqueue_style( $handle );

		$svg_data_uri = self::menu_icon_data_uri();

		$css = '#toplevel_page_statnive .wp-menu-image {'
			. 'background-image: none !important;'
			. 'background-color: currentColor;'
			. '-webkit-mask-image: url("' . $svg_data_uri . '");'
			. 'mask-image: url("' . $svg_data_uri . '");'
			. '-webkit-mask-repeat: no-repeat;'
			. 'mask-repeat: no-repeat;'
			. '-webkit-mask-position: center 8px;'
			. 'mask-position: center 8px;'
			. '-webkit-mask-size: 20px auto;'
			. 'mask-size: 20px auto;'
			. '}'
			. '#wp-admin-bar-statnive-stats #statnive-bar-chart {'
			. 'display: inline-flex;'
			. 'align-items: center;'
			. 'gap: 6px;'
			. '}'
			. '#wp-admin-bar-statnive-stats #statnive-bar-icon {'
			. 'display: inline-block;'
			. 'width: 20px;'
			. 'height: 20px;'
			. 'background-color: currentColor;'
			. '-webkit-mask-image: url("' . $svg_data_uri . '");'
			. 'mask-image: url("' . $svg_data_uri . '");'
			. '-webkit-mask-repeat: no-repeat;'
			. 'mask-repeat: no-repeat;'
			. '-webkit-mask-position: center;'
			. 'mask-position: center;'
			. '-webkit-mask-size: contain;'
			. 'mask-size: contain;'
			. '}';

		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Base64 data URI for the menu-icon SVG. Shared by `register_menu()`
	 * (where WordPress uses it as the menu icon's background-image) and
	 * `enqueue_menu_icon_style()` (where we paint it through a CSS mask).
	 */
	private static function menu_icon_data_uri(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI encoding, not obfuscation.
		return 'data:image/svg+xml;base64,' . base64_encode( self::MENU_ICON_SVG );
	}

	/**
	 * Render the admin page container.
	 *
	 * Outputs a single div that the React SPA mounts into.
	 */
	public static function render_page(): void {
		echo wp_kses_post( '<div id="statnive-app"></div>' );
	}

	/**
	 * Check if the current admin page is any of the Statnive admin pages.
	 *
	 * @return bool True if on the Statnive admin page (overview, revenue, or settings).
	 */
	public static function is_statnive_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		return in_array(
			$page,
			[ self::MENU_SLUG_OVERVIEW, self::MENU_SLUG_ASK, self::MENU_SLUG_REVENUE, self::MENU_SLUG_SETTINGS ],
			true
		);
	}

	/**
	 * Map the active `?page=` slug to an initial SPA hash-route.
	 *
	 * Injected as `window.StatniveDashboard.initialRoute` so the SPA
	 * boots into the submenu the user clicked instead of always "/".
	 *
	 * @return string Initial route — defaults to "/" off-Statnive pages.
	 */
	public static function current_initial_route(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		switch ( $page ) {
			case self::MENU_SLUG_ASK:
				return self::ROUTE_ASK;
			case self::MENU_SLUG_REVENUE:
				return self::ROUTE_REVENUE;
			case self::MENU_SLUG_SETTINGS:
				return self::ROUTE_SETTINGS;
			case self::MENU_SLUG_OVERVIEW:
			default:
				return self::ROUTE_OVERVIEW;
		}
	}
}
