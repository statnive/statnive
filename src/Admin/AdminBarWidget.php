<?php

declare(strict_types=1);

namespace Statnive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin bar mini-chart widget.
 *
 * Adds a small sparkline of today's visitors to the WordPress admin bar.
 * Only visible to users with manage_options capability.
 */
final class AdminBarWidget {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_bar_menu', [ self::class, 'add_node' ], 100 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Add the admin bar node.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function add_node( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$span_allowed = [
			'span' => [
				'id'          => [],
				'style'       => [],
				'aria-hidden' => [],
			],
		];

		$title_html = '<span id="statnive-bar-chart" style="display:inline-flex;align-items:center;gap:6px;">'
			. '<span id="statnive-bar-icon" aria-hidden="true"></span>'
			. '<span id="statnive-bar-count">—</span>'
			. '</span>';

		$wp_admin_bar->add_node(
			[
				'id'    => 'statnive-stats',
				'title' => wp_kses( $title_html, $span_allowed ),
				'href'  => admin_url( 'admin.php?page=statnive' ),
				'meta'  => [
					'title' => __( 'Statnive — Today\'s visitors', 'statnive' ),
				],
			]
		);
	}

	/**
	 * Enqueue the lightweight admin bar script.
	 */
	public static function enqueue_assets(): void {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		$today = gmdate( 'Y-m-d' );
		$nonce = wp_create_nonce( 'wp_rest' );

		// Build the URL in JS to avoid esc_js() re-encoding & as &amp;.
		// rest_url() is safe for esc_js() since it contains no query string.
		$base_url = esc_js( rest_url( 'statnive/v1/summary' ) );

		$script = sprintf(
			'(function(){var el=document.getElementById("statnive-bar-count");if(!el)return;'
			. 'var url="%s"+"?from=%s&to=%s";'
			. 'function f(){fetch(url,{headers:{"X-WP-Nonce":"%s"}}).then(function(r){return r.json()})'
			. '.then(function(d){if(d&&d.totals)el.textContent=d.totals.visitors}).catch(function(){})}'
			. 'f();setInterval(f,60000)})()',
			$base_url,
			esc_js( $today ),
			esc_js( $today ),
			esc_js( $nonce )
		);

		wp_add_inline_script( 'admin-bar', $script );
	}
}
