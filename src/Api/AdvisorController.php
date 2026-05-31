<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Advisor\Categories;
use Statnive\Advisor\Questions;
use Statnive\Advisor\QuestionResolver;
use Statnive\Advisor\UserPreferences;
use Statnive\Capability;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for the Ask me! Advisor page.
 *
 * Routes (namespace `statnive/v1`):
 *   GET  /advisor/questions     — full 120-question inventory + categories.
 *   POST /advisor/answers       — batch answer resolution: { question_ids, from, to }.
 *   GET  /advisor/preferences   — current user's pinned IDs (defaults if unset).
 *   PUT  /advisor/preferences   — write pinned IDs (validated + max-20 cap).
 *
 * Every route is gated on `Capability::can_view_reports()` — same as the
 * rest of the Statnive dashboard surface.
 */
final class AdvisorController extends WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'statnive/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'advisor';

	/**
	 * Register all advisor routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/questions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_questions' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/answers',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'post_answers' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => self::get_answers_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/preferences',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_preferences' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'put_preferences' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => self::get_preferences_args(),
				],
			]
		);
	}

	/**
	 * Permission check — shared across all advisor routes.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function permissions_check( $request ): bool {
		return Capability::can_view_reports();
	}

	// =================================================================
	// GET /advisor/questions
	// =================================================================

	/**
	 * Return the full 120-question inventory + 10 category labels.
	 *
	 * The response is locale-keyed (translated `question` + `category` fields)
	 * and includes the bilingual `searchable[]` array per plan §G.3 so the
	 * client search box can match against translated + English text.
	 *
	 * Cached via the 1-hour inventory transient (locale-keyed so locale
	 * switches always fall through to a fresh build).
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function get_questions( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		// REST resolves translations via `determine_locale()` (which honours
		// per-user `locale` profile meta), so two users on the same site can
		// receive different translations. Keying the transient on
		// `get_locale()` would let user A's translated inventory leak to
		// user B. Use `determine_locale()` to match what `__()` will return
		// for this request.
		$locale    = function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : 'en_US' );
		$cache_key = 'statnive_advisor_inv_v' . (int) get_option( 'statnive_cache_version', 0 ) . '_' . md5( (string) $locale );

		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$payload = [
			'categories' => Categories::all(),
			'questions'  => Questions::with_searchable(),
		];

		set_transient( $cache_key, $payload, HOUR_IN_SECONDS );

		return new WP_REST_Response( $payload, 200 );
	}

	// =================================================================
	// POST /advisor/answers
	// =================================================================

	/**
	 * Schema for the POST /advisor/answers body.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_answers_args(): array {
		return [
			'question_ids' => [
				'type'              => 'array',
				'required'          => true,
				'items'             => [ 'type' => 'string' ],
				'validate_callback' => static function ( $value ): bool {
					return is_array( $value ) && count( $value ) >= 1 && count( $value ) <= 25;
				},
			],
			'from'         => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static fn( $v ) => is_string( $v ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ),
			],
			'to'           => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static fn( $v ) => is_string( $v ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ),
			],
		];
	}

	/**
	 * Resolve a batch of question IDs and return an array of answers.
	 *
	 * The response carries a `Server-Timing` header with per-question
	 * latency + cache-hit markers (plan §G.15 observability).
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function post_answers( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( [ 'message' => 'Invalid body.' ], 400 );
		}

		$ids = array_values(
			array_filter(
				(array) ( $body['question_ids'] ?? [] ),
				static fn( $x ) => is_string( $x ) && '' !== $x
			)
		);

		if ( empty( $ids ) ) {
			return new WP_REST_Response( [ 'message' => 'question_ids is required.' ], 400 );
		}

		$from = isset( $body['from'] ) ? (string) $body['from'] : gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$to   = isset( $body['to'] ) ? (string) $body['to'] : gmdate( 'Y-m-d' );

		// Reject inverted ranges explicitly. `BETWEEN $from AND $to` would
		// silently return zero rows on the server (because MySQL's BETWEEN
		// is `$from <= col AND col <= $to`), so the client would render an
		// empty answer with no clue why. A 400 here surfaces the mistake.
		if ( $from > $to ) {
			return new WP_REST_Response(
				[
					'message' => 'from must be on or before to.',
					'code'    => 'invalid_date_range',
				],
				400
			);
		}

		$resolver = new QuestionResolver();
		$result   = $resolver->resolve_batch(
			$ids,
			[
				'from' => $from,
				'to'   => $to,
			]
		);

		$response = new WP_REST_Response(
			[
				'answers' => $result['answers'],
				'from'    => $from,
				'to'      => $to,
			],
			200
		);

		$response->header( 'Server-Timing', self::format_server_timing( $result['server_timing'] ) );

		return $response;
	}

	/**
	 * Format the per-question timing breakdown as a W3C `Server-Timing`
	 * header value: `metric;dur=42;desc="db", metric;dur=8;desc="cache", …`
	 *
	 * Per the W3C Server-Timing spec the metric name should be unique within
	 * the header. When the client sends the same question_id twice (or `total`
	 * collides with a hypothetical `qN`), we disambiguate by appending a `__n`
	 * suffix so DevTools renders each timing as a distinct row.
	 *
	 * @param array<int, array<string, mixed>> $entries Timing entries.
	 */
	private static function format_server_timing( array $entries ): string {
		$parts = [];
		$seen  = [];
		foreach ( $entries as $e ) {
			$id   = isset( $e['id'] ) ? (string) $e['id'] : 'q';
			$ms   = isset( $e['ms'] ) ? (float) $e['ms'] : 0.0;
			$desc = isset( $e['desc'] ) ? (string) $e['desc'] : '';

			$id_safe   = (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $id );
			$desc_safe = (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $desc );
			if ( '' === $id_safe ) {
				$id_safe = 'q';
			}

			$count = $seen[ $id_safe ] ?? 0;
			if ( $count > 0 ) {
				$emit_id = $id_safe . '__' . $count;
			} else {
				$emit_id = $id_safe;
			}
			$seen[ $id_safe ] = $count + 1;

			$entry = sprintf( '%s;dur=%.1f', $emit_id, $ms );
			if ( '' !== $desc_safe ) {
				$entry .= ';desc="' . $desc_safe . '"';
			}
			$parts[] = $entry;
		}
		return implode( ', ', $parts );
	}

	// =================================================================
	// GET / PUT /advisor/preferences
	// =================================================================

	/**
	 * Schema for the PUT /advisor/preferences body.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_preferences_args(): array {
		return [
			'pinned_questions' => [
				'type'     => 'array',
				'required' => true,
				'items'    => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Read the current user's pinned questions. Returns defaults if unset.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function get_preferences( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_REST_Response( [ 'message' => 'Not authenticated.' ], 401 );
		}

		return new WP_REST_Response(
			[
				'pinned_questions' => UserPreferences::get( $user_id ),
				'max_pins'         => UserPreferences::MAX_PINS,
				'defaults'         => UserPreferences::default_pinned(),
			],
			200
		);
	}

	/**
	 * Write the current user's pinned questions. Validates IDs against the
	 * known inventory and truncates to the max-pins cap.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function put_preferences( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_REST_Response( [ 'message' => 'Not authenticated.' ], 401 );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( [ 'message' => 'Invalid body.' ], 400 );
		}

		$ids = (array) ( $body['pinned_questions'] ?? [] );

		$stored = UserPreferences::set( $user_id, $ids );

		return new WP_REST_Response(
			[
				'pinned_questions' => $stored,
				'max_pins'         => UserPreferences::MAX_PINS,
			],
			200
		);
	}
}
