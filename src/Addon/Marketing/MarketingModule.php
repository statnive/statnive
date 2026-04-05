<?php

declare(strict_types=1);

namespace Statnive\Addon\Marketing;

use Statnive\Feature\FeatureGate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marketing add-on module.
 *
 * Provides campaign management with UTM URL generator and
 * Google Search Console integration.
 * Gated by 'marketing' feature — requires Agency tier.
 */
final class MarketingModule {

	/**
	 * Initialize the module if the feature is available.
	 */
	public static function init(): void {
		if ( ! FeatureGate::can( 'marketing' ) ) {
			return;
		}

		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register Marketing REST routes.
	 */
	public static function register_routes(): void {
		$controller = new CampaignsController();
		$controller->register_routes();
	}
}
