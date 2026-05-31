<?php

declare(strict_types=1);

namespace Statnive\Advisor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 120-question Advisor inventory.
 *
 * Source of truth: jaan-to/docs/research/advisor-question-inventory/*.md
 *                  jaan-to/docs/research/advisor-question-inventory/
 *                       statnive-plugin-analytics-advisor-question-inventory-mapping.csv
 *
 * **Hard architectural rule** (see plan §G.2): this class is the single
 * authoritative inventory. The REST inventory endpoint, the QuestionResolver
 * dispatch table, and the client-side search index all read from
 * `Questions::with_searchable()`. Never duplicate the inventory.
 *
 * Future PRs that add questions (e.g., Phase 14 Paid unlock, future analytics
 * surfaces) extend `Questions::all()` and become searchable + resolvable
 * automatically — no other code changes required.
 *
 * Each entry shape:
 *   id           string — stable identifier (`q1`..`q120`)
 *   category_id  string — one of Categories::*
 *   question     string — translated question text (the user-visible label)
 *   question_en  string — English source (kept verbatim for bilingual search)
 *   keywords     string[] — 3-5 English search tags
 *   plan         'free'|'paid' — Free questions resolve today; Paid render with
 *                                "🟡 Coming soon" chip in v1 (no upgrade CTA)
 *   surface      string — the Statnive report or API path that holds the data
 *   viz_hint     string — UI viz template hint; see `Questions::VIZ_*` constants
 *   confidence   'direct'|'calculated'|'proxy' — answer-tier badge
 *   depends_on_schema  optional string — column the answer needs but Statnive
 *                                        doesn't have today (e.g. `entry_count`)
 */
final class Questions {

	/**
	 * Plan tiers.
	 */
	public const PLAN_FREE = 'free';
	public const PLAN_PAID = 'paid';

	/**
	 * Confidence tiers.
	 */
	public const CONF_DIRECT     = 'direct';
	public const CONF_CALCULATED = 'calculated';
	public const CONF_PROXY      = 'proxy';

	/**
	 * UI viz template hints. Mirrored in `resources/react/types/api.ts`
	 * (`AdvisorVizHint`) so the AnswerViz dispatch and the inventory share a
	 * single vocabulary.
	 */
	public const VIZ_KPI_TILE    = 'kpi_tile';
	public const VIZ_TABLE       = 'table';
	public const VIZ_DONUT       = 'donut';
	public const VIZ_BAR         = 'bar';
	public const VIZ_MAP         = 'map';
	public const VIZ_DELTA       = 'delta';
	public const VIZ_LINE        = 'line';
	public const VIZ_LIVE        = 'live';
	public const VIZ_LIVE_TABLE  = 'live_table';
	public const VIZ_STATUS      = 'status';
	public const VIZ_FUNNEL      = 'funnel';
	public const VIZ_ANOMALY     = 'anomaly';
	public const VIZ_RECOMMENDATION = 'recommendation';
	public const VIZ_COMING_SOON = 'coming_soon';
	public const VIZ_ERROR       = 'error';

	/**
	 * Resolver-only response status codes for the `status` envelope key.
	 */
	public const STATUS_OK          = 'ok';
	public const STATUS_COMING_SOON = 'coming_soon';
	public const STATUS_ERROR       = 'error';

	/**
	 * Schema-gap markers — values match the columns research #71 flagged as
	 * missing from the current `summary` table. Questions tagged with these
	 * keys render the `🟡 Coming soon` (v1.1) chip until the follow-on schema
	 * migration ships.
	 */
	public const SCHEMA_ENTRY_COUNT        = 'entry_count';
	public const SCHEMA_EXIT_COUNT         = 'exit_count';
	public const SCHEMA_PAGE_VISITOR_COUNT = 'page_visitor_count';
	public const SCHEMA_AVG_TIME_ON_PAGE   = 'avg_time_on_page';

	/**
	 * Aggregate the full 120-question inventory.
	 *
	 * Memoised per-locale within a request: the inventory is deterministic
	 * for a given locale (constant strings + `__()` lookups), so we cache
	 * the assembled array keyed on `determine_locale()`. This avoids re-
	 * evaluating the 98-case `translate_question_text()` switch 120 times
	 * every time a caller hits the inventory (resolver `find()`, REST
	 * endpoint, `valid_ids()`, `with_searchable()`).
	 *
	 * Keying on locale is required because PHP-FPM workers are reused
	 * across requests with different user locales (via `determine_locale()`
	 * honouring per-user `locale` profile meta). A non-keyed static would
	 * leak whichever language was first translated into all subsequent
	 * requests on the same worker.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		static $cached = [];
		$locale        = function_exists( 'determine_locale' ) ? (string) determine_locale() : ( function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US' );
		if ( isset( $cached[ $locale ] ) ) {
			return $cached[ $locale ];
		}
		$cached[ $locale ] = array_merge(
			self::traffic_overview(),
			self::real_time_tracking_health(),
			self::pages_and_content(),
			self::referrers_and_channels(),
			self::campaigns_and_utm(),
			self::geography_and_language(),
			self::devices_and_browsers(),
			self::engagement_and_quality(),
			self::revenue(),
			self::events_and_privacy()
		);
		return $cached[ $locale ];
	}

	/**
	 * Inventory enriched with translated `category` label + bilingual
	 * `searchable[]` field. This is what the REST endpoint returns and what
	 * the React search hook indexes.
	 *
	 * `apply_filters('statnive_advisor_questions', $list)` lets future PRs
	 * inject questions via a filter (forward-compat per plan §G.2).
	 *
	 * The pre-filter enriched inventory is memoised per-locale so the
	 * bilingual `searchable[]` array isn't rebuilt every call. The filter
	 * is still re-applied each call so test mutations / runtime additions
	 * surface. Locale-keyed for the same reason as `all()` — a non-keyed
	 * static leaks the first request's translations across PHP-FPM-worker
	 * reuse.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function with_searchable(): array {
		static $cached_pre_filter = [];
		$locale                   = function_exists( 'determine_locale' ) ? (string) determine_locale() : ( function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US' );
		if ( ! isset( $cached_pre_filter[ $locale ] ) ) {
			$cat_by_id = [];
			foreach ( Categories::all() as $c ) {
				$cat_by_id[ $c['id'] ] = $c;
			}

			$out = [];
			foreach ( self::all() as $q ) {
				$cat              = $cat_by_id[ $q['category_id'] ] ?? [
					'label'    => $q['category_id'],
					'label_en' => $q['category_id'],
				];
				$q['category']    = $cat['label'];
				$q['category_en'] = $cat['label_en'];
				$searchable       = array_filter(
					array_merge(
						[ $q['question'], $q['question_en'], $q['category'], $q['category_en'] ],
						$q['keywords']
					),
					static fn( $s ) => is_string( $s ) && '' !== $s
				);
				$q['searchable']  = array_values( array_unique( $searchable ) );
				$out[]            = $q;
			}
			$cached_pre_filter[ $locale ] = $out;
		}

		/**
		 * Filter the Ask me! question inventory.
		 *
		 * Future PRs (Phase 14 Paid unlocks, new analytics surfaces) can
		 * inject additional questions here without modifying core inventory.
		 *
		 * @param array<int, array<string, mixed>> $cached_pre_filter Inventory rows.
		 */
		return (array) apply_filters( 'statnive_advisor_questions', $cached_pre_filter[ $locale ] );
	}

	/**
	 * Look up a single inventory row by ID. Returns null if not found.
	 *
	 * Uses `with_searchable()` so the filter-injected rows surface — but
	 * since `with_searchable()`'s pre-filter inventory is memoised, the
	 * cost per call is just the filter re-apply + a single linear scan.
	 *
	 * @param string $id Question ID.
	 * @return array<string, mixed>|null
	 */
	public static function find( string $id ): ?array {
		foreach ( self::with_searchable() as $q ) {
			if ( $q['id'] === $id ) {
				return $q;
			}
		}
		return null;
	}

	/**
	 * Returns just the IDs — used by UserPreferences validation.
	 *
	 * @return array<int, string>
	 */
	public static function valid_ids(): array {
		return array_column( self::with_searchable(), 'id' );
	}

	// =================================================================
	// Category inventories — 120 questions total
	//
	// Each `__()` call must contain a literal string so the WP i18n
	// extractor picks it up. Keep `question` and `question_en` byte-
	// identical (the literal string vs the same string verbatim).
	// =================================================================

	/**
	 * Inventory for the category (12 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function traffic_overview(): array {
		$cat = Categories::TRAFFIC_OVERVIEW;
		return [
			[
				'id'             => 'q2',
				'category_id'    => $cat,
				/* translators: %s is a date-range phrase like "today", "in the last 30 days", "this month" — substituted client-side from the dashboard date picker. */
				'question'       => __( 'How many people visited my site %s?', 'statnive' ),
				'question_en'    => 'How many people visited my site %s?',
				'keywords'       => [ 'traffic', 'visitors', 'today', 'this week', 'last 7 days', 'last 30 days', 'this month', 'last month' ],
				'plan'           => self::PLAN_FREE,
				'surface'        => '/summary',
				'viz_hint'       => 'kpi_tile',
				'confidence'     => self::CONF_DIRECT,
				'dynamic_window' => 'current',
			],
			[
				'id'          => 'q3',
				'category_id' => $cat,
				'question'    => __( 'How many pageviews did I get?', 'statnive' ),
				'question_en' => 'How many pageviews did I get?',
				'keywords'    => [ 'pageviews', 'page views', 'views' ],
				'plan'        => self::PLAN_FREE,
				'surface'     => '/summary',
				'viz_hint'    => 'kpi_tile',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q4',
				'category_id' => $cat,
				'question'    => __( 'How many sessions did I get?', 'statnive' ),
				'question_en' => 'How many sessions did I get?',
				'keywords'    => [ 'sessions', 'visits' ],
				'plan'        => self::PLAN_FREE,
				'surface'     => '/summary',
				'viz_hint'    => 'kpi_tile',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'             => 'q6',
				'category_id'    => $cat,
				/* translators: %s is a phrase like "with yesterday" or "with the previous 30 days" — substituted client-side from the dashboard date picker. */
				'question'       => __( 'Is my traffic up or down compared %s?', 'statnive' ),
				'question_en'    => 'Is my traffic up or down compared %s?',
				'keywords'       => [ 'traffic', 'compare', 'yesterday', 'last week', 'last month', 'delta', 'period' ],
				'plan'           => self::PLAN_FREE,
				'surface'        => '/summary',
				'viz_hint'       => 'delta',
				'confidence'     => self::CONF_CALCULATED,
				'dynamic_window' => 'prior',
			],
			[
				'id'          => 'q7',
				'category_id' => $cat,
				'question'    => __( 'Which day had the most traffic?', 'statnive' ),
				'question_en' => 'Which day had the most traffic?',
				'keywords'    => [ 'best day', 'top day', 'most' ],
				'plan'        => self::PLAN_FREE,
				'surface'     => '/summary',
				'viz_hint'    => 'table',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q8',
				'category_id' => $cat,
				'question'    => __( 'Which date had the lowest traffic?', 'statnive' ),
				'question_en' => 'Which date had the lowest traffic?',
				'keywords'    => [ 'worst day', 'lowest', 'least' ],
				'plan'        => self::PLAN_FREE,
				'surface'     => '/summary',
				'viz_hint'    => 'table',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q9',
				'category_id' => $cat,
				'question'    => __( 'What is my traffic trend over time?', 'statnive' ),
				'question_en' => 'What is my traffic trend over time?',
				'keywords'    => [ 'trend', 'over time', 'history' ],
				'plan'        => self::PLAN_FREE,
				'surface'     => '/summary',
				'viz_hint'    => 'line',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q10',
				'category_id' => $cat,
				'question'    => __( 'Did traffic suddenly drop?', 'statnive' ),
				'question_en' => 'Did traffic suddenly drop?',
				'keywords'    => [ 'drop', 'anomaly', 'dropped' ],
				'plan'        => self::PLAN_FREE,
				'surface'     => '/summary',
				'viz_hint'    => 'anomaly',
				'confidence'  => self::CONF_CALCULATED,
			],
			[
				'id'          => 'q11',
				'category_id' => $cat,
				'question'    => __( 'Did traffic suddenly spike?', 'statnive' ),
				'question_en' => 'Did traffic suddenly spike?',
				'keywords'    => [ 'spike', 'anomaly', 'jumped' ],
				'plan'        => self::PLAN_FREE,
				'surface'     => '/summary',
				'viz_hint'    => 'anomaly',
				'confidence'  => self::CONF_CALCULATED,
			],
			[
				'id'          => 'q12',
				'category_id' => $cat,
				'question'    => __( 'Which source caused the biggest traffic change?', 'statnive' ),
				'question_en' => 'Which source caused the biggest traffic change?',
				'keywords'    => [ 'source', 'channel', 'change', 'delta' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/summary+/sources',
				'viz_hint'    => 'table',
				'confidence'  => self::CONF_CALCULATED,
			],
		];
	}

	/**
	 * Inventory for the category (10 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function real_time_tracking_health(): array {
		$cat = Categories::REAL_TIME_TRACKING_HEALTH;
		return [
			[
				'id'          => 'q13',
				'category_id' => $cat,
				'question'    => __( 'How many people are on my site right now?', 'statnive' ),
				'question_en' => 'How many people are on my site right now?',
				'keywords'    => [ 'real-time', 'now', 'live', 'active' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/realtime',
				'viz_hint'    => 'live',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q14',
				'category_id' => $cat,
				'question'    => __( 'Which pages are active right now?', 'statnive' ),
				'question_en' => 'Which pages are active right now?',
				'keywords'    => [ 'real-time', 'active pages', 'now' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/realtime',
				'viz_hint'    => 'live_table',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q15',
				'category_id' => $cat,
				'question'    => __( 'Is Statnive tracking my site?', 'statnive' ),
				'question_en' => 'Is Statnive tracking my site?',
				'keywords'    => [ 'tracking', 'health', 'working' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/realtime+/summary',
				'viz_hint'    => 'status',
				'confidence'  => self::CONF_CALCULATED,
			],
			[
				'id'          => 'q16',
				'category_id' => $cat,
				'question'    => __( 'Did my test visit appear?', 'statnive' ),
				'question_en' => 'Did my test visit appear?',
				'keywords'    => [ 'test', 'verify', 'real-time' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/realtime',
				'viz_hint'    => 'live',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'             => 'q17',
				'category_id'    => $cat,
				/* translators: %s is a date-range phrase from the date picker — e.g. "today", "in the last 7 days", "last month". */
				'question'       => __( 'Has Statnive received data %s?', 'statnive' ),
				'question_en'    => 'Has Statnive received data %s?',
				'keywords'       => [ 'data', 'today', 'this week', 'last month', 'health' ],
				'plan'           => self::PLAN_PAID,
				'surface'        => '/summary',
				'viz_hint'       => 'status',
				'confidence'     => self::CONF_DIRECT,
				'dynamic_window' => 'current',
			],
			[
				'id'          => 'q18',
				'category_id' => $cat,
				'question'    => __( 'Has Statnive received data in the last 30 minutes?', 'statnive' ),
				'question_en' => 'Has Statnive received data in the last 30 minutes?',
				'keywords'    => [ 'recent', 'health', 'tracking' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/realtime',
				'viz_hint'    => 'status',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q19',
				'category_id' => $cat,
				'question'    => __( 'Is one page receiving no visits?', 'statnive' ),
				'question_en' => 'Is one page receiving no visits?',
				'keywords'    => [ 'zero', 'no visits', 'page' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/pages',
				'viz_hint'    => 'table',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q20',
				'category_id' => $cat,
				'question'    => __( 'Is my site showing zero traffic?', 'statnive' ),
				'question_en' => 'Is my site showing zero traffic?',
				'keywords'    => [ 'zero', 'no data', 'tracking' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/summary',
				'viz_hint'    => 'status',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q21',
				'category_id' => $cat,
				'question'    => __( 'Are tracking hits coming from the wrong domain?', 'statnive' ),
				'question_en' => 'Are tracking hits coming from the wrong domain?',
				'keywords'    => [ 'domain', 'diagnostics', 'mismatch' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/diagnostics',
				'viz_hint'    => 'table',
				'confidence'  => self::CONF_DIRECT,
			],
			[
				'id'          => 'q22',
				'category_id' => $cat,
				'question'    => __( 'Are bots affecting my data?', 'statnive' ),
				'question_en' => 'Are bots affecting my data?',
				'keywords'    => [ 'bots', 'bot share', 'spam' ],
				'plan'        => self::PLAN_PAID,
				'surface'     => '/dimensions/devices',
				'viz_hint'    => 'kpi_tile',
				'confidence'  => self::CONF_PROXY,
			],
		];
	}

	/**
	 * Inventory for the category (18 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function pages_and_content(): array {
		$cat = Categories::PAGES_AND_CONTENT;
		return [
			self::q( 'q23', $cat, 'What are my top pages?', [ 'top pages', 'most viewed' ], self::PLAN_FREE, '/pages', 'table', self::CONF_DIRECT ),
			self::q( 'q24', $cat, 'What are my most-read posts?', [ 'posts', 'most read', 'popular' ], self::PLAN_FREE, '/pages', 'table', self::CONF_DIRECT ),
			self::q( 'q25', $cat, 'Which page got the most views today?', [ 'top page', 'today' ], self::PLAN_FREE, '/pages', 'table', self::CONF_DIRECT ),
			self::q( 'q26', $cat, 'Which page got the most views this week?', [ 'top page', 'this week' ], self::PLAN_FREE, '/pages', 'table', self::CONF_DIRECT ),
			self::q( 'q27', $cat, 'What is my best landing page?', [ 'landing page', 'entry' ], self::PLAN_FREE, '/pages/entry', 'table', self::CONF_DIRECT, self::SCHEMA_ENTRY_COUNT ),
			self::q( 'q28', $cat, 'Which pages do people enter from?', [ 'entry', 'landing' ], self::PLAN_FREE, '/pages/entry', 'table', self::CONF_DIRECT, self::SCHEMA_ENTRY_COUNT ),
			self::q( 'q29', $cat, 'Which pages do people leave from?', [ 'exit', 'leave' ], self::PLAN_FREE, '/pages/exit', 'table', self::CONF_DIRECT, self::SCHEMA_EXIT_COUNT ),
			self::q( 'q30', $cat, 'Which pages have the most exits?', [ 'exit', 'most exits' ], self::PLAN_FREE, '/pages/exit', 'table', self::CONF_DIRECT, self::SCHEMA_EXIT_COUNT ),
			self::q( 'q31', $cat, 'Which page titles get the most views?', [ 'title', 'top' ], self::PLAN_FREE, '/pages', 'table', self::CONF_DIRECT ),
			self::q( 'q32', $cat, 'Did my latest post get traffic?', [ 'latest', 'newest post' ], self::PLAN_FREE, '/pages', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q33', $cat, 'Did my homepage get seen?', [ 'homepage', 'home', 'landing' ], self::PLAN_FREE, '/pages', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q34', $cat, 'How many people viewed my contact page?', [ 'contact', 'contact us' ], self::PLAN_FREE, '/pages', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q35', $cat, 'How many people viewed my about page?', [ 'about', 'about us' ], self::PLAN_FREE, '/pages', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q36', $cat, 'How many people viewed my pricing page?', [ 'pricing', 'price' ], self::PLAN_FREE, '/pages', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q37', $cat, 'Which old posts still get traffic?', [ 'evergreen', 'old posts' ], self::PLAN_FREE, '/pages', 'table', self::CONF_PROXY ),
			self::q( 'q38', $cat, 'Which pages are losing visitors?', [ 'losing', 'declining' ], self::PLAN_PAID, '/pages', 'table', self::CONF_CALCULATED ),
			self::q( 'q39', $cat, 'Which pages probably need updating?', [ 'update', 'refresh', 'stale' ], self::PLAN_PAID, '/pages', 'table', self::CONF_PROXY ),
			self::q( 'q40', $cat, 'Which pages have the longest average duration?', [ 'duration', 'time on page', 'engagement' ], self::PLAN_PAID, '/pages', 'table', self::CONF_CALCULATED, self::SCHEMA_AVG_TIME_ON_PAGE ),
		];
	}

	/**
	 * Inventory for the category (16 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function referrers_and_channels(): array {
		$cat = Categories::REFERRERS_AND_CHANNELS;
		return [
			self::q( 'q41', $cat, 'Where is my traffic coming from?', [ 'channel', 'where from', 'sources' ], self::PLAN_FREE, '/sources', 'donut', self::CONF_DIRECT ),
			self::q( 'q42', $cat, 'Which channel sends the most traffic?', [ 'top channel', 'best source' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q43', $cat, 'How much traffic comes from Google?', [ 'google', 'organic' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q44', $cat, 'How much traffic comes from organic search?', [ 'organic', 'search', 'seo' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q45', $cat, 'How much traffic comes from social media?', [ 'social', 'social media' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q46', $cat, 'Which social network sends the most traffic?', [ 'social', 'network', 'top social' ], self::PLAN_FREE, '/sources', 'table', self::CONF_DIRECT ),
			self::q( 'q47', $cat, 'How much traffic comes from direct visits?', [ 'direct', 'no referrer' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q48', $cat, 'Did Reddit send traffic?', [ 'reddit', 'social' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q49', $cat, 'Did Twitter/X send traffic?', [ 'twitter', 'x', 'social' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q50', $cat, 'Did Facebook send traffic?', [ 'facebook', 'fb', 'social' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q51', $cat, 'Did Instagram send traffic?', [ 'instagram', 'social' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q52', $cat, 'Did YouTube send traffic?', [ 'youtube', 'social' ], self::PLAN_FREE, '/sources', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q53', $cat, 'Are AI tools sending traffic?', [ 'ai assistants', 'chatgpt', 'claude', 'perplexity' ], self::PLAN_FREE, '/sources', 'table', self::CONF_DIRECT ),
			self::q( 'q54', $cat, 'Is organic traffic increasing?', [ 'organic trend', 'seo trend' ], self::PLAN_FREE, '/sources', 'line', self::CONF_CALCULATED ),
			self::q( 'q55', $cat, 'Is referral traffic increasing?', [ 'referral trend', 'social trend' ], self::PLAN_FREE, '/sources', 'line', self::CONF_CALCULATED ),
			self::q( 'q56', $cat, 'Which referrer sends low-quality traffic?', [ 'quality', 'bad source' ], self::PLAN_PAID, '/sources+/pages', 'table', self::CONF_PROXY ),
		];
	}

	/**
	 * Inventory for the category (15 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function campaigns_and_utm(): array {
		$cat = Categories::CAMPAIGNS_AND_UTM;
		return [
			self::q( 'q57', $cat, 'Did my campaign drive traffic?', [ 'campaign', 'utm' ], self::PLAN_FREE, '/utm', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q58', $cat, 'Which UTM campaign sent the most visitors?', [ 'utm campaign', 'top campaign' ], self::PLAN_FREE, '/utm', 'table', self::CONF_DIRECT ),
			self::q( 'q59', $cat, 'Which UTM source sent the most traffic?', [ 'utm source', 'source' ], self::PLAN_FREE, '/utm', 'table', self::CONF_DIRECT ),
			self::q( 'q60', $cat, 'Which UTM medium sent the most traffic?', [ 'utm medium', 'medium' ], self::PLAN_FREE, '/utm', 'table', self::CONF_DIRECT ),
			self::q( 'q61', $cat, 'Did my newsletter drive traffic?', [ 'newsletter', 'email' ], self::PLAN_FREE, '/utm', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q62', $cat, 'Did my email campaign work?', [ 'email', 'campaign' ], self::PLAN_FREE, '/utm', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q64', $cat, 'Did my Facebook campaign send traffic?', [ 'facebook', 'meta', 'paid' ], self::PLAN_FREE, '/utm', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q65', $cat, 'Did my Google Ads campaign send traffic?', [ 'google ads', 'cpc', 'paid' ], self::PLAN_FREE, '/utm', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q66', $cat, 'Which landing page did my campaign send people to?', [ 'landing', 'campaign' ], self::PLAN_FREE, '/utm+/pages/entry', 'table', self::CONF_DIRECT, self::SCHEMA_ENTRY_COUNT ),
			self::q( 'q67', $cat, 'Which UTM combination worked best by traffic?', [ 'combo', 'best utm' ], self::PLAN_FREE, '/utm', 'table', self::CONF_DIRECT ),
			self::q( 'q68', $cat, 'Did Black Friday increase traffic?', [ 'black friday', 'sale', 'event' ], self::PLAN_PAID, '/summary+/utm', 'delta', self::CONF_CALCULATED ),
			self::q( 'q69', $cat, 'Which campaign had the best visitor quality?', [ 'quality', 'engagement' ], self::PLAN_PAID, '/utm', 'table', self::CONF_PROXY ),
			self::q( 'q70', $cat, 'Which campaign had the highest bounce count?', [ 'bounce', 'campaign' ], self::PLAN_PAID, '/utm', 'table', self::CONF_DIRECT ),
			self::q( 'q71', $cat, 'Which campaign should I double down on?', [ 'double down', 'winner' ], self::PLAN_PAID, '/utm', 'table', self::CONF_PROXY ),
		];
	}

	/**
	 * Inventory for the category (9 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function geography_and_language(): array {
		$cat = Categories::GEOGRAPHY_AND_LANGUAGE;
		return [
			self::q( 'q72', $cat, 'What countries are my visitors from?', [ 'country', 'countries', 'where' ], self::PLAN_FREE, '/dimensions/countries', 'map', self::CONF_DIRECT ),
			self::q( 'q73', $cat, 'Which country sends the most traffic?', [ 'top country' ], self::PLAN_FREE, '/dimensions/countries', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q74', $cat, 'Is most of my traffic local or international?', [ 'local', 'international', 'home country' ], self::PLAN_FREE, '/dimensions/countries', 'donut', self::CONF_CALCULATED ),
			self::q( 'q75', $cat, 'What language are my visitors using?', [ 'language' ], self::PLAN_FREE, '/dimensions/languages', 'table', self::CONF_DIRECT ),
			self::q( 'q76', $cat, 'What cities are my visitors from?', [ 'cities', 'city' ], self::PLAN_PAID, '/dimensions/cities', 'map', self::CONF_DIRECT ),
			self::q( 'q77', $cat, 'Which city sends the most traffic?', [ 'top city' ], self::PLAN_PAID, '/dimensions/cities', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q78', $cat, 'Did traffic from one country drop?', [ 'country drop', 'change' ], self::PLAN_PAID, '/dimensions/countries', 'table', self::CONF_CALCULATED ),
			self::q( 'q79', $cat, 'Which countries visit a specific page?', [ 'country page' ], self::PLAN_PAID, '/dimensions/countries+/pages', 'map', self::CONF_DIRECT ),
			self::q( 'q80', $cat, 'Which countries visit product or pricing pages?', [ 'country product', 'country pricing' ], self::PLAN_PAID, '/dimensions/countries+/pages', 'map', self::CONF_PROXY ),
		];
	}

	/**
	 * Inventory for the category (12 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function devices_and_browsers(): array {
		$cat = Categories::DEVICES_AND_BROWSERS;
		return [
			self::q( 'q81', $cat, 'How much traffic is mobile vs desktop?', [ 'mobile', 'desktop', 'device' ], self::PLAN_FREE, '/dimensions/devices', 'bar', self::CONF_DIRECT ),
			self::q( 'q82', $cat, 'Which device type is most common?', [ 'device', 'top device' ], self::PLAN_FREE, '/dimensions/devices', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q83', $cat, 'How much traffic is tablet?', [ 'tablet' ], self::PLAN_FREE, '/dimensions/devices', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q84', $cat, 'Which browser do visitors use?', [ 'browser', 'chrome', 'safari', 'firefox' ], self::PLAN_FREE, '/browsers', 'table', self::CONF_DIRECT ),
			self::q( 'q85', $cat, 'Which operating systems do visitors use?', [ 'os', 'windows', 'mac', 'android', 'ios' ], self::PLAN_FREE, '/oss', 'table', self::CONF_DIRECT ),
			self::q( 'q86', $cat, 'Is mobile traffic higher than desktop?', [ 'mobile', 'compare' ], self::PLAN_FREE, '/dimensions/devices', 'bar', self::CONF_CALCULATED ),
			self::q( 'q87', $cat, 'Should I prioritize mobile design?', [ 'mobile share', 'priority' ], self::PLAN_FREE, '/dimensions/devices', 'kpi_tile', self::CONF_PROXY ),
			self::q( 'q88', $cat, 'Which pages are mostly viewed on mobile?', [ 'mobile pages' ], self::PLAN_PAID, '/dimensions/devices+/pages', 'table', self::CONF_DIRECT ),
			self::q( 'q89', $cat, 'Which OS/browser should I test first?', [ 'test', 'qa' ], self::PLAN_PAID, '/browsers+/oss', 'table', self::CONF_DIRECT ),
			self::q( 'q90', $cat, 'Does mobile look worse than desktop?', [ 'mobile vs desktop', 'engagement' ], self::PLAN_PAID, '/dimensions/devices+/pages', 'bar', self::CONF_CALCULATED ),
			self::q( 'q91', $cat, 'Which device type exits more often?', [ 'device exit' ], self::PLAN_PAID, '/dimensions/devices+/pages', 'bar', self::CONF_CALCULATED, self::SCHEMA_EXIT_COUNT ),
			self::q( 'q92', $cat, 'How much bot traffic is shown in device breakdown?', [ 'bots', 'bot share' ], self::PLAN_PAID, '/dimensions/devices', 'kpi_tile', self::CONF_DIRECT ),
		];
	}

	/**
	 * Inventory for the category (8 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function engagement_and_quality(): array {
		$cat = Categories::ENGAGEMENT_AND_QUALITY;
		return [
			self::q( 'q93', $cat, 'Which pages have the most bounces?', [ 'bounce', 'pages' ], self::PLAN_PAID, '/pages', 'table', self::CONF_DIRECT ),
			self::q( 'q94', $cat, 'Which pages have the highest exit count?', [ 'exit', 'pages' ], self::PLAN_PAID, '/pages/exit', 'table', self::CONF_DIRECT, self::SCHEMA_EXIT_COUNT ),
			self::q( 'q95', $cat, 'How long do visitors stay on average?', [ 'duration', 'avg time' ], self::PLAN_PAID, '/summary', 'kpi_tile', self::CONF_CALCULATED ),
			self::q( 'q96', $cat, 'How many pages does each session view?', [ 'pages per session' ], self::PLAN_PAID, '/summary', 'kpi_tile', self::CONF_CALCULATED ),
			self::q( 'q97', $cat, 'Which pages keep visitors engaged?', [ 'engaged', 'time on page' ], self::PLAN_PAID, '/pages', 'table', self::CONF_CALCULATED, self::SCHEMA_AVG_TIME_ON_PAGE ),
			self::q( 'q98', $cat, 'Which traffic source sends engaged visitors?', [ 'source quality', 'engagement' ], self::PLAN_PAID, '/sources+/pages', 'table', self::CONF_PROXY ),
			self::q( 'q99', $cat, 'Which pages may be broken or confusing?', [ 'broken pages', 'confusing' ], self::PLAN_PAID, '/pages', 'table', self::CONF_PROXY ),
			self::q( 'q100', $cat, 'What is the bounce rate for a page?', [ 'bounce rate', 'page bounce' ], self::PLAN_PAID, '/pages', 'kpi_tile', self::CONF_CALCULATED ),
		];
	}

	/**
	 * Inventory for the category (15 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function revenue(): array {
		$cat = Categories::REVENUE;
		return [
			self::q( 'q101', $cat, 'How many orders did I get?', [ 'orders', 'revenue' ], self::PLAN_PAID, '/revenue', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q102', $cat, 'How much gross revenue did I make?', [ 'gross revenue', 'sales' ], self::PLAN_PAID, '/revenue', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q103', $cat, 'How much net revenue did I make?', [ 'net revenue' ], self::PLAN_PAID, '/revenue', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q104', $cat, 'What is my average order value?', [ 'aov', 'average order' ], self::PLAN_PAID, '/revenue', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q105', $cat, 'How much did I refund?', [ 'refund', 'refunds' ], self::PLAN_PAID, '/revenue', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q106', $cat, 'What is my refund rate?', [ 'refund rate' ], self::PLAN_PAID, '/revenue', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q107', $cat, 'Which channel brings the most revenue?', [ 'revenue channel', 'attribution' ], self::PLAN_PAID, '/revenue', 'table', self::CONF_DIRECT ),
			self::q( 'q108', $cat, 'Which UTM campaign brings the most revenue?', [ 'revenue campaign' ], self::PLAN_PAID, '/revenue', 'table', self::CONF_DIRECT ),
			self::q( 'q109', $cat, 'Which landing page brings the most revenue?', [ 'revenue landing' ], self::PLAN_PAID, '/revenue', 'table', self::CONF_DIRECT ),
			self::q( 'q110', $cat, 'Which product sells the most units?', [ 'product units', 'best seller' ], self::PLAN_PAID, '/revenue', 'table', self::CONF_DIRECT ),
			self::q( 'q111', $cat, 'Which product makes the most revenue?', [ 'product revenue' ], self::PLAN_PAID, '/revenue', 'table', self::CONF_DIRECT ),
			self::q( 'q112', $cat, 'Which coupon is used the most?', [ 'coupon redemptions' ], self::PLAN_PAID, '/revenue', 'table', self::CONF_DIRECT ),
			self::q( 'q113', $cat, 'Which coupon gives the most discount?', [ 'coupon discount' ], self::PLAN_PAID, '/revenue', 'table', self::CONF_DIRECT ),
			self::q( 'q114', $cat, 'What is my funnel conversion rate?', [ 'funnel', 'conversion rate' ], self::PLAN_PAID, '/revenue', 'funnel', self::CONF_DIRECT ),
			self::q( 'q115', $cat, 'How much tax and shipping did customers pay?', [ 'tax', 'shipping' ], self::PLAN_PAID, '/revenue', 'kpi_tile', self::CONF_DIRECT ),
		];
	}

	/**
	 * Inventory for the category (5 questions).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function events_and_privacy(): array {
		$cat = Categories::EVENTS_AND_PRIVACY;
		return [
			self::q( 'q116', $cat, 'Which custom events happened most often?', [ 'events', 'custom event' ], self::PLAN_PAID, '/events', 'table', self::CONF_DIRECT ),
			self::q( 'q117', $cat, 'How many sessions included a specific event?', [ 'event sessions' ], self::PLAN_PAID, '/events', 'kpi_tile', self::CONF_DIRECT ),
			self::q( 'q118', $cat, 'Is a specific custom event firing?', [ 'event health', 'firing' ], self::PLAN_PAID, '/events', 'status', self::CONF_DIRECT ),
			self::q( 'q119', $cat, 'Is my privacy audit passing?', [ 'privacy', 'audit', 'compliance' ], self::PLAN_PAID, '/privacy-audit', 'status', self::CONF_DIRECT ),
			self::q( 'q120', $cat, 'What privacy or diagnostics warnings should I fix?', [ 'privacy warnings', 'diagnostics' ], self::PLAN_PAID, '/privacy-audit+/diagnostics', 'table', self::CONF_DIRECT ),
		];
	}

	/**
	 * Compact constructor for a question row.
	 *
	 * `$question_en` is the English source string verbatim; translation
	 * happens via the dispatch table in `translate_question_text()` so the
	 * static WP i18n extractor (`make-pot`) sees every literal `__()` call
	 * and produces one POT entry per question text.
	 *
	 * @param string             $id                Stable question identifier (`q1`..`q120`).
	 * @param string             $category_id       One of Categories::*.
	 * @param string             $question_en       English source label.
	 * @param array<int, string> $keywords          3-5 English search tags.
	 * @param string             $plan              `Questions::PLAN_FREE` or `PLAN_PAID`.
	 * @param string             $surface           Statnive report/path that holds the data.
	 * @param string             $viz_hint          UI viz template hint.
	 * @param string             $confidence        One of `Questions::CONF_*`.
	 * @param string|null        $depends_on_schema Schema column the answer needs (e.g. `entry_count`).
	 * @return array<string, mixed>
	 */
	private static function q(
		string $id,
		string $category_id,
		string $question_en,
		array $keywords,
		string $plan,
		string $surface,
		string $viz_hint,
		string $confidence,
		?string $depends_on_schema = null
	): array {
		$row = [
			'id'          => $id,
			'category_id' => $category_id,
			'question'    => self::translate_question_text( $id, $question_en ),
			'question_en' => $question_en,
			'keywords'    => $keywords,
			'plan'        => $plan,
			'surface'     => $surface,
			'viz_hint'    => $viz_hint,
			'confidence'  => $confidence,
		];
		if ( null !== $depends_on_schema ) {
			$row['depends_on_schema'] = $depends_on_schema;
		}
		return $row;
	}

	/**
	 * Question text translation table. Each `__()` call uses a literal
	 * string so the WP i18n make-pot extractor picks every question up.
	 *
	 * Q1-Q12 are translated inline in `traffic_overview()` (which uses
	 * literal `__()` calls directly). Q23-Q120 route through this method
	 * because they're constructed via the compact `q()` helper.
	 *
	 * @param string $id       Question ID (`q23`..`q120`).
	 * @param string $fallback English source label, returned if `$id` is unknown.
	 * @return string Translated question text (or English fallback).
	 */
	private static function translate_question_text( string $id, string $fallback ): string {
		switch ( $id ) {
			/* translators: Ask me! question card label */
			case 'q23':
				return __( 'What are my top pages?', 'statnive' );
			case 'q24':
				return __( 'What are my most-read posts?', 'statnive' );
			case 'q25':
				return __( 'Which page got the most views today?', 'statnive' );
			case 'q26':
				return __( 'Which page got the most views this week?', 'statnive' );
			case 'q27':
				return __( 'What is my best landing page?', 'statnive' );
			case 'q28':
				return __( 'Which pages do people enter from?', 'statnive' );
			case 'q29':
				return __( 'Which pages do people leave from?', 'statnive' );
			case 'q30':
				return __( 'Which pages have the most exits?', 'statnive' );
			case 'q31':
				return __( 'Which page titles get the most views?', 'statnive' );
			case 'q32':
				return __( 'Did my latest post get traffic?', 'statnive' );
			case 'q33':
				return __( 'Did my homepage get seen?', 'statnive' );
			case 'q34':
				return __( 'How many people viewed my contact page?', 'statnive' );
			case 'q35':
				return __( 'How many people viewed my about page?', 'statnive' );
			case 'q36':
				return __( 'How many people viewed my pricing page?', 'statnive' );
			case 'q37':
				return __( 'Which old posts still get traffic?', 'statnive' );
			case 'q38':
				return __( 'Which pages are losing visitors?', 'statnive' );
			case 'q39':
				return __( 'Which pages probably need updating?', 'statnive' );
			case 'q40':
				return __( 'Which pages have the longest average duration?', 'statnive' );
			case 'q41':
				return __( 'Where is my traffic coming from?', 'statnive' );
			case 'q42':
				return __( 'Which channel sends the most traffic?', 'statnive' );
			case 'q43':
				return __( 'How much traffic comes from Google?', 'statnive' );
			case 'q44':
				return __( 'How much traffic comes from organic search?', 'statnive' );
			case 'q45':
				return __( 'How much traffic comes from social media?', 'statnive' );
			case 'q46':
				return __( 'Which social network sends the most traffic?', 'statnive' );
			case 'q47':
				return __( 'How much traffic comes from direct visits?', 'statnive' );
			case 'q48':
				return __( 'Did Reddit send traffic?', 'statnive' );
			case 'q49':
				return __( 'Did Twitter/X send traffic?', 'statnive' );
			case 'q50':
				return __( 'Did Facebook send traffic?', 'statnive' );
			case 'q51':
				return __( 'Did Instagram send traffic?', 'statnive' );
			case 'q52':
				return __( 'Did YouTube send traffic?', 'statnive' );
			case 'q53':
				return __( 'Are AI tools sending traffic?', 'statnive' );
			case 'q54':
				return __( 'Is organic traffic increasing?', 'statnive' );
			case 'q55':
				return __( 'Is referral traffic increasing?', 'statnive' );
			case 'q56':
				return __( 'Which referrer sends low-quality traffic?', 'statnive' );
			case 'q57':
				return __( 'Did my campaign drive traffic?', 'statnive' );
			case 'q58':
				return __( 'Which UTM campaign sent the most visitors?', 'statnive' );
			case 'q59':
				return __( 'Which UTM source sent the most traffic?', 'statnive' );
			case 'q60':
				return __( 'Which UTM medium sent the most traffic?', 'statnive' );
			case 'q61':
				return __( 'Did my newsletter drive traffic?', 'statnive' );
			case 'q62':
				return __( 'Did my email campaign work?', 'statnive' );
			case 'q64':
				return __( 'Did my Facebook campaign send traffic?', 'statnive' );
			case 'q65':
				return __( 'Did my Google Ads campaign send traffic?', 'statnive' );
			case 'q66':
				return __( 'Which landing page did my campaign send people to?', 'statnive' );
			case 'q67':
				return __( 'Which UTM combination worked best by traffic?', 'statnive' );
			case 'q68':
				return __( 'Did Black Friday increase traffic?', 'statnive' );
			case 'q69':
				return __( 'Which campaign had the best visitor quality?', 'statnive' );
			case 'q70':
				return __( 'Which campaign had the highest bounce count?', 'statnive' );
			case 'q71':
				return __( 'Which campaign should I double down on?', 'statnive' );
			case 'q72':
				return __( 'What countries are my visitors from?', 'statnive' );
			case 'q73':
				return __( 'Which country sends the most traffic?', 'statnive' );
			case 'q74':
				return __( 'Is most of my traffic local or international?', 'statnive' );
			case 'q75':
				return __( 'What language are my visitors using?', 'statnive' );
			case 'q76':
				return __( 'What cities are my visitors from?', 'statnive' );
			case 'q77':
				return __( 'Which city sends the most traffic?', 'statnive' );
			case 'q78':
				return __( 'Did traffic from one country drop?', 'statnive' );
			case 'q79':
				return __( 'Which countries visit a specific page?', 'statnive' );
			case 'q80':
				return __( 'Which countries visit product or pricing pages?', 'statnive' );
			case 'q81':
				return __( 'How much traffic is mobile vs desktop?', 'statnive' );
			case 'q82':
				return __( 'Which device type is most common?', 'statnive' );
			case 'q83':
				return __( 'How much traffic is tablet?', 'statnive' );
			case 'q84':
				return __( 'Which browser do visitors use?', 'statnive' );
			case 'q85':
				return __( 'Which operating systems do visitors use?', 'statnive' );
			case 'q86':
				return __( 'Is mobile traffic higher than desktop?', 'statnive' );
			case 'q87':
				return __( 'Should I prioritize mobile design?', 'statnive' );
			case 'q88':
				return __( 'Which pages are mostly viewed on mobile?', 'statnive' );
			case 'q89':
				return __( 'Which OS/browser should I test first?', 'statnive' );
			case 'q90':
				return __( 'Does mobile look worse than desktop?', 'statnive' );
			case 'q91':
				return __( 'Which device type exits more often?', 'statnive' );
			case 'q92':
				return __( 'How much bot traffic is shown in device breakdown?', 'statnive' );
			case 'q93':
				return __( 'Which pages have the most bounces?', 'statnive' );
			case 'q94':
				return __( 'Which pages have the highest exit count?', 'statnive' );
			case 'q95':
				return __( 'How long do visitors stay on average?', 'statnive' );
			case 'q96':
				return __( 'How many pages does each session view?', 'statnive' );
			case 'q97':
				return __( 'Which pages keep visitors engaged?', 'statnive' );
			case 'q98':
				return __( 'Which traffic source sends engaged visitors?', 'statnive' );
			case 'q99':
				return __( 'Which pages may be broken or confusing?', 'statnive' );
			case 'q100':
				return __( 'What is the bounce rate for a page?', 'statnive' );
			case 'q101':
				return __( 'How many orders did I get?', 'statnive' );
			case 'q102':
				return __( 'How much gross revenue did I make?', 'statnive' );
			case 'q103':
				return __( 'How much net revenue did I make?', 'statnive' );
			case 'q104':
				return __( 'What is my average order value?', 'statnive' );
			case 'q105':
				return __( 'How much did I refund?', 'statnive' );
			case 'q106':
				return __( 'What is my refund rate?', 'statnive' );
			case 'q107':
				return __( 'Which channel brings the most revenue?', 'statnive' );
			case 'q108':
				return __( 'Which UTM campaign brings the most revenue?', 'statnive' );
			case 'q109':
				return __( 'Which landing page brings the most revenue?', 'statnive' );
			case 'q110':
				return __( 'Which product sells the most units?', 'statnive' );
			case 'q111':
				return __( 'Which product makes the most revenue?', 'statnive' );
			case 'q112':
				return __( 'Which coupon is used the most?', 'statnive' );
			case 'q113':
				return __( 'Which coupon gives the most discount?', 'statnive' );
			case 'q114':
				return __( 'What is my funnel conversion rate?', 'statnive' );
			case 'q115':
				return __( 'How much tax and shipping did customers pay?', 'statnive' );
			case 'q116':
				return __( 'Which custom events happened most often?', 'statnive' );
			case 'q117':
				return __( 'How many sessions included a specific event?', 'statnive' );
			case 'q118':
				return __( 'Is a specific custom event firing?', 'statnive' );
			case 'q119':
				return __( 'Is my privacy audit passing?', 'statnive' );
			case 'q120':
				return __( 'What privacy or diagnostics warnings should I fix?', 'statnive' );
			default:
				return $fallback;
		}
	}
}
