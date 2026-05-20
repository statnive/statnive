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

		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) || '' === $id ) {
				continue;
			}

			$q_start    = microtime( true );
			$source     = 'db';
			$resolution = $this->resolve_one( $id, $date_range, $source );
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
		$q = Questions::find( $id );

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

		$cache_params = [
			'q'      => $id,
			'from'   => $from,
			'to'     => $to,
			'locale' => function_exists( 'get_locale' ) ? get_locale() : 'en_US',
		];

		$cached = $this->get_cached_response( 'advisor_answer', $cache_params );
		if ( null !== $cached ) {
			$source = 'cache';
			return $cached;
		}

		try {
			$answer = $this->dispatch( $id, $from, $to, $q );
		} catch ( \Throwable $e ) {
			$source = 'placeholder';
			return $this->error( $id, 'handler_error', $e->getMessage() );
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

			// Pages & Content (cat 3) — top-pages family.
			case 'q23':
				return $this->answer_q23( $from, $to, $q );

			// Referrers & Channels (cat 4) — channel mix.
			case 'q41':
				return $this->answer_q41( $from, $to, $q );

			// Geography & Language (cat 6) — country mix.
			case 'q72':
				return $this->answer_q72( $from, $to, $q );

			// Devices & Browsers (cat 7) — device split.
			case 'q81':
				return $this->answer_q81( $from, $to, $q );

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
			'status'     => 'coming_soon',
			'reason'     => $reason,
			'value'      => null,
			'viz'        => 'coming_soon',
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
			'status'  => 'error',
			'code'    => $code,
			'message' => $message,
			'value'   => null,
			'viz'     => 'error',
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
			'status'     => 'ok',
			'value'      => $value,
			'viz'        => $viz_override ?? ( $q['viz_hint'] ?? 'kpi_tile' ),
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

		return $this->ok( $q, [ 'rows' => $normalized ], 'table' );
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
				WHERE DATE(s.started_at) BETWEEN %s AND %s
				GROUP BY channel
				ORDER BY sessions DESC',
				$sessions_table,
				$referrers_table,
				$from,
				$to
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

		return $this->ok( $q, [ 'rows' => $normalized ], 'donut' );
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

		return $this->ok( $q, [ 'rows' => $normalized ], 'map' );
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

		return $this->ok( $q, [ 'rows' => $normalized ], 'bar' );
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
			'kpi_tile'
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
			'kpi_tile'
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
			'kpi_tile'
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
			'delta'
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
			'delta'
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
			'table'
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
			'table'
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
			'table'
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
		return $this->ok( $q, $this->anomaly_summary( $from, $to, 'drop' ), 'delta' );
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
		return $this->ok( $q, $this->anomaly_summary( $from, $to, 'spike' ), 'delta' );
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
	 * Top/bottom days by visitor count over [from, to], limit 5.
	 *
	 * @param string $from      Date range start (`Y-m-d`).
	 * @param string $to        Date range end (`Y-m-d`).
	 * @param string $order_dir `ASC` for worst-first, `DESC` for best-first.
	 * @return array<int, array{date:string,visitors:int}>
	 */
	private function load_daily_visitors( string $from, string $to, string $order_dir ): array {
		global $wpdb;
		$totals_table = TableRegistry::get( 'summary_totals' );
		$dir          = ( 'ASC' === strtoupper( $order_dir ) ) ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date, COALESCE(visitors, 0) AS visitors
				FROM %i WHERE date BETWEEN %s AND %s
				ORDER BY visitors {$dir} LIMIT 5",
				$totals_table,
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map(
			static fn( $r ) => [
				'date'     => (string) $r['date'],
				'visitors' => (int) $r['visitors'],
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Daily series (date, visitors, sessions, views) for the trend chart.
	 *
	 * @param string $from Date range start (`Y-m-d`).
	 * @param string $to   Date range end (`Y-m-d`).
	 * @return array<int, array{date:string,visitors:int,sessions:int,views:int}>
	 */
	private function load_daily_series( string $from, string $to ): array {
		global $wpdb;
		$totals_table = TableRegistry::get( 'summary_totals' );

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
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( $r ) => [
				'date'     => (string) $r['date'],
				'visitors' => (int) $r['visitors'],
				'sessions' => (int) $r['sessions'],
				'views'    => (int) $r['views'],
			],
			is_array( $rows ) ? $rows : []
		);
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
}
