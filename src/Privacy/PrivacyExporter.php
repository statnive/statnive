<?php

declare(strict_types=1);

namespace Statnive\Privacy;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Personal Data Exporter for GDPR compliance.
 *
 * Exports analytics data associated with a logged-in user's sessions.
 * Anonymous visitors cannot be identified due to daily-rotating hashed IDs.
 */
final class PrivacyExporter {

	/**
	 * Batch size for paginated export.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Register the exporter with WordPress.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'add_exporter' ] );
	}

	/**
	 * Add Statnive exporter to the list.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_exporter( array $exporters ): array {
		$exporters['statnive'] = [
			'exporter_friendly_name' => __( 'Statnive Analytics', 'statnive' ),
			'callback'               => [ self::class, 'export' ],
		];
		return $exporters;
	}

	/**
	 * Export personal data for a user.
	 *
	 * @param string $email_address User's email address.
	 * @param int    $page          Page number for batch processing.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );

		if ( false === $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		global $wpdb;

		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );
		$uris_table     = TableRegistry::get( 'resource_uris' );
		$offset         = ( $page - 1 ) * self::BATCH_SIZE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT s.ID, s.started_at, s.ended_at, s.total_views, s.duration
				FROM %i s
				WHERE s.user_id = %d
				ORDER BY s.started_at DESC
				LIMIT %d OFFSET %d',
				$sessions_table,
				$user->ID,
				self::BATCH_SIZE,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $sessions ) ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		$export_items = [];
		foreach ( $sessions as $session ) {
			$data = [
				[
					'name'  => __( 'Session Start', 'statnive' ),
					'value' => $session->started_at,
				],
				[
					'name'  => __( 'Session End', 'statnive' ),
					'value' => $session->ended_at ?? __( 'Active', 'statnive' ),
				],
				[
					'name'  => __( 'Pages Viewed', 'statnive' ),
					'value' => $session->total_views,
				],
				[
					'name'  => __( 'Duration (seconds)', 'statnive' ),
					'value' => $session->duration ?? '0',
				],
			];

			// Get viewed pages for this session.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$pages = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT ru.uri FROM %i v
					JOIN %i ru ON v.resource_uri_id = ru.ID
					WHERE v.session_id = %d
					ORDER BY v.viewed_at ASC
					LIMIT 50',
					$views_table,
					$uris_table,
					$session->ID
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! empty( $pages ) ) {
				$data[] = [
					'name'  => __( 'Pages Visited', 'statnive' ),
					'value' => implode( ', ', $pages ),
				];
			}

			$export_items[] = [
				'group_id'          => 'statnive-sessions',
				'group_label'       => __( 'Statnive Analytics Sessions', 'statnive' ),
				'group_description' => __( 'Analytics sessions recorded by Statnive. No IP addresses or cookies are stored.', 'statnive' ),
				'item_id'           => 'session-' . $session->ID,
				'data'              => $data,
			];
		}

		return [
			'data' => $export_items,
			'done' => count( $sessions ) < self::BATCH_SIZE,
		];
	}
}
