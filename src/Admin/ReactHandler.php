<?php

declare(strict_types=1);

namespace Statnive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * React SPA asset handler for WordPress admin.
 *
 * Enqueues the compiled React dashboard and injects configuration
 * via wp_localize_script. Only loads on the Statnive admin page.
 */
final class ReactHandler {

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue React SPA assets on the Statnive admin page only.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_statnive' !== $hook_suffix ) {
			return;
		}

		$manifest = self::read_manifest();
		if ( empty( $manifest ) ) {
			return;
		}

		// Find the main entry point from Vite manifest.
		$main_entry = $manifest['resources/react/main.tsx'] ?? null;
		if ( null === $main_entry ) {
			return;
		}

		$base_url = plugins_url( 'public/react/', STATNIVE_FILE );

		// Enqueue the main JS bundle.
		$js_url = $base_url . ( $main_entry['file'] ?? '' );
		wp_enqueue_script(
			'statnive-dashboard',
			$js_url,
			[],
			STATNIVE_VERSION,
			true
		);

		// Enqueue CSS if present.
		if ( ! empty( $main_entry['css'] ) ) {
			foreach ( $main_entry['css'] as $index => $css_file ) {
				wp_enqueue_style(
					'statnive-dashboard-' . $index,
					$base_url . $css_file,
					[],
					STATNIVE_VERSION
				);
			}
		}

		// Inject dashboard configuration.
		wp_localize_script(
			'statnive-dashboard',
			'StatniveDashboard',
			[
				'restUrl'   => rest_url( 'statnive/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'siteTitle' => get_bloginfo( 'name' ),
				'version'   => STATNIVE_VERSION,
			]
		);

		// Build import map for dynamic chunks so lazy imports resolve correctly.
		$import_map = self::build_import_map( $manifest, $base_url );

		// Add module type + import map to script tag.
		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $handle ) use ( $js_url, $import_map ): string {
				if ( 'statnive-dashboard' !== $handle ) {
					return $tag;
				}

				// Inject import map before the module script.
				$importmap_tag = '';
				if ( ! empty( $import_map ) ) {
					$importmap_json = wp_json_encode( [ 'imports' => $import_map ], JSON_UNESCAPED_SLASHES );
					$importmap_tag  = wp_get_inline_script_tag( $importmap_json, [ 'type' => 'importmap' ] );
				}

				$tag = str_replace( ' src=', ' type="module" src=', $tag );
				return $importmap_tag . $tag;
			},
			10,
			2
		);
	}

	/**
	 * Build an import map from the Vite manifest.
	 *
	 * Maps relative chunk paths (e.g., "./chunk-abc.js") to full plugin URLs
	 * so dynamic imports resolve correctly in WordPress admin context.
	 *
	 * @param array<string, mixed> $manifest  Parsed Vite manifest.
	 * @param string               $base_url  Plugin assets base URL.
	 * @return array<string, string> Import map entries.
	 */
	private static function build_import_map( array $manifest, string $base_url ): array {
		$map = [];

		foreach ( $manifest as $entry ) {
			$file = $entry['file'] ?? '';
			if ( empty( $file ) ) {
				continue;
			}

			// Map both relative (./) and bare chunk references to full URLs.
			$filename        = basename( $file );
			$full_url        = $base_url . $file;
			$map[ './' . $filename ] = $full_url;
		}

		return $map;
	}

	/**
	 * Read the Vite manifest file.
	 *
	 * @return array<string, mixed> Parsed manifest, or empty array on failure.
	 */
	private static function read_manifest(): array {
		$manifest_path = STATNIVE_PATH . 'public/react/.vite/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			return [];
		}

		$content = file_get_contents( $manifest_path );
		if ( false === $content ) {
			return [];
		}

		$decoded = json_decode( $content, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
