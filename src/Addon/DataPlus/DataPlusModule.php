<?php

declare(strict_types=1);

namespace Statnive\Addon\DataPlus;

use Statnive\Feature\FeatureGate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Plus add-on module.
 *
 * Provides CPT (Custom Post Type) analytics and download tracking.
 * Gated by 'data_plus' feature — requires Professional tier or above.
 */
final class DataPlusModule {

	/**
	 * Initialize the module if the feature is available.
	 */
	public static function init(): void {
		if ( ! FeatureGate::can( 'data_plus' ) ) {
			return;
		}

		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		add_filter( 'statnive_view_meta', [ self::class, 'enrich_view_with_cpt' ], 10, 2 );
	}

	/**
	 * Register Data Plus REST routes.
	 */
	public static function register_routes(): void {
		$controller = new CptStatsController();
		$controller->register_routes();
	}

	/**
	 * Enrich view data with CPT information.
	 *
	 * @param array<string, mixed> $meta View metadata.
	 * @param int                  $resource_id WordPress post ID.
	 * @return array<string, mixed> Enriched metadata.
	 */
	public static function enrich_view_with_cpt( array $meta, int $resource_id ): array {
		if ( $resource_id > 0 ) {
			$post_type = get_post_type( $resource_id );
			if ( false !== $post_type ) {
				$meta['post_type'] = $post_type;
			}
		}
		return $meta;
	}
}
