<?php

declare(strict_types=1);

namespace Statnive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin menu registration.
 *
 * Registers the top-level "Statnive" admin menu item and renders
 * the container div for the React SPA.
 */
final class AdminMenuManager {

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
	}

	/**
	 * Register the Statnive admin menu page.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'Statnive Analytics', 'statnive' ),
			__( 'Statnive', 'statnive' ),
			'manage_options',
			'statnive',
			[ self::class, 'render_page' ],
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2E3YWFhZCI+PHBhdGggZD0iTTIgMTFoMy41TDkgMmw2IDIwIDMuNS0xMUgyMnYyaC0zLjVMMTUgMjAgOSA0IDUuNSAxM0gyeiIvPjwvc3ZnPg==',
			26
		);
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
	 * Check if the current admin page is the Statnive page.
	 *
	 * @return bool True if on the Statnive admin page.
	 */
	public static function is_statnive_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		return 'statnive' === $page;
	}
}
