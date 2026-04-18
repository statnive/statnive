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

	private const MENU_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
		. '<path d="M 10 82 L 50 24" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>'
		. '<path d="M 50 24 L 92 82" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>'
		. '<circle cx="50" cy="22" r="7.5" fill="#00A693"/>'
		. '</svg>';

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
			'data:image/svg+xml;base64,' . base64_encode( self::MENU_ICON_SVG ),
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
