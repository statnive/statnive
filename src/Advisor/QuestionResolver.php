<?php

declare(strict_types=1);

namespace Statnive\Advisor;

use Statnive\Api\Concerns\CachesResponses;
use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side dispatch: question ID → answer.
 *
 * Plan §3 (Backend) hard rule: this resolver reuses Statnive's existing
 * `$wpdb` queries — it never invents a new SQL path. Answers therefore
 * match the dedicated reports byte-identical.
 *
 * v1 implements native handlers for the 5 default-pinned questions
 * (q2, q23, q41, q72, q81). All other questions resolve to the
 * `coming_soon` placeholder, which the UI renders as the `🟡 Coming soon`
 * chip — identical to schema-gap rows. This is by design (plan §"Free vs
 * Paid handling (v1)"): the whole question inventory is visible on day
 * one; resolver coverage extends in successive PRs on this branch.
 *
 * Future PRs (on this same branch) add handlers for the remaining 115
 * questions. The dispatch table below is the single registration point.
 */
final class QuestionResolver {

	use CachesResponses;

	/**
	 * Resolve a batch of question IDs into a list of answers.
	 *
	 * Server-Timing breakdown is captured per-question and exposed via
	 * the `server_timing` envelope key so the controller can mirror it
	 * into the HTTP `Server-Timing` header.
	 *
	 * @param array<int, string>   $ids        Question IDs to resolve.
	 * @param array<string, mixed> $date_range ['from' => 'Y-m-d', 'to' => 'Y-m-d'].
	 * @return array{answers:array<int, array<string,mixed>>, server_timing:array<int, array<string,mixed>>}
	 */
	public function resolve_batch( array $ids, array $date_range ): array {
		$answers       = [];
		$server_timing = [];
		$total_start   = microtime( true );

		// Build an ID→row map once for the whole batch so each resolution
		// doesn't linear-scan the 120-row inventory via Questions::find().
		// With 5 default pins this saves ~600 row comparisons per batch.
		$by_id = [];
		foreach ( Questions::with_searchable() as $row ) {
			$by_id[ $row['id'] ] = $row;
		}

		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) || '' === $id ) {
				continue;
			}

			$q_start    = microtime( true );
			$source     = 'db';
			$resolution = $this->resolve_with_row( $id, $by_id[ $id ] ?? null, $date_range, $source );
			$elapsed_ms = ( microtime( true ) - $q_start ) * 1000;

			$answers[]       = $resolution;
			$server_timing[] = [
				'id'   => $id,
				'ms'   => round( $elapsed_ms, 1 ),
				'desc' => $source,
			];
		}

		$server_timing[] = [
			'id'   => 'total',
			'ms'   => round( ( microtime( true ) - $total_start ) * 1000, 1 ),
			'desc' => 'batch',
		];

		return [
			'answers'       => $answers,
			'server_timing' => $server_timing,
		];
	}

	/**
	 * Resolve a single question. Wraps each handler in cache+try/catch so
	 * a single bad question doesn't tank the batch (plan §Test plan B).
	 *
	 * @param string               $id         Question ID.
	 * @param array<string, mixed> $date_range Date range params.
	 * @param string               $source     OUT — set to 'cache' on hit, 'db'
	 *                                          on resolved query, 'placeholder'
	 *                                          on coming-soon stub.
	 * @return array<string, mixed>
	 */
	public function resolve_one( string $id, array $date_range, string &$source ): array {
		return $this->resolve_with_row( $id, Questions::find( $id ), $date_range, $source );
	}

	/**
	 * Resolve a single question given an already-looked-up inventory row.
	 *
	 * Internal helper so `resolve_batch()` can prebuild the ID→row map once
	 * per batch and avoid N linear scans across the 120-row inventory.
	 *
	 * @param string                    $id         Question ID.
	 * @param array<string, mixed>|null $q          Inventory row, or null when ID is unknown.
	 * @param array<string, mixed>      $date_range Date range params.
	 * @param string                    $source     OUT — `cache` / `db` / `placeholder`.
	 * @return array<string, mixed>
	 */
	private function resolve_with_row( string $id, ?array $q, array $date_range, string &$source ): array {
		if ( null === $q ) {
			$source = 'placeholder';
			return $this->error( $id, 'unknown_question', 'Question not in inventory.' );
		}

		// Coming-soon shape — render-only, no query — for:
		// • schema-gap questions (need v1.1 columns).
		// • Paid-per-CSV questions in v1 (plan: "all v1 ships Free, Paid
		// get same coming-soon chip — no upgrade CTA").
		// • questions without a native handler yet (subsequent PR work).
		if ( $this->is_coming_soon( $q ) ) {
			$source = 'placeholder';
			return $this->coming_soon( $q );
		}

		$from = (string) ( $date_range['from'] ?? gmdate( 'Y-m-d', strtotime( '-7 days' ) ) );
		$to   = (string) ( $date_range['to'] ?? gmdate( 'Y-m-d' ) );

		// Locale is included in the cache key as a forward-compat hedge —
		// v1 answers are numeric/identifier shapes with no localized text,
		// so the locale-keyed split wastes one cache slot per locale per
		// question. We keep the key because future handlers (e.g. channel
		// labels, country names returned via __()) WILL return locale-
		// specific strings, and silently flipping the key later would
		// strand stale cached strings across a switch_to_locale() call.
		// Use `determine_locale()` so per-user profile-locale overrides
		// don't share cache slots between users on the same site.
		$cache_params = [
			'q'      => $id,
			'from'   => $from,
			'to'     => $to,
			'locale' => function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : 'en_US' ),
		];

		$cached = $this->get_cached_response( 'advisor_answer', $cache_params );
		if ( null !== $cached ) {
			$source = 'cache';
			return $cached;
		}

		try {
			$answer = $this->dispatch( $id, $from, $to, $q );
		} catch ( \Throwable $e ) {
			// Log the full message server-side so support can diagnose, but
			// return a constant string to the client. `$e->getMessage()` can
			// surface SQL fragments / internal table-name expectations on
			// `prepare()` failures and would be a data-exposure leak.
			if ( function_exists( 'error_log' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[statnive] advisor handler error for %s: %s', $id, $e->getMessage() ) );
			}
			$source = 'placeholder';
			return $this->error( $id, 'handler_error', 'Failed to resolve question.' );
		}

		$this->set_cached_response( 'advisor_answer', $cache_params, $answer, $from, $to );
		$source = 'db';
		return $answer;
	}

	/**
	 * Dispatch a single question to its handler.
	 *
	 * V1 covers the 5 default pinned questions. Subsequent PRs on this
	 * branch extend coverage one category at a time. Adding a handler:
	 * add a `case` here and a corresponding `answer_q{N}()` method below.
	 *
	 * @param string               $id   Question ID.
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Full inventory row for the question.
	 * @return array<string, mixed>
	 */
	private function dispatch( string $id, string $from, string $to, array $q ): array {
		switch ( $id ) {
			// Traffic Overview (cat 1) — all served by /summary aggregations.
			case 'q1':
				return $this->answer_visitors_today( $q );
			case 'q2':
				return $this->answer_q2( $from, $to, $q );
			case 'q3':
				return $this->answer_pageviews_range( $from, $to, $q );
			case 'q4':
				return $this->answer_sessions_range( $from, $to, $q );
			case 'q5':
				return $this->answer_today_vs_yesterday( $q );
			case 'q6':
				return $this->answer_period_delta( $from, $to, $q );
			case 'q7':
				return $this->answer_best_day( $from, $to, $q );
			case 'q8':
				return $this->answer_worst_day( $from, $to, $q );
			case 'q9':
				return $this->answer_trend( $from, $to, $q );
			case 'q10':
				return $this->answer_drop_anomaly( $from, $to, $q );
			case 'q11':
				return $this->answer_spike_anomaly( $from, $to, $q );

			// Real-time & Tracking Health (cat 2) — Q13..Q20.
			case 'q13':
				return $this->answer_active_now( $q );
			case 'q14':
				return $this->answer_active_pages( $q );
			case 'q15':
				return $this->answer_tracking_status( $q );
			case 'q16':
			case 'q18':
				return $this->answer_recent_events_status( $q );
			case 'q17':
				return $this->answer_data_today( $q );
			case 'q19':
				return $this->answer_pages_zero_visits( $from, $to, $q );
			case 'q20':
				return $this->answer_zero_traffic_status( $from, $to, $q );

			// Pages & Content (cat 3) — Q23..Q37 Free.
			case 'q23':
				return $this->answer_q23( $from, $to, $q );
			case 'q24':
				return $this->answer_top_posts( $from, $to, $q );
			case 'q25':
				return $this->answer_top_page_today( $q );
			case 'q26':
				return $this->answer_top_page_week( $from, $to, $q );
			case 'q31':
				return $this->answer_top_page_titles( $from, $to, $q );
			case 'q32':
				return $this->answer_latest_post_traffic( $q );
			case 'q33':
				return $this->answer_homepage_views( $from, $to, $q );
			case 'q34':
			case 'q35':
			case 'q36':
				return $this->answer_named_page_views( $from, $to, $q, $id );
			case 'q37':
				return $this->answer_evergreen_posts( $from, $to, $q );

			// Referrers & Channels (cat 4) — Q41..Q55 Free.
			case 'q41':
				return $this->answer_q41( $from, $to, $q );
			case 'q42':
				return $this->answer_top_channel( $from, $to, $q );
			case 'q43':
				return $this->answer_channel_google( $from, $to, $q );
			case 'q44':
				return $this->answer_channel_filter( $from, $to, $q, 'Organic Search' );
			case 'q45':
				return $this->answer_channel_filter( $from, $to, $q, 'Social' );
			case 'q46':
				return $this->answer_top_social_network( $from, $to, $q );
			case 'q47':
				return $this->answer_channel_filter( $from, $to, $q, 'Direct' );
			case 'q48':
				return $this->answer_named_referrer( $from, $to, $q, [ 'reddit' ] );
			case 'q49':
				return $this->answer_named_referrer( $from, $to, $q, [ 'twitter', 'x.com', 't.co' ] );
			case 'q50':
				return $this->answer_named_referrer( $from, $to, $q, [ 'facebook', 'fb.com', 'l.facebook' ] );
			case 'q51':
				return $this->answer_named_referrer( $from, $to, $q, [ 'instagram', 'l.instagram' ] );
			case 'q52':
				return $this->answer_named_referrer( $from, $to, $q, [ 'youtube', 'youtu.be' ] );
			case 'q53':
				return $this->answer_channel_filter( $from, $to, $q, 'AI Assistants' );
			case 'q54':
				return $this->answer_channel_trend( $from, $to, $q, 'Organic Search' );
			case 'q55':
				return $this->answer_channel_trend( $from, $to, $q, 'Referral' );

			// Campaigns & UTM (cat 5) — Q57..Q67 Free.
			case 'q57':
				return $this->answer_utm_groupby( $from, $to, $q, 'campaign' );
			case 'q58':
				return $this->answer_utm_groupby( $from, $to, $q, 'campaign' );
			case 'q59':
				return $this->answer_utm_groupby( $from, $to, $q, 'source' );
			case 'q60':
				return $this->answer_utm_groupby( $from, $to, $q, 'medium' );
			case 'q61':
			case 'q62':
				return $this->answer_utm_medium_filter( $from, $to, $q, [ 'email', 'newsletter' ] );
			case 'q63':
				return $this->answer_utm_groupby( $from, $to, $q, 'campaign' );
			case 'q64':
				return $this->answer_utm_source_filter( $from, $to, $q, [ 'facebook', 'fb', 'meta' ] );
			case 'q65':
				return $this->answer_utm_source_filter( $from, $to, $q, [ 'google', 'googleads' ] );
			case 'q66':
				return $this->answer_utm_landing( $from, $to, $q );
			case 'q67':
				return $this->answer_utm_combo( $from, $to, $q );

			// Geography & Language (cat 6) — Q72..Q75 Free.
			case 'q72':
				return $this->answer_q72( $from, $to, $q );
			case 'q73':
				return $this->answer_top_country( $from, $to, $q );
			case 'q74':
				return $this->answer_local_vs_international( $from, $to, $q );
			case 'q75':
				return $this->answer_top_languages( $from, $to, $q );

			// Devices & Browsers (cat 7) — Q81..Q87 Free.
			case 'q81':
				return $this->answer_q81( $from, $to, $q );
			case 'q82':
				return $this->answer_top_device( $from, $to, $q );
			case 'q83':
				return $this->answer_tablet_share( $from, $to, $q );
			case 'q84':
				return $this->answer_top_browsers( $from, $to, $q );
			case 'q85':
				return $this->answer_top_oss( $from, $to, $q );
			case 'q86':
				return $this->answer_mobile_vs_desktop( $from, $to, $q );
			case 'q87':
				return $this->answer_mobile_priority( $from, $to, $q );

			default:
				// No native handler yet — treat as coming-soon for v1.
				return $this->coming_soon( $q );
		}
	}

	// =================================================================
	// Coming-soon + error envelopes
	// =================================================================

	/**
	 * Is this question deferred to a later version (no native v1 handler)?
	 *
	 * @param array<string, mixed> $q Inventory row.
	 */
	private function is_coming_soon( array $q ): bool {
		if ( ( $q['plan'] ?? Questions::PLAN_FREE ) === Questions::PLAN_PAID ) {
			return true;
		}
		if ( isset( $q['depends_on_schema'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Coming-soon envelope. Distinguishes Paid (Growth v2 unlock) from
	 * schema-gap (v1.1 column migration) so the UI can show the right copy.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function coming_soon( array $q ): array {
		$reason = isset( $q['depends_on_schema'] )
			? 'schema_gap_v1_1'
			: ( ( $q['plan'] ?? '' ) === Questions::PLAN_PAID ? 'paid_growth_v2' : 'handler_pending' );

		return [
			'id'         => $q['id'],
			'status'     => Questions::STATUS_COMING_SOON,
			'reason'     => $reason,
			'value'      => null,
			'viz'        => Questions::VIZ_COMING_SOON,
			'source'     => $q['surface'] ?? null,
			'plan'       => $q['plan'] ?? Questions::PLAN_FREE,
			'confidence' => $q['confidence'] ?? Questions::CONF_DIRECT,
		];
	}

	/**
	 * Error envelope for a failed resolution.
	 *
	 * @param string $id      Question ID.
	 * @param string $code    Short machine code (e.g. `unknown_question`).
	 * @param string $message Human-readable error message.
	 * @return array<string, mixed>
	 */
	private function error( string $id, string $code, string $message ): array {
		return [
			'id'      => $id,
			'status'  => Questions::STATUS_ERROR,
			'code'    => $code,
			'message' => $message,
			'value'   => null,
			'viz'     => Questions::VIZ_ERROR,
		];
	}

	/**
	 * Success envelope.
	 *
	 * @param array<string, mixed> $q            Inventory row.
	 * @param mixed                $value        Answer value (KPI number, table rows, …).
	 * @param string|null          $viz_override Override the inventory's `viz_hint`.
	 * @return array<string, mixed>
	 */
	private function ok( array $q, $value, ?string $viz_override = null ): array {
		return [
			'id'         => $q['id'],
			'status'     => Questions::STATUS_OK,
			'value'      => $value,
			'viz'        => $viz_override ?? ( $q['viz_hint'] ?? Questions::VIZ_KPI_TILE ),
			'source'     => $q['surface'] ?? null,
			'plan'       => $q['plan'] ?? Questions::PLAN_FREE,
			'confidence' => $q['confidence'] ?? Questions::CONF_DIRECT,
		];
	}

	// =================================================================
	// Question handlers (v1 — 5 default-pinned questions)
	// =================================================================

	/**
	 * Q2 — "How many people visited this week?"
	 *
	 * Surface: /summary. Sums `summary_totals.visitors` over [from, to]
	 * + falls back to raw `sessions` for today, matching the proven
	 * pattern in SummaryController.
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_q2( string $from, string $to, array $q ): array {
		global $wpdb;
		$totals_table = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$visitors_aggregated = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(visitors), 0) FROM %i WHERE date BETWEEN %s AND %s',
				$totals_table,
				$from,
				$to
			)
		);

		$today                = gmdate( 'Y-m-d' );
		$visitors_today_extra = 0;
		if ( $from <= $today && $to >= $today ) {
			$sessions_table       = TableRegistry::get( 'sessions' );
			$visitors_today_extra = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT visitor_id) FROM %i WHERE DATE(started_at) = %s',
					$sessions_table,
					$today
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'visitors' => $visitors_aggregated + $visitors_today_extra,
				'from'     => $from,
				'to'       => $to,
			]
		);
	}

	/**
	 * Q23 — "What are my top pages?"
	 *
	 * Surface: /pages. Aggregates pageviews per resource URI from `summary`
	 * across [from, to], orders desc, top 10.
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_q23( string $from, string $to, array $q ): array {
		global $wpdb;
		$summary_table = TableRegistry::get( 'summary' );
		$uris_table    = TableRegistry::get( 'resource_uris' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ru.uri AS uri, COALESCE(SUM(s.views), 0) AS views
				FROM %i s
				JOIN %i ru ON ru.ID = s.resource_uri_id
				WHERE s.date BETWEEN %s AND %s
				GROUP BY ru.uri
				ORDER BY views DESC
				LIMIT 10',
				$summary_table,
				$uris_table,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$normalized = array_map(
			static fn( $r ) => [
				'uri'   => (string) $r['uri'],
				'views' => (int) $r['views'],
			],
			is_array( $rows ) ? $rows : []
		);

		return $this->ok( $q, [ 'rows' => $normalized ], Questions::VIZ_TABLE );
	}

	/**
	 * Q41 — "Where is my traffic coming from?"
	 *
	 * Surface: /sources?group_by=channel. Aggregates sessions per
	 * `referrer.channel` from `sessions` joined to `referrers`. Returns a
	 * donut-shape `[ {channel, sessions, visitors} ]`.
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_q41( string $from, string $to, array $q ): array {
		global $wpdb;
		$sessions_table  = TableRegistry::get( 'sessions' );
		$referrers_table = TableRegistry::get( 'referrers' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COALESCE(r.channel, "Direct") AS channel,
						COUNT(DISTINCT s.ID) AS sessions,
						COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i s
				LEFT JOIN %i r ON r.ID = s.referrer_id
				WHERE s.started_at BETWEEN %s AND %s
				GROUP BY channel
				ORDER BY sessions DESC',
				$sessions_table,
				$referrers_table,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$normalized = array_map(
			static fn( $r ) => [
				'channel'  => (string) $r['channel'],
				'sessions' => (int) $r['sessions'],
				'visitors' => (int) $r['visitors'],
			],
			is_array( $rows ) ? $rows : []
		);

		return $this->ok( $q, [ 'rows' => $normalized ], Questions::VIZ_DONUT );
	}

	/**
	 * Q72 — "What countries are my visitors from?"
	 *
	 * Surface: /dimensions/countries. Aggregates distinct visitors per
	 * country across [from, to], orders desc.
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_q72( string $from, string $to, array $q ): array {
		global $wpdb;
		$sessions_table  = TableRegistry::get( 'sessions' );
		$countries_table = TableRegistry::get( 'countries' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.code AS code, COALESCE(c.name, "Unknown") AS name,
						COUNT(DISTINCT s.visitor_id) AS visitors,
						COUNT(DISTINCT s.ID) AS sessions
				FROM %i s
				LEFT JOIN %i c ON c.ID = s.country_id
				WHERE DATE(s.started_at) BETWEEN %s AND %s
				GROUP BY c.code, c.name
				ORDER BY visitors DESC
				LIMIT 25',
				$sessions_table,
				$countries_table,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$normalized = array_map(
			static fn( $r ) => [
				'code'     => (string) ( $r['code'] ?? '' ),
				'name'     => (string) $r['name'],
				'visitors' => (int) $r['visitors'],
				'sessions' => (int) $r['sessions'],
			],
			is_array( $rows ) ? $rows : []
		);

		return $this->ok( $q, [ 'rows' => $normalized ], Questions::VIZ_MAP );
	}

	/**
	 * Q81 — "How much traffic is mobile vs desktop?"
	 *
	 * Surface: /dimensions/devices. Aggregates sessions + visitors per
	 * device_type code.
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_q81( string $from, string $to, array $q ): array {
		global $wpdb;
		$sessions_table = TableRegistry::get( 'sessions' );
		$devices_table  = TableRegistry::get( 'device_types' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COALESCE(dt.name, "Unknown") AS device,
						COUNT(DISTINCT s.ID) AS sessions,
						COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i s
				LEFT JOIN %i dt ON dt.ID = s.device_type_id
				WHERE DATE(s.started_at) BETWEEN %s AND %s
				GROUP BY device
				ORDER BY sessions DESC',
				$sessions_table,
				$devices_table,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$normalized = array_map(
			static fn( $r ) => [
				'device'   => (string) $r['device'],
				'sessions' => (int) $r['sessions'],
				'visitors' => (int) $r['visitors'],
			],
			is_array( $rows ) ? $rows : []
		);

		return $this->ok( $q, [ 'rows' => $normalized ], Questions::VIZ_BAR );
	}

	// =================================================================
	// Traffic Overview handlers (cat 1) — Q1 + Q3..Q11
	//
	// All read from the pre-aggregated `summary_totals` table; today gets
	// a real-time fallback to raw `sessions` so the answer matches what
	// SummaryController shows for the same date range.
	// =================================================================

	/**
	 * Q1 — "How many people visited my site today?"
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_visitors_today( array $q ): array {
		global $wpdb;
		$today          = gmdate( 'Y-m-d' );
		$sessions_table = TableRegistry::get( 'sessions' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$visitors = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT visitor_id) FROM %i WHERE DATE(started_at) = %s',
				$sessions_table,
				$today
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'visitors' => $visitors,
				'from'     => $today,
				'to'       => $today,
			],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Q3 — "How many pageviews did I get?"
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_pageviews_range( string $from, string $to, array $q ): array {
		$totals = $this->load_period_totals( $from, $to );
		return $this->ok(
			$q,
			[
				'pageviews' => $totals['views'],
				'from'      => $from,
				'to'        => $to,
			],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Q4 — "How many sessions did I get?"
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_sessions_range( string $from, string $to, array $q ): array {
		$totals = $this->load_period_totals( $from, $to );
		return $this->ok(
			$q,
			[
				'sessions' => $totals['sessions'],
				'from'     => $from,
				'to'       => $to,
			],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Q5 — "Is my traffic up or down compared with yesterday?"
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_today_vs_yesterday( array $q ): array {
		$today_str     = gmdate( 'Y-m-d' );
		$yesterday_str = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		$today     = $this->load_day_visitors( $today_str );
		$yesterday = $this->load_day_visitors( $yesterday_str );

		$delta_pct = ( $yesterday > 0 ) ? ( ( $today - $yesterday ) / $yesterday ) * 100 : 0.0;

		return $this->ok(
			$q,
			[
				'current'     => $today,
				'previous'    => $yesterday,
				'delta_pct'   => round( $delta_pct, 1 ),
				'current_at'  => $today_str,
				'previous_at' => $yesterday_str,
			],
			Questions::VIZ_DELTA
		);
	}

	/**
	 * Q6 — "Is my traffic up or down compared with last week?"
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_period_delta( string $from, string $to, array $q ): array {
		$current   = $this->load_period_totals( $from, $to );
		$length    = max( 1, ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS + 1 );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -' . ( $length - 1 ) . ' day' ) );
		$previous  = $this->load_period_totals( $prev_from, $prev_to );

		$delta_pct = ( $previous['visitors'] > 0 )
			? ( ( $current['visitors'] - $previous['visitors'] ) / $previous['visitors'] ) * 100
			: 0.0;

		return $this->ok(
			$q,
			[
				'current'   => $current['visitors'],
				'previous'  => $previous['visitors'],
				'delta_pct' => round( $delta_pct, 1 ),
				'from'      => $from,
				'to'        => $to,
				'prev_from' => $prev_from,
				'prev_to'   => $prev_to,
			],
			Questions::VIZ_DELTA
		);
	}

	/**
	 * Q7 — "Which day had the most traffic?"
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_best_day( string $from, string $to, array $q ): array {
		return $this->ok(
			$q,
			[ 'rows' => $this->load_daily_visitors( $from, $to, 'DESC' ) ],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Q8 — "Which date had the lowest traffic?"
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_worst_day( string $from, string $to, array $q ): array {
		return $this->ok(
			$q,
			[ 'rows' => $this->load_daily_visitors( $from, $to, 'ASC' ) ],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Q9 — "What is my traffic trend over time?"
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_trend( string $from, string $to, array $q ): array {
		return $this->ok(
			$q,
			[ 'rows' => $this->load_daily_series( $from, $to ) ],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Q10 — "Did traffic suddenly drop?" (z-score proxy)
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_drop_anomaly( string $from, string $to, array $q ): array {
		return $this->ok( $q, $this->anomaly_summary( $from, $to, 'drop' ), Questions::VIZ_DELTA );
	}

	/**
	 * Q11 — "Did traffic suddenly spike?"
	 *
	 * @param string               $from Date range start (`Y-m-d`).
	 * @param string               $to   Date range end (`Y-m-d`).
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_spike_anomaly( string $from, string $to, array $q ): array {
		return $this->ok( $q, $this->anomaly_summary( $from, $to, 'spike' ), Questions::VIZ_DELTA );
	}

	// =================================================================
	// Traffic Overview helpers
	// =================================================================

	/**
	 * Sum visitors/sessions/views across `summary_totals` in [from, to],
	 * with a real-time top-up for today read straight from `sessions`/`views`.
	 *
	 * @param string $from Date range start (`Y-m-d`).
	 * @param string $to   Date range end (`Y-m-d`).
	 * @return array{visitors:int,sessions:int,views:int}
	 */
	private function load_period_totals( string $from, string $to ): array {
		global $wpdb;
		$totals_table   = TableRegistry::get( 'summary_totals' );
		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );
		$today          = gmdate( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(visitors), 0) AS visitors,
					COALESCE(SUM(sessions), 0) AS sessions,
					COALESCE(SUM(views), 0) AS views
				FROM %i WHERE date BETWEEN %s AND %s',
				$totals_table,
				$from,
				$to
			),
			ARRAY_A
		);

		$out = [
			'visitors' => (int) ( $row['visitors'] ?? 0 ),
			'sessions' => (int) ( $row['sessions'] ?? 0 ),
			'views'    => (int) ( $row['views'] ?? 0 ),
		];

		if ( $from <= $today && $to >= $today ) {
			$today_row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT s.visitor_id) AS visitors,
						COUNT(DISTINCT s.ID) AS sessions,
						COUNT(v.ID) AS views
					FROM %i s
					LEFT JOIN %i v ON v.session_id = s.ID AND DATE(v.viewed_at) = %s
					WHERE DATE(s.started_at) = %s',
					$sessions_table,
					$views_table,
					$today,
					$today
				),
				ARRAY_A
			);
			if ( is_array( $today_row ) ) {
				$out['visitors'] += (int) $today_row['visitors'];
				$out['sessions'] += (int) $today_row['sessions'];
				$out['views']    += (int) $today_row['views'];
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $out;
	}

	/**
	 * Load visitor count for a single calendar day. Reads `summary_totals`
	 * for past days; live-counts from `sessions` for today.
	 *
	 * @param string $day Date (`Y-m-d`).
	 */
	private function load_day_visitors( string $day ): int {
		global $wpdb;
		$today = gmdate( 'Y-m-d' );

		if ( $day === $today ) {
			$sessions_table = TableRegistry::get( 'sessions' );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT visitor_id) FROM %i WHERE DATE(started_at) = %s',
					$sessions_table,
					$day
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$totals_table = TableRegistry::get( 'summary_totals' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(visitors, 0) FROM %i WHERE date = %s',
				$totals_table,
				$day
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Top/bottom days by visitor count over [from, to], limit 5. Today's
	 * visitor count is sourced from raw `sessions` (matching `load_day_visitors`),
	 * so q7/q8 never undercount today vs. q1/q5 because of a stale roll-up.
	 *
	 * @param string $from      Date range start (`Y-m-d`).
	 * @param string $to        Date range end (`Y-m-d`).
	 * @param string $order_dir `ASC` for worst-first, `DESC` for best-first.
	 * @return array<int, array{date:string,visitors:int}>
	 */
	private function load_daily_visitors( string $from, string $to, string $order_dir ): array {
		$series = $this->load_daily_series( $from, $to );
		$asc    = ( 'ASC' === strtoupper( $order_dir ) );
		usort(
			$series,
			static function ( $a, $b ) use ( $asc ) {
				$av = (int) ( $a['visitors'] ?? 0 );
				$bv = (int) ( $b['visitors'] ?? 0 );
				if ( $av === $bv ) {
					return strcmp( (string) ( $a['date'] ?? '' ), (string) ( $b['date'] ?? '' ) );
				}
				return $asc ? ( $av - $bv ) : ( $bv - $av );
			}
		);

		return array_map(
			static fn( $r ) => [
				'date'     => (string) ( $r['date'] ?? '' ),
				'visitors' => (int) ( $r['visitors'] ?? 0 ),
			],
			array_slice( $series, 0, 5 )
		);
	}

	/**
	 * Daily series (date, visitors, sessions, views) for the trend chart.
	 * Today's row is sourced live from `sessions`/`views` so the trend's
	 * last point matches what q1/q5 report for the same day.
	 *
	 * @param string $from Date range start (`Y-m-d`).
	 * @param string $to   Date range end (`Y-m-d`).
	 * @return array<int, array{date:string,visitors:int,sessions:int,views:int}>
	 */
	private function load_daily_series( string $from, string $to ): array {
		global $wpdb;
		$totals_table = TableRegistry::get( 'summary_totals' );
		$today        = gmdate( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT date,
					COALESCE(visitors, 0) AS visitors,
					COALESCE(sessions, 0) AS sessions,
					COALESCE(views, 0) AS views
				FROM %i WHERE date BETWEEN %s AND %s
				ORDER BY date ASC',
				$totals_table,
				$from,
				$to
			),
			ARRAY_A
		);

		$out = is_array( $rows ) ? array_map(
			static fn( $r ) => [
				'date'     => (string) $r['date'],
				'visitors' => (int) $r['visitors'],
				'sessions' => (int) $r['sessions'],
				'views'    => (int) $r['views'],
			],
			$rows
		) : [];

		if ( $from <= $today && $to >= $today ) {
			$sessions_table = TableRegistry::get( 'sessions' );
			$views_table    = TableRegistry::get( 'views' );
			$today_row      = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT s.visitor_id) AS visitors,
						COUNT(DISTINCT s.ID) AS sessions,
						COUNT(v.ID) AS views
					FROM %i s
					LEFT JOIN %i v ON v.session_id = s.ID AND DATE(v.viewed_at) = %s
					WHERE DATE(s.started_at) = %s',
					$sessions_table,
					$views_table,
					$today,
					$today
				),
				ARRAY_A
			);
			if ( is_array( $today_row ) ) {
				$out   = array_values( array_filter( $out, static fn( $r ) => ( $r['date'] ?? '' ) !== $today ) );
				$out[] = [
					'date'     => $today,
					'visitors' => (int) ( $today_row['visitors'] ?? 0 ),
					'sessions' => (int) ( $today_row['sessions'] ?? 0 ),
					'views'    => (int) ( $today_row['views'] ?? 0 ),
				];
				usort( $out, static fn( $a, $b ) => strcmp( (string) $a['date'], (string) $b['date'] ) );
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $out;
	}

	/**
	 * Anomaly summary for Q10/Q11: compares the last day of the range to
	 * the prior-7-day mean. Returns the worst single drop or biggest spike
	 * along with a simple normalized score.
	 *
	 * @param string $from      Date range start (`Y-m-d`).
	 * @param string $to        Date range end (`Y-m-d`).
	 * @param string $direction Either `drop` or `spike`.
	 * @return array<string, mixed>
	 */
	private function anomaly_summary( string $from, string $to, string $direction ): array {
		$series = $this->load_daily_series( $from, $to );
		if ( count( $series ) < 2 ) {
			return [
				'has_anomaly' => false,
				'rows'        => [],
			];
		}

		$last  = $series[ array_key_last( $series ) ];
		$prior = array_slice( $series, max( 0, count( $series ) - 8 ), 7 );
		$mean  = 0;
		if ( ! empty( $prior ) ) {
			$mean = array_sum( array_column( $prior, 'visitors' ) ) / count( $prior );
		}

		$delta_pct = ( $mean > 0 ) ? ( ( $last['visitors'] - $mean ) / $mean ) * 100 : 0.0;

		$is_drop  = $delta_pct <= -15;
		$is_spike = $delta_pct >= 25;
		$has      = ( 'drop' === $direction ) ? $is_drop : $is_spike;

		return [
			'has_anomaly' => $has,
			'current'     => (int) $last['visitors'],
			'baseline'    => (int) round( $mean ),
			'delta_pct'   => round( $delta_pct, 1 ),
			'on_date'     => $last['date'],
		];
	}

	// =================================================================
	// Real-time & Tracking Health handlers (cat 2)
	// =================================================================

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_active_now( array $q ): array {
		return $this->ok( $q, [ 'visitors' => $this->count_active_visitors( 300 ) ], Questions::VIZ_KPI_TILE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_active_pages( array $q ): array {
		global $wpdb;
		$sessions_table = TableRegistry::get( 'sessions' );
		$views_table    = TableRegistry::get( 'views' );
		$uris_table     = TableRegistry::get( 'resource_uris' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ru.uri AS uri, COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i s
				JOIN %i v ON v.session_id = s.ID
				JOIN %i ru ON ru.ID = v.resource_uri_id
				WHERE v.viewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)
				GROUP BY ru.uri
				ORDER BY visitors DESC
				LIMIT 10',
				$sessions_table,
				$views_table,
				$uris_table,
				300
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'uri'      => (string) $r['uri'],
						'visitors' => (int) $r['visitors'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_tracking_status( array $q ): array {
		$recent_5min = $this->count_active_visitors( 300 );
		$today_total = $this->load_day_visitors( gmdate( 'Y-m-d' ) );

		if ( $recent_5min > 0 ) {
			$status = 'active';
		} elseif ( $today_total > 0 ) {
			$status = 'quiet';
		} else {
			$status = 'stalled';
		}

		return $this->ok(
			$q,
			[
				'status'      => $status,
				'active_5min' => $recent_5min,
				'today_total' => $today_total,
			],
			Questions::VIZ_DELTA
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_recent_events_status( array $q ): array {
		return $this->ok( $q, [ 'visitors' => $this->count_active_visitors( 1800 ) ], Questions::VIZ_KPI_TILE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_data_today( array $q ): array {
		return $this->ok(
			$q,
			[ 'visitors' => $this->load_day_visitors( gmdate( 'Y-m-d' ) ) ],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_pages_zero_visits( string $from, string $to, array $q ): array {
		global $wpdb;
		$summary_table = TableRegistry::get( 'summary' );
		$uris_table    = TableRegistry::get( 'resource_uris' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ru.uri AS uri, COALESCE(SUM(s.views), 0) AS views
				FROM %i ru
				LEFT JOIN %i s ON s.resource_uri_id = ru.ID AND s.date BETWEEN %s AND %s
				GROUP BY ru.uri
				HAVING views = 0
				LIMIT 20',
				$uris_table,
				$summary_table,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'uri'   => (string) $r['uri'],
						'views' => (int) $r['views'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_zero_traffic_status( string $from, string $to, array $q ): array {
		$totals = $this->load_period_totals( $from, $to );
		$zero   = ( 0 === $totals['visitors'] + $totals['sessions'] + $totals['views'] );
		return $this->ok(
			$q,
			[
				'visitors' => $totals['visitors'],
				'zero'     => $zero,
			],
			Questions::VIZ_KPI_TILE
		);
	}

	// =================================================================
	// Pages & Content handlers (cat 3) — Q24..Q37 Free
	// =================================================================

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_posts( string $from, string $to, array $q ): array {
		return $this->ok( $q, [ 'rows' => $this->load_top_pages( $from, $to, '/blog/' ) ], Questions::VIZ_TABLE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_page_today( array $q ): array {
		$today = gmdate( 'Y-m-d' );
		return $this->ok( $q, [ 'rows' => $this->load_top_pages( $today, $today, null ) ], Questions::VIZ_TABLE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_page_week( string $from, string $to, array $q ): array {
		return $this->ok( $q, [ 'rows' => $this->load_top_pages( $from, $to, null ) ], Questions::VIZ_TABLE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_page_titles( string $from, string $to, array $q ): array {
		global $wpdb;
		$summary_table = TableRegistry::get( 'summary' );
		$uris_table    = TableRegistry::get( 'resource_uris' );
		$resources     = TableRegistry::get( 'resources' );

		// Title lives on `resources.cached_title`; summary→resource_uris→resources
		// is the canonical path (PagesController uses the same join chain).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COALESCE(r.cached_title, "Untitled") AS title,
					COALESCE(SUM(s.views), 0) AS views
				FROM %i s
				JOIN %i ru ON ru.ID = s.resource_uri_id
				JOIN %i r ON r.resource_id = ru.resource_id
				WHERE s.date BETWEEN %s AND %s AND r.cached_title IS NOT NULL AND r.cached_title <> ""
				GROUP BY r.cached_title
				ORDER BY views DESC
				LIMIT 10',
				$summary_table,
				$uris_table,
				$resources,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'title' => (string) ( $r['title'] ?? '' ),
						'views' => (int) $r['views'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param array<string, mixed> $q Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_latest_post_traffic( array $q ): array {
		$latest = get_posts(
			[
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		if ( empty( $latest ) ) {
			return $this->ok( $q, [ 'visitors' => 0 ], Questions::VIZ_KPI_TILE );
		}

		$uri = ( wp_parse_url( get_permalink( (int) $latest[0] ), PHP_URL_PATH ) ?? '/' );
		return $this->ok(
			$q,
			[
				'visitors' => $this->views_for_uri_pattern( $uri ),
				'uri'      => $uri,
			],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_homepage_views( string $from, string $to, array $q ): array {
		return $this->ok(
			$q,
			[ 'visitors' => $this->views_for_exact_uri( $from, $to, '/' ) ],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Q34/Q35/Q36 — contact / about / pricing page views (named-URI pattern).
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @param string               $id   Question ID (`q34`, `q35`, or `q36`).
	 * @return array<string, mixed>
	 */
	private function answer_named_page_views( string $from, string $to, array $q, string $id ): array {
		$pattern = match ( $id ) {
			'q34' => '/contact',
			'q35' => '/about',
			'q36' => '/pricing',
			default => '/',
		};
		return $this->ok(
			$q,
			[ 'visitors' => $this->views_for_uri_pattern( $pattern, $from, $to ) ],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_evergreen_posts( string $from, string $to, array $q ): array {
		// Evergreen heuristic: posts published 180+ days ago (per
		// `resources.cached_date`) that still got traffic in [from, to].
		// Posts without a cached_date (non-WP resources or pre-backfill)
		// are excluded so the answer doesn't mislead.
		global $wpdb;
		$summary   = TableRegistry::get( 'summary' );
		$uris      = TableRegistry::get( 'resource_uris' );
		$resources = TableRegistry::get( 'resources' );
		$cutoff    = gmdate( 'Y-m-d', strtotime( '-180 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ru.uri AS uri, COALESCE(SUM(s.views), 0) AS views
				FROM %i s
				JOIN %i ru ON ru.ID = s.resource_uri_id
				JOIN %i r ON r.resource_id = ru.resource_id
				WHERE s.date BETWEEN %s AND %s
					AND r.cached_date IS NOT NULL
					AND DATE(r.cached_date) <= %s
				GROUP BY ru.uri
				HAVING views > 0
				ORDER BY views DESC
				LIMIT 10',
				$summary,
				$uris,
				$resources,
				$from,
				$to,
				$cutoff
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'uri'   => (string) $r['uri'],
						'views' => (int) $r['views'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	// =================================================================
	// Referrers & Channels handlers (cat 4) — Q42..Q55 Free
	// =================================================================

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_channel( string $from, string $to, array $q ): array {
		$rows = $this->load_referrer_channels( $from, $to );
		return $this->ok( $q, [ 'rows' => array_slice( $rows, 0, 1 ) ], Questions::VIZ_KPI_TILE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_channel_google( string $from, string $to, array $q ): array {
		return $this->ok(
			$q,
			[ 'visitors' => $this->count_referrer_visitors( $from, $to, 'Organic Search', 'google' ) ],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from    Date range start.
	 * @param string               $to      Date range end.
	 * @param array<string, mixed> $q       Inventory row.
	 * @param string               $channel Channel code to filter by.
	 * @return array<string, mixed>
	 */
	private function answer_channel_filter( string $from, string $to, array $q, string $channel ): array {
		return $this->ok(
			$q,
			[ 'visitors' => $this->count_referrer_visitors( $from, $to, $channel ) ],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_social_network( string $from, string $to, array $q ): array {
		global $wpdb;
		$sessions  = TableRegistry::get( 'sessions' );
		$referrers = TableRegistry::get( 'referrers' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COALESCE(r.name, "Unknown") AS network,
					COUNT(DISTINCT s.ID) AS sessions
				FROM %i s
				JOIN %i r ON r.ID = s.referrer_id
				WHERE r.channel = %s AND s.started_at BETWEEN %s AND %s
				GROUP BY r.name
				ORDER BY sessions DESC
				LIMIT 10',
				$sessions,
				$referrers,
				'Social',
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'network'  => (string) $r['network'],
						'sessions' => (int) $r['sessions'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from     Date range start.
	 * @param string               $to       Date range end.
	 * @param array<string, mixed> $q        Inventory row.
	 * @param array<int, string>   $patterns Lowercase substrings to match on `referrers.name` / `referrers.domain`.
	 * @return array<string, mixed>
	 */
	private function answer_named_referrer( string $from, string $to, array $q, array $patterns ): array {
		global $wpdb;
		$sessions  = TableRegistry::get( 'sessions' );
		$referrers = TableRegistry::get( 'referrers' );

		if ( empty( $patterns ) ) {
			return $this->ok( $q, [ 'visitors' => 0 ], Questions::VIZ_KPI_TILE );
		}

		// Build a fixed-shape OR'd LIKE clause so visitors matching multiple
		// patterns (e.g. `name LIKE %twitter%` and `domain LIKE %x.com%`) are
		// counted via COUNT(DISTINCT visitor_id) rather than summed across
		// per-pattern queries (which would double-count overlapping referrers).
		//
		// Padding to a fixed N=3 lets us emit a fixed-placeholder-count SQL
		// that PCP can read as fully prepared. The sentinel
		// `__never_matches_xyzzy__` contributes zero matches when the caller
		// passes fewer than 3 patterns. No question in the v1 inventory
		// passes more than 3.
		$padded = array_pad( array_slice( $patterns, 0, 3 ), 3, '__never_matches_xyzzy__' );
		$likes  = array_map(
			static fn( $p ) => '%' . $wpdb->esc_like( strtolower( (string) $p ) ) . '%',
			$padded
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$visitors = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT s.visitor_id)
				FROM %i s
				JOIN %i r ON r.ID = s.referrer_id
				WHERE (
					(LOWER(r.name) LIKE %s OR LOWER(r.domain) LIKE %s)
					OR (LOWER(r.name) LIKE %s OR LOWER(r.domain) LIKE %s)
					OR (LOWER(r.name) LIKE %s OR LOWER(r.domain) LIKE %s)
				) AND s.started_at BETWEEN %s AND %s',
				$sessions,
				$referrers,
				$likes[0],
				$likes[0],
				$likes[1],
				$likes[1],
				$likes[2],
				$likes[2],
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok( $q, [ 'visitors' => $visitors ], Questions::VIZ_KPI_TILE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from    Date range start.
	 * @param string               $to      Date range end.
	 * @param array<string, mixed> $q       Inventory row.
	 * @param string               $channel Channel code to trend.
	 * @return array<string, mixed>
	 */
	private function answer_channel_trend( string $from, string $to, array $q, string $channel ): array {
		$current   = $this->count_referrer_visitors( $from, $to, $channel );
		$length    = max( 1, ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS + 1 );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -' . ( $length - 1 ) . ' day' ) );
		$previous  = $this->count_referrer_visitors( $prev_from, $prev_to, $channel );
		$delta_pct = ( $previous > 0 ) ? ( ( $current - $previous ) / $previous ) * 100 : 0.0;

		return $this->ok(
			$q,
			[
				'current'   => $current,
				'previous'  => $previous,
				'delta_pct' => round( $delta_pct, 1 ),
			],
			Questions::VIZ_DELTA
		);
	}

	// =================================================================
	// Campaigns & UTM handlers (cat 5) — Q57..Q67 Free
	// =================================================================

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from   Date range start.
	 * @param string               $to     Date range end.
	 * @param array<string, mixed> $q      Inventory row.
	 * @param string               $column UTM column (`source`, `medium`, `campaign`).
	 * @return array<string, mixed>
	 */
	private function answer_utm_groupby( string $from, string $to, array $q, string $column ): array {
		$column = in_array( $column, [ 'source', 'medium', 'campaign' ], true ) ? $column : 'campaign';
		global $wpdb;
		$parameters = TableRegistry::get( 'parameters' );
		$sessions   = TableRegistry::get( 'sessions' );

		// `parameters` is EAV — filter by `param_key = 'utm_<column>'` and group
		// on `param_value`. Mirrors the pivot pattern in UtmController.
		$param_key = 'utm_' . $column;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT p.param_value AS value,
					COUNT(DISTINCT s.ID) AS sessions,
					COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i p
				JOIN %i s ON s.ID = p.session_id
				WHERE p.param_key = %s
					AND p.param_value IS NOT NULL
					AND p.param_value != ""
					AND s.started_at BETWEEN %s AND %s
				GROUP BY p.param_value
				ORDER BY sessions DESC
				LIMIT 10',
				$parameters,
				$sessions,
				$param_key,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'value'    => (string) $r['value'],
						'sessions' => (int) $r['sessions'],
						'visitors' => (int) $r['visitors'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from    Date range start.
	 * @param string               $to      Date range end.
	 * @param array<string, mixed> $q       Inventory row.
	 * @param array<int, string>   $mediums UTM medium values to match (case-insensitive).
	 * @return array<string, mixed>
	 */
	private function answer_utm_medium_filter( string $from, string $to, array $q, array $mediums ): array {
		return $this->ok(
			$q,
			[ 'visitors' => $this->count_utm_visitors( $from, $to, 'medium', $mediums ) ],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from    Date range start.
	 * @param string               $to      Date range end.
	 * @param array<string, mixed> $q       Inventory row.
	 * @param array<int, string>   $sources UTM source values to match (case-insensitive).
	 * @return array<string, mixed>
	 */
	private function answer_utm_source_filter( string $from, string $to, array $q, array $sources ): array {
		return $this->ok(
			$q,
			[ 'visitors' => $this->count_utm_visitors( $from, $to, 'source', $sources ) ],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_utm_landing( string $from, string $to, array $q ): array {
		global $wpdb;
		$parameters = TableRegistry::get( 'parameters' );
		$sessions   = TableRegistry::get( 'sessions' );
		$views      = TableRegistry::get( 'views' );
		$uris       = TableRegistry::get( 'resource_uris' );

		// `parameters` is EAV: filter to rows where `param_key = 'utm_campaign'`
		// then group by (param_value, ru.uri). Some parameter rows lack a
		// `view_id` (session-level params), so resolve the landing URI via the
		// view that recorded the campaign. Falls back to the URI directly
		// referenced by the parameter row.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT p.param_value AS campaign,
					ru.uri AS landing,
					COUNT(DISTINCT s.ID) AS sessions
				FROM %i p
				JOIN %i s ON s.ID = p.session_id
				LEFT JOIN %i v ON v.ID = p.view_id
				JOIN %i ru ON ru.ID = COALESCE(v.resource_uri_id, p.resource_uri_id)
				WHERE p.param_key = "utm_campaign"
					AND p.param_value IS NOT NULL
					AND p.param_value != ""
					AND s.started_at BETWEEN %s AND %s
				GROUP BY p.param_value, ru.uri
				ORDER BY sessions DESC
				LIMIT 10',
				$parameters,
				$sessions,
				$views,
				$uris,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'campaign' => (string) $r['campaign'],
						'landing'  => (string) $r['landing'],
						'sessions' => (int) $r['sessions'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_utm_combo( string $from, string $to, array $q ): array {
		global $wpdb;
		$parameters = TableRegistry::get( 'parameters' );
		$sessions   = TableRegistry::get( 'sessions' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					session_utm.source,
					session_utm.medium,
					session_utm.campaign,
					COUNT(DISTINCT session_utm.session_id) AS sessions
				FROM (
					SELECT
						s.ID AS session_id,
						MAX(CASE WHEN p.param_key = 'utm_source'   THEN p.param_value END) AS source,
						MAX(CASE WHEN p.param_key = 'utm_medium'   THEN p.param_value END) AS medium,
						MAX(CASE WHEN p.param_key = 'utm_campaign' THEN p.param_value END) AS campaign
					FROM %i p
					JOIN %i s ON s.ID = p.session_id
					WHERE p.param_key IN ('utm_source', 'utm_medium', 'utm_campaign')
						AND s.started_at BETWEEN %s AND %s
					GROUP BY s.ID
				) session_utm
				WHERE session_utm.source IS NOT NULL AND session_utm.source != ''
				GROUP BY session_utm.source, session_utm.medium, session_utm.campaign
				ORDER BY sessions DESC
				LIMIT 10",
				$parameters,
				$sessions,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'source'   => (string) $r['source'],
						'medium'   => (string) ( $r['medium'] ?? '' ),
						'campaign' => (string) ( $r['campaign'] ?? '' ),
						'sessions' => (int) $r['sessions'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	// =================================================================
	// Geography & Language handlers (cat 6) — Q73..Q75 Free
	// =================================================================

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_country( string $from, string $to, array $q ): array {
		$rows = $this->load_country_rows( $from, $to );
		return $this->ok( $q, [ 'rows' => array_slice( $rows, 0, 1 ) ], Questions::VIZ_KPI_TILE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_local_vs_international( string $from, string $to, array $q ): array {
		$rows      = $this->load_country_rows( $from, $to );
		$home_code = $this->infer_home_country_code();

		$home   = 0;
		$others = 0;
		foreach ( $rows as $row ) {
			if ( '' !== $home_code && strtoupper( (string) $row['code'] ) === $home_code ) {
				$home += (int) $row['visitors'];
			} else {
				$others += (int) $row['visitors'];
			}
		}

		// If the home country can't be inferred, treat the top row as home.
		if ( '' === $home_code && ! empty( $rows ) ) {
			$home   = (int) $rows[0]['visitors'];
			$others = max( 0, array_sum( array_column( $rows, 'visitors' ) ) - $home );
		}

		return $this->ok(
			$q,
			[
				'home'          => $home,
				'international' => $others,
			],
			Questions::VIZ_DONUT
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_languages( string $from, string $to, array $q ): array {
		global $wpdb;
		$sessions  = TableRegistry::get( 'sessions' );
		$languages = TableRegistry::get( 'languages' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COALESCE(l.code, "Unknown") AS code,
					COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i s
				LEFT JOIN %i l ON l.ID = s.language_id
				WHERE DATE(s.started_at) BETWEEN %s AND %s
				GROUP BY l.code
				ORDER BY visitors DESC
				LIMIT 10',
				$sessions,
				$languages,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->ok(
			$q,
			[
				'rows' => array_map(
					static fn( $r ) => [
						'code'     => (string) $r['code'],
						'visitors' => (int) $r['visitors'],
					],
					is_array( $rows ) ? $rows : []
				),
			],
			Questions::VIZ_TABLE
		);
	}

	// =================================================================
	// Devices & Browsers handlers (cat 7) — Q82..Q87 Free
	// =================================================================

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_device( string $from, string $to, array $q ): array {
		$rows = $this->load_device_rows( $from, $to );
		return $this->ok( $q, [ 'rows' => array_slice( $rows, 0, 1 ) ], Questions::VIZ_KPI_TILE );
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_tablet_share( string $from, string $to, array $q ): array {
		$rows   = $this->load_device_rows( $from, $to );
		$total  = array_sum( array_column( $rows, 'sessions' ) );
		$tablet = 0;
		foreach ( $rows as $row ) {
			if ( 0 === strcasecmp( (string) $row['device'], 'Tablet' ) ) {
				$tablet = (int) $row['sessions'];
				break;
			}
		}
		$share = $total > 0 ? round( ( $tablet / $total ) * 100, 1 ) : 0.0;
		return $this->ok(
			$q,
			[
				'sessions'  => $tablet,
				'share_pct' => $share,
			],
			Questions::VIZ_KPI_TILE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_browsers( string $from, string $to, array $q ): array {
		return $this->ok(
			$q,
			[ 'rows' => $this->load_top_dim_rows( $from, $to, 'device_browsers', 'device_browser_id', 'name' ) ],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_top_oss( string $from, string $to, array $q ): array {
		return $this->ok(
			$q,
			[ 'rows' => $this->load_top_dim_rows( $from, $to, 'device_oss', 'device_os_id', 'name' ) ],
			Questions::VIZ_TABLE
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_mobile_vs_desktop( string $from, string $to, array $q ): array {
		$rows    = $this->load_device_rows( $from, $to );
		$mobile  = 0;
		$desktop = 0;
		foreach ( $rows as $row ) {
			$name = strtolower( (string) $row['device'] );
			if ( 'mobile' === $name ) {
				$mobile = (int) $row['sessions'];
			} elseif ( 'desktop' === $name ) {
				$desktop = (int) $row['sessions'];
			}
		}
		$delta_pct = ( $desktop > 0 ) ? ( ( $mobile - $desktop ) / $desktop ) * 100 : 0.0;
		return $this->ok(
			$q,
			[
				'current'   => $mobile,
				'previous'  => $desktop,
				'delta_pct' => round( $delta_pct, 1 ),
			],
			Questions::VIZ_DELTA
		);
	}

	/**
	 * Resolve an Ask me! question.
	 *
	 * @param string               $from Date range start.
	 * @param string               $to   Date range end.
	 * @param array<string, mixed> $q    Inventory row.
	 * @return array<string, mixed>
	 */
	private function answer_mobile_priority( string $from, string $to, array $q ): array {
		$rows   = $this->load_device_rows( $from, $to );
		$total  = array_sum( array_column( $rows, 'sessions' ) );
		$mobile = 0;
		foreach ( $rows as $row ) {
			if ( 0 === strcasecmp( (string) $row['device'], 'Mobile' ) ) {
				$mobile = (int) $row['sessions'];
				break;
			}
		}
		$share = $total > 0 ? round( ( $mobile / $total ) * 100, 1 ) : 0.0;
		return $this->ok(
			$q,
			[
				'sessions'  => $mobile,
				'share_pct' => $share,
			],
			Questions::VIZ_KPI_TILE
		);
	}

	// =================================================================
	// Shared aggregation helpers
	// =================================================================

	/**
	 * Count distinct visitors active within the last `$window_seconds`.
	 *
	 * @param int $window_seconds Lookback window in seconds (e.g. 300 for 5 min).
	 * @return int
	 */
	private function count_active_visitors( int $window_seconds ): int {
		global $wpdb;
		$sessions = TableRegistry::get( 'sessions' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT visitor_id)
				FROM %i
				WHERE started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)',
				$sessions,
				$window_seconds
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Top pages by views over [from, to] from `summary` joined to `resource_uris`.
	 * Mirrors PagesController's real-time top-up: when `to` reaches today,
	 * today's raw views are merged on top of the aggregated history so the
	 * answer matches the Pages report byte-for-byte.
	 *
	 * @param string      $from   Date range start (`Y-m-d`).
	 * @param string      $to     Date range end (`Y-m-d`).
	 * @param string|null $prefix Optional URI prefix to filter (e.g. `/blog/`).
	 * @return array<int, array{uri:string,views:int}>
	 */
	private function load_top_pages( string $from, string $to, ?string $prefix ): array {
		global $wpdb;
		$summary    = TableRegistry::get( 'summary' );
		$uris       = TableRegistry::get( 'resource_uris' );
		$today      = gmdate( 'Y-m-d' );
		$has_prefix = ( null !== $prefix && '' !== $prefix );
		$like       = $has_prefix ? ( $wpdb->esc_like( (string) $prefix ) . '%' ) : null;
		$summary_to = ( $to >= $today ) ? gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) ) : $to;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$aggregated = [];
		if ( $from <= $summary_to ) {
			if ( $has_prefix ) {
				$aggregated = (array) $wpdb->get_results(
					$wpdb->prepare(
						'SELECT ru.uri AS uri, COALESCE(SUM(s.views), 0) AS views
						FROM %i s
						JOIN %i ru ON ru.ID = s.resource_uri_id
						WHERE s.date BETWEEN %s AND %s AND ru.uri LIKE %s
						GROUP BY ru.uri',
						$summary,
						$uris,
						$from,
						$summary_to,
						$like
					),
					ARRAY_A
				);
			} else {
				$aggregated = (array) $wpdb->get_results(
					$wpdb->prepare(
						'SELECT ru.uri AS uri, COALESCE(SUM(s.views), 0) AS views
						FROM %i s
						JOIN %i ru ON ru.ID = s.resource_uri_id
						WHERE s.date BETWEEN %s AND %s
						GROUP BY ru.uri',
						$summary,
						$uris,
						$from,
						$summary_to
					),
					ARRAY_A
				);
			}
		}

		$today_rows = [];
		if ( $from <= $today && $to >= $today ) {
			$views_table = TableRegistry::get( 'views' );
			if ( $has_prefix ) {
				$today_rows = (array) $wpdb->get_results(
					$wpdb->prepare(
						'SELECT ru.uri AS uri, COUNT(v.ID) AS views
						FROM %i v
						JOIN %i ru ON ru.ID = v.resource_uri_id
						WHERE DATE(v.viewed_at) = %s AND ru.uri LIKE %s
						GROUP BY ru.uri',
						$views_table,
						$uris,
						$today,
						$like
					),
					ARRAY_A
				);
			} else {
				$today_rows = (array) $wpdb->get_results(
					$wpdb->prepare(
						'SELECT ru.uri AS uri, COUNT(v.ID) AS views
						FROM %i v
						JOIN %i ru ON ru.ID = v.resource_uri_id
						WHERE DATE(v.viewed_at) = %s
						GROUP BY ru.uri',
						$views_table,
						$uris,
						$today
					),
					ARRAY_A
				);
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$totals = [];
		foreach ( $aggregated as $row ) {
			$uri            = (string) ( $row['uri'] ?? '' );
			$totals[ $uri ] = ( $totals[ $uri ] ?? 0 ) + (int) ( $row['views'] ?? 0 );
		}
		foreach ( $today_rows as $row ) {
			$uri            = (string) ( $row['uri'] ?? '' );
			$totals[ $uri ] = ( $totals[ $uri ] ?? 0 ) + (int) ( $row['views'] ?? 0 );
		}
		arsort( $totals, SORT_NUMERIC );
		$totals = array_slice( $totals, 0, 10, true );

		$out = [];
		foreach ( $totals as $uri => $views ) {
			$out[] = [
				'uri'   => (string) $uri,
				'views' => (int) $views,
			];
		}
		return $out;
	}

	/**
	 * Total views for an exact URI over [from, to]. Adds today's real-time
	 * portion from raw `views` when the range includes today, mirroring
	 * PagesController so the advisor and the Pages report stay aligned.
	 *
	 * @param string $from Date range start.
	 * @param string $to   Date range end.
	 * @param string $uri  Exact URI (e.g. `/`).
	 */
	private function views_for_exact_uri( string $from, string $to, string $uri ): int {
		global $wpdb;
		$summary    = TableRegistry::get( 'summary' );
		$uris       = TableRegistry::get( 'resource_uris' );
		$today      = gmdate( 'Y-m-d' );
		$summary_to = ( $to >= $today ) ? gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) ) : $to;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$aggregated = 0;
		if ( $from <= $summary_to ) {
			$aggregated = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COALESCE(SUM(s.views), 0)
					FROM %i s
					JOIN %i ru ON ru.ID = s.resource_uri_id
					WHERE s.date BETWEEN %s AND %s AND ru.uri = %s',
					$summary,
					$uris,
					$from,
					$summary_to,
					$uri
				)
			);
		}

		$today_extra = 0;
		if ( $from <= $today && $to >= $today ) {
			$views_table = TableRegistry::get( 'views' );
			$today_extra = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(v.ID)
					FROM %i v
					JOIN %i ru ON ru.ID = v.resource_uri_id
					WHERE DATE(v.viewed_at) = %s AND ru.uri = %s',
					$views_table,
					$uris,
					$today,
					$uri
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $aggregated + $today_extra;
	}

	/**
	 * Total views for URIs starting with `$pattern`. Adds today's real-time
	 * portion from raw `views` when the range includes today so the answer
	 * stays consistent with PagesController.
	 *
	 * @param string      $pattern URI prefix.
	 * @param string|null $from    Optional date range start; defaults to 30 days ago.
	 * @param string|null $to      Optional date range end; defaults to today.
	 */
	private function views_for_uri_pattern( string $pattern, ?string $from = null, ?string $to = null ): int {
		global $wpdb;
		$today      = gmdate( 'Y-m-d' );
		$from       = $from ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$to         = $to ?? $today;
		$summary    = TableRegistry::get( 'summary' );
		$uris       = TableRegistry::get( 'resource_uris' );
		$like       = $wpdb->esc_like( $pattern ) . '%';
		$summary_to = ( $to >= $today ) ? gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) ) : $to;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$aggregated = 0;
		if ( $from <= $summary_to ) {
			$aggregated = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COALESCE(SUM(s.views), 0)
					FROM %i s
					JOIN %i ru ON ru.ID = s.resource_uri_id
					WHERE s.date BETWEEN %s AND %s AND ru.uri LIKE %s',
					$summary,
					$uris,
					$from,
					$summary_to,
					$like
				)
			);
		}

		$today_extra = 0;
		if ( $from <= $today && $to >= $today ) {
			$views_table = TableRegistry::get( 'views' );
			$today_extra = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(v.ID)
					FROM %i v
					JOIN %i ru ON ru.ID = v.resource_uri_id
					WHERE DATE(v.viewed_at) = %s AND ru.uri LIKE %s',
					$views_table,
					$uris,
					$today,
					$like
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $aggregated + $today_extra;
	}

	/**
	 * Referrer-channel grouping shared by q41 / q42 / q53 / q54 / q55.
	 *
	 * @param string $from Date range start.
	 * @param string $to   Date range end.
	 * @return array<int, array{channel:string,sessions:int,visitors:int}>
	 */
	private function load_referrer_channels( string $from, string $to ): array {
		global $wpdb;
		$sessions  = TableRegistry::get( 'sessions' );
		$referrers = TableRegistry::get( 'referrers' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COALESCE(r.channel, "Direct") AS channel,
					COUNT(DISTINCT s.ID) AS sessions,
					COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i s
				LEFT JOIN %i r ON r.ID = s.referrer_id
				WHERE s.started_at BETWEEN %s AND %s
				GROUP BY channel
				ORDER BY sessions DESC',
				$sessions,
				$referrers,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( $r ) => [
				'channel'  => (string) $r['channel'],
				'sessions' => (int) $r['sessions'],
				'visitors' => (int) $r['visitors'],
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Count distinct visitors arriving from a channel (optionally a substring
	 * match against the referrer name, e.g. `google` inside Organic Search).
	 *
	 * @param string      $from         Date range start.
	 * @param string      $to           Date range end.
	 * @param string      $channel      Channel code (e.g. `Organic Search`).
	 * @param string|null $name_pattern Optional substring to match on `referrers.name`.
	 */
	private function count_referrer_visitors( string $from, string $to, string $channel, ?string $name_pattern = null ): int {
		global $wpdb;
		$sessions  = TableRegistry::get( 'sessions' );
		$referrers = TableRegistry::get( 'referrers' );

		$start = $from . ' 00:00:00';
		$end   = $to . ' 23:59:59';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( null !== $name_pattern ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT s.visitor_id)
					FROM %i s
					JOIN %i r ON r.ID = s.referrer_id
					WHERE r.channel = %s
						AND LOWER(r.name) LIKE %s
						AND s.started_at BETWEEN %s AND %s',
					$sessions,
					$referrers,
					$channel,
					'%' . $wpdb->esc_like( strtolower( $name_pattern ) ) . '%',
					$start,
					$end
				)
			);
		}

		// "Direct" maps to NULL referrer_id rather than a row in referrers.
		if ( 'Direct' === $channel ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT s.visitor_id)
					FROM %i s
					WHERE s.referrer_id IS NULL
						AND s.started_at BETWEEN %s AND %s',
					$sessions,
					$start,
					$end
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT s.visitor_id)
				FROM %i s
				JOIN %i r ON r.ID = s.referrer_id
				WHERE r.channel = %s
					AND s.started_at BETWEEN %s AND %s',
				$sessions,
				$referrers,
				$channel,
				$start,
				$end
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Count distinct visitors whose `parameters.utm_{column}` value matches
	 * any of `$values` (case-insensitive).
	 *
	 * @param string             $from   Date range start.
	 * @param string             $to     Date range end.
	 * @param string             $column One of `source` / `medium` / `campaign`.
	 * @param array<int, string> $values Lowercase exact matches to OR together.
	 */
	private function count_utm_visitors( string $from, string $to, string $column, array $values ): int {
		$column = in_array( $column, [ 'source', 'medium', 'campaign' ], true ) ? $column : 'source';
		if ( empty( $values ) ) {
			return 0;
		}

		global $wpdb;
		$parameters = TableRegistry::get( 'parameters' );
		$sessions   = TableRegistry::get( 'sessions' );

		// `parameters` is an EAV table (`param_key`/`param_value`), so match the
		// row where `param_key = 'utm_<column>'` rather than referencing a
		// non-existent dedicated column.
		//
		// To satisfy WP Plugin Check (PCP §18), we pad `$values` to a fixed
		// width and emit a fixed-shape SQL with a fixed placeholder count.
		// `__never_matches_xyzzy__` is a sentinel that no real UTM value
		// equals, so the padding rows contribute zero matches without
		// changing the result.
		$param_key = 'utm_' . $column;
		$padded    = array_pad(
			array_map( static fn( $v ) => strtolower( (string) $v ), array_slice( $values, 0, 8 ) ),
			8,
			'__never_matches_xyzzy__'
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT s.visitor_id)
				FROM %i p
				JOIN %i s ON s.ID = p.session_id
				WHERE p.param_key = %s
					AND LOWER(p.param_value) IN (%s, %s, %s, %s, %s, %s, %s, %s)
					AND s.started_at BETWEEN %s AND %s',
				$parameters,
				$sessions,
				$param_key,
				$padded[0],
				$padded[1],
				$padded[2],
				$padded[3],
				$padded[4],
				$padded[5],
				$padded[6],
				$padded[7],
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Infer the site's home ISO-3166 alpha-2 country code from the WordPress
	 * locale.  Reads `get_locale()` (the modern API; `WPLANG` was removed in
	 * WP 4.0) and extracts the region subtag.  Returns an empty string when no
	 * reliable country can be derived — e.g. `en` (no region), `mul`, or an
	 * unfilterable locale — so the caller can fall back to its top-row
	 * heuristic.
	 *
	 * @return string Uppercase 2-letter country code, or '' when unknown.
	 */
	private function infer_home_country_code(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		if ( '' === $locale || false === strpos( $locale, '_' ) ) {
			return '';
		}
		$region = substr( $locale, strpos( $locale, '_' ) + 1 );
		// Strip script/variant suffixes (e.g. `zh_CN_Hans` -> `CN`).
		$region = strtok( $region, '_' );
		if ( ! is_string( $region ) || 2 !== strlen( $region ) ) {
			return '';
		}
		return strtoupper( $region );
	}

	/**
	 * Country grouping shared by q72 / q73 / q74.
	 *
	 * @param string $from Date range start.
	 * @param string $to   Date range end.
	 * @return array<int, array{code:string,name:string,visitors:int,sessions:int}>
	 */
	private function load_country_rows( string $from, string $to ): array {
		global $wpdb;
		$sessions  = TableRegistry::get( 'sessions' );
		$countries = TableRegistry::get( 'countries' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.code AS code,
					COALESCE(c.name, "Unknown") AS name,
					COUNT(DISTINCT s.visitor_id) AS visitors,
					COUNT(DISTINCT s.ID) AS sessions
				FROM %i s
				LEFT JOIN %i c ON c.ID = s.country_id
				WHERE DATE(s.started_at) BETWEEN %s AND %s
				GROUP BY c.code, c.name
				ORDER BY visitors DESC
				LIMIT 25',
				$sessions,
				$countries,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( $r ) => [
				'code'     => (string) ( $r['code'] ?? '' ),
				'name'     => (string) $r['name'],
				'visitors' => (int) $r['visitors'],
				'sessions' => (int) $r['sessions'],
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Device grouping shared by q81..q87.
	 *
	 * @param string $from Date range start.
	 * @param string $to   Date range end.
	 * @return array<int, array{device:string,sessions:int,visitors:int}>
	 */
	private function load_device_rows( string $from, string $to ): array {
		global $wpdb;
		$sessions = TableRegistry::get( 'sessions' );
		$devices  = TableRegistry::get( 'device_types' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COALESCE(dt.name, "Unknown") AS device,
					COUNT(DISTINCT s.ID) AS sessions,
					COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i s
				LEFT JOIN %i dt ON dt.ID = s.device_type_id
				WHERE DATE(s.started_at) BETWEEN %s AND %s
				GROUP BY device
				ORDER BY sessions DESC',
				$sessions,
				$devices,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( $r ) => [
				'device'   => (string) $r['device'],
				'sessions' => (int) $r['sessions'],
				'visitors' => (int) $r['visitors'],
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Generic top-N for a one-step dimension table (browser / OS / language /
	 * resolution / city) joined to `sessions` via a single FK column.
	 *
	 * `$fk` and `$name_col` are interpolated into SQL (no `%i` placeholder
	 * exists for column names in `$wpdb->prepare()`), so both MUST come from
	 * the hardcoded allowlist below — never from user input or filterable
	 * surfaces. The allowlist defends against future PRs forgetting that
	 * constraint.
	 *
	 * @param string $from       Date range start.
	 * @param string $to         Date range end.
	 * @param string $table_key  TableRegistry key (e.g. `device_browsers`).
	 * @param string $fk         Column on `sessions` that holds the dim ID (e.g. `device_browser_id`).
	 * @param string $name_col   Column on the dim table to surface as the row label.
	 * @return array<int, array{name:string,sessions:int,visitors:int}>
	 */
	private function load_top_dim_rows( string $from, string $to, string $table_key, string $fk, string $name_col ): array {
		// Allowlist of (fk, name_col) pairs. Any caller outside this set
		// would be rejected here. The columns are also passed to
		// `$wpdb->prepare()` via `%i` identifier placeholders so PCP
		// reads the SQL as fully prepared.
		$allowed = [
			'device_browser_id' => [ 'name' ],
			'device_os_id'      => [ 'name' ],
			'language_id'       => [ 'code', 'name' ],
		];
		if ( ! isset( $allowed[ $fk ] ) || ! in_array( $name_col, $allowed[ $fk ], true ) ) {
			return [];
		}

		global $wpdb;
		$sessions  = TableRegistry::get( 'sessions' );
		$dim_table = TableRegistry::get( $table_key );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(d.%i, 'Unknown') AS name,
					COUNT(DISTINCT s.ID) AS sessions,
					COUNT(DISTINCT s.visitor_id) AS visitors
				FROM %i s
				LEFT JOIN %i d ON d.ID = s.%i
				WHERE DATE(s.started_at) BETWEEN %s AND %s
				GROUP BY name
				ORDER BY sessions DESC
				LIMIT 10",
				$name_col,
				$sessions,
				$dim_table,
				$fk,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( $r ) => [
				'name'     => (string) $r['name'],
				'sessions' => (int) $r['sessions'],
				'visitors' => (int) $r['visitors'],
			],
			is_array( $rows ) ? $rows : []
		);
	}
}
