<?php

declare(strict_types=1);

namespace Statnive\Addon\RealTimeStats;

use Statnive\Feature\FeatureGate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Real-Time Stats add-on module.
 *
 * Provides enhanced real-time analytics: visitor paths, geographic live view.
 * Gated by 'realtime_stats' feature — requires Professional tier or above.
 *
 * The base real-time endpoint already exists (free tier).
 * This module adds enhanced data: live visitor paths and geographic visualization.
 */
final class RealTimeModule {

	/**
	 * Initialize the module if the feature is available.
	 */
	public static function init(): void {
		if ( ! FeatureGate::can( 'realtime_stats' ) ) {
			return;
		}

		add_filter( 'statnive_realtime_response', [ self::class, 'enrich_realtime_data' ] );
	}

	/**
	 * Enrich real-time response with premium data (visitor paths).
	 *
	 * @param array<string, mixed> $data Base real-time data.
	 * @return array<string, mixed> Enriched data with paths.
	 */
	public static function enrich_realtime_data( array $data ): array {
		global $wpdb;

		$sessions = \Statnive\Database\TableRegistry::get( 'sessions' );
		$views    = \Statnive\Database\TableRegistry::get( 'views' );

		// Get visitor navigation paths from active sessions.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$paths = $wpdb->get_results(
			"SELECT s.ID AS session_id, GROUP_CONCAT(v.resource_uri_id ORDER BY v.viewed_at SEPARATOR ',') AS path_ids
			FROM `{$sessions}` s
			INNER JOIN `{$views}` v ON v.session_id = s.ID
			WHERE s.ended_at IS NULL OR s.ended_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
			GROUP BY s.ID
			LIMIT 20",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$data['visitor_paths'] = is_array( $paths ) ? $paths : [];

		return $data;
	}
}
