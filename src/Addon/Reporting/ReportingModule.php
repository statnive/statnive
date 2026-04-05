<?php

declare(strict_types=1);

namespace Statnive\Addon\Reporting;

use Statnive\Feature\FeatureGate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Reporting add-on module.
 *
 * Provides custom saved reports with filters and PDF export.
 * Gated by 'advanced_reporting' feature — requires Professional tier or above.
 */
final class ReportingModule {

	/**
	 * Initialize the module if the feature is available.
	 */
	public static function init(): void {
		if ( ! FeatureGate::can( 'advanced_reporting' ) ) {
			return;
		}

		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register Reporting REST routes.
	 */
	public static function register_routes(): void {
		$controller = new ReportsController();
		$controller->register_routes();
	}
}
