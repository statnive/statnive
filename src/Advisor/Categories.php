<?php

declare(strict_types=1);

namespace Statnive\Advisor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 10 Ask me! question categories.
 *
 * Each owner-question is tagged with one of these category IDs. The first tab
 * on the Ask me! page is always the "pinned" pseudo-category which is not
 * listed here — see UserPreferences for the pinned set.
 *
 * Order matches the tab strip order from research #73:
 * jaan-to/docs/research/advisor-question-inventory/README.md
 */
final class Categories {

	public const TRAFFIC_OVERVIEW          = 'traffic_overview';
	public const REAL_TIME_TRACKING_HEALTH = 'real_time_tracking_health';
	public const PAGES_AND_CONTENT         = 'pages_and_content';
	public const REFERRERS_AND_CHANNELS    = 'referrers_and_channels';
	public const CAMPAIGNS_AND_UTM         = 'campaigns_and_utm';
	public const GEOGRAPHY_AND_LANGUAGE    = 'geography_and_language';
	public const DEVICES_AND_BROWSERS      = 'devices_and_browsers';
	public const ENGAGEMENT_AND_QUALITY    = 'engagement_and_quality';
	public const REVENUE                   = 'revenue';
	public const EVENTS_AND_PRIVACY        = 'events_and_privacy';

	/**
	 * Ordered list of category IDs as they appear in the Ask me! tab strip.
	 *
	 * @return array<int, string>
	 */
	public static function ordered_ids(): array {
		return [
			self::TRAFFIC_OVERVIEW,
			self::PAGES_AND_CONTENT,
			self::REFERRERS_AND_CHANNELS,
			self::CAMPAIGNS_AND_UTM,
			self::GEOGRAPHY_AND_LANGUAGE,
			self::DEVICES_AND_BROWSERS,
			self::REAL_TIME_TRACKING_HEALTH,
			self::ENGAGEMENT_AND_QUALITY,
			self::REVENUE,
			self::EVENTS_AND_PRIVACY,
		];
	}

	/**
	 * Translated, ordered list of categories for the inventory response.
	 *
	 * @return array<int, array{id:string,label:string,label_en:string}>
	 */
	public static function all(): array {
		$labels = self::labels_en();
		$out    = [];
		foreach ( self::ordered_ids() as $id ) {
			$out[] = [
				'id'       => $id,
				'label'    => self::translate( $id, $labels[ $id ] ),
				'label_en' => $labels[ $id ],
			];
		}
		return $out;
	}

	/**
	 * English source labels — kept English-only so they can be referenced
	 * verbatim from the inventory's `searchable[]` field for code-switching
	 * bilingual search per design-spec §G.3.
	 *
	 * @return array<string, string>
	 */
	public static function labels_en(): array {
		return [
			self::TRAFFIC_OVERVIEW          => 'Traffic Overview',
			self::REAL_TIME_TRACKING_HEALTH => 'Real-time & Tracking Health',
			self::PAGES_AND_CONTENT         => 'Pages & Content',
			self::REFERRERS_AND_CHANNELS    => 'Referrers & Channels',
			self::CAMPAIGNS_AND_UTM         => 'Campaigns & UTM',
			self::GEOGRAPHY_AND_LANGUAGE    => 'Geography & Language',
			self::DEVICES_AND_BROWSERS      => 'Devices & Browsers',
			self::ENGAGEMENT_AND_QUALITY    => 'Engagement & Quality',
			self::REVENUE                   => 'Revenue',
			self::EVENTS_AND_PRIVACY        => 'Events & Privacy',
		];
	}

	/**
	 * Translate a category label via the statnive text domain.
	 *
	 * Each `__()` call uses a literal string so the WP i18n extractor picks it
	 * up. The $id → string match below is exhaustive across the 10 categories.
	 */
	/**
	 * Translate a category label via the `statnive` text domain. Falls back to
	 * the English source if no translation is registered for `$id`.
	 *
	 * @param string $id       Category ID (one of self::*).
	 * @param string $fallback English label, returned for unknown `$id`.
	 */
	private static function translate( string $id, string $fallback ): string {
		switch ( $id ) {
			case self::TRAFFIC_OVERVIEW:
				/* translators: Ask me! category tab label */
				return __( 'Traffic Overview', 'statnive' );
			case self::REAL_TIME_TRACKING_HEALTH:
				/* translators: Ask me! category tab label */
				return __( 'Real-time & Tracking Health', 'statnive' );
			case self::PAGES_AND_CONTENT:
				/* translators: Ask me! category tab label */
				return __( 'Pages & Content', 'statnive' );
			case self::REFERRERS_AND_CHANNELS:
				/* translators: Ask me! category tab label */
				return __( 'Referrers & Channels', 'statnive' );
			case self::CAMPAIGNS_AND_UTM:
				/* translators: Ask me! category tab label */
				return __( 'Campaigns & UTM', 'statnive' );
			case self::GEOGRAPHY_AND_LANGUAGE:
				/* translators: Ask me! category tab label */
				return __( 'Geography & Language', 'statnive' );
			case self::DEVICES_AND_BROWSERS:
				/* translators: Ask me! category tab label */
				return __( 'Devices & Browsers', 'statnive' );
			case self::ENGAGEMENT_AND_QUALITY:
				/* translators: Ask me! category tab label */
				return __( 'Engagement & Quality', 'statnive' );
			case self::REVENUE:
				/* translators: Ask me! category tab label */
				return __( 'Revenue', 'statnive' );
			case self::EVENTS_AND_PRIVACY:
				/* translators: Ask me! category tab label */
				return __( 'Events & Privacy', 'statnive' );
			default:
				return $fallback;
		}
	}
}
