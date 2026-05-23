<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Api\Concerns\CachesResponses;
use Statnive\Api\Concerns\ValidatesDateRange;
use Statnive\Capability;
use Statnive\Integration\WooCommerce\BackfillService;
use Statnive\Integration\WooCommerce\Currency;
use Statnive\Integration\WooCommerce\Detector;
use Statnive\Integration\WooCommerce\ReportQueryService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API for the Revenue Report SPA page.
 *
 * Ten read-only endpoints under `/statnive/v1/revenue/*`. Each one
 * returns a shared envelope `{ data, meta }` so the SPA can rely on
 * one shape for currency / timezone / request_id / period context.
 *
 * Capability gate uses the synthetic `statnive_view_reports` from PR 1
 * so admins on non-WooCommerce sites can still hit `/wc-status` and
 * see the "WooCommerce not installed" state.
 *
 * Queries delegate to {@see ReportQueryService} — this controller never
 * touches SQL directly.
 */
final class RevenueController extends WP_REST_Controller {

	use ValidatesDateRange;
	use CachesResponses;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'statnive/v1';

	/**
	 * REST base prefix for these routes.
	 */
	private const BASE = 'revenue';

	/**
	 * Register the 10 routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . self::BASE . '/wc-status',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_wc_status' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . self::BASE . '/backfill',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'trigger_backfill' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
			]
		);

		$range_args = [
			'from' => [
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => [ $this, 'validate_date' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'to'   => [
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => [ $this, 'validate_date' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
		];

		$range_limit_args = $range_args + [
			'limit' => [
				'required'          => false,
				'type'              => 'integer',
				'default'           => 25,
				'minimum'           => 1,
				'maximum'           => 200,
				'sanitize_callback' => 'absint',
			],
		];

		$limit10_args = $range_args + [
			'limit' => [
				'required'          => false,
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			],
		];

		$plain = [
			'summary'    => 'get_summary',
			'timeseries' => 'get_timeseries',
			'by-channel' => 'get_by_channel',
			'funnel'     => 'get_funnel',
			'refunds'    => 'get_refunds',
		];
		foreach ( $plain as $route => $method ) {
			register_rest_route(
				$this->namespace,
				'/' . self::BASE . '/' . $route,
				[
					[
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, $method ],
						'permission_callback' => [ $this, 'permission_check' ],
						'args'                => $range_args,
					],
				]
			);
		}

		$limited = [
			'by-utm'     => 'get_by_utm',
			'by-landing' => 'get_by_landing',
			'coupons'    => 'get_coupons',
		];
		foreach ( $limited as $route => $method ) {
			register_rest_route(
				$this->namespace,
				'/' . self::BASE . '/' . $route,
				[
					[
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, $method ],
						'permission_callback' => [ $this, 'permission_check' ],
						'args'                => $range_limit_args,
					],
				]
			);
		}

		register_rest_route(
			$this->namespace,
			'/' . self::BASE . '/products',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_products' ],
					'permission_callback' => [ $this, 'permission_check' ],
					'args'                => $limit10_args,
				],
			]
		);
	}

	/**
	 * Permission check shared by every endpoint.
	 */
	public function permission_check(): bool {
		return Capability::can_view_reports();
	}

	/**
	 * GET /statnive/v1/revenue/wc-status — read-only health snapshot.
	 *
	 * Does not require WooCommerce; returns honest status either way.
	 */
	public function get_wc_status(): WP_REST_Response {
		$status   = Detector::status();
		$failures = (int) get_option( 'statnive_failed_requests', 0 );
		return $this->envelope(
			[
				'woocommerce_active'  => $status['active'],
				'woocommerce_version' => $status['version'],
				'hpos_enabled'        => $status['hpos'],
				'attribution_enabled' => $status['attribution'],
				'min_wc_required'     => $status['min_required'],
				'recorder_failures'   => $failures,
				'backfill'            => BackfillService::status_payload(),
			],
			null,
			null
		);
	}

	/**
	 * POST /statnive/v1/revenue/backfill — manual re-trigger.
	 *
	 * The job auto-starts on first admin pageview when a gap is detected,
	 * so this endpoint exists mostly for `failed` recovery and power-user
	 * use. Idempotent: if a job is already pending/running, returns 409
	 * with the current state.
	 */
	public function trigger_backfill(): WP_REST_Response {
		$result  = BackfillService::start();
		$payload = [
			'ok'    => (bool) $result['ok'],
			'state' => $result['state'],
		];
		if ( isset( $result['reason'] ) ) {
			$payload['reason'] = $result['reason'];
		}
		$response = $this->envelope( $payload, null, null );
		$response->set_status( (int) $result['http_status'] );
		return $response;
	}

	/**
	 * GET /statnive/v1/revenue/summary
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`.
	 */
	public function get_summary( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to ] = $this->extract_range( $request );
		$cached        = $this->get_cached_response( 'revenue_summary', compact( 'from', 'to' ) );
		if ( null !== $cached ) {
			return $this->envelope( $cached, $from, $to );
		}
		$data = ReportQueryService::summary( $from, $to );
		$this->set_cached_response( 'revenue_summary', compact( 'from', 'to' ), $data, $from, $to );
		return $this->envelope( $data, $from, $to );
	}

	/**
	 * GET /statnive/v1/revenue/timeseries
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`.
	 */
	public function get_timeseries( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to ] = $this->extract_range( $request );
		return $this->cached_envelope(
			'revenue_timeseries',
			[
				'from' => $from,
				'to'   => $to,
			],
			static fn(): array => ReportQueryService::timeseries( $from, $to ),
			$from,
			$to
		);
	}

	/**
	 * GET /statnive/v1/revenue/by-channel
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`.
	 */
	public function get_by_channel( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to ] = $this->extract_range( $request );
		return $this->cached_envelope(
			'revenue_by_channel',
			[
				'from' => $from,
				'to'   => $to,
			],
			static fn(): array => ReportQueryService::by_channel( $from, $to ),
			$from,
			$to
		);
	}

	/**
	 * GET /statnive/v1/revenue/by-utm
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`, `limit`.
	 */
	public function get_by_utm( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to, $limit ] = $this->extract_range_limit( $request );
		return $this->cached_envelope( 'revenue_by_utm', compact( 'from', 'to', 'limit' ), static fn(): array => ReportQueryService::by_utm( $from, $to, $limit ), $from, $to );
	}

	/**
	 * GET /statnive/v1/revenue/by-landing
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`, `limit`.
	 */
	public function get_by_landing( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to, $limit ] = $this->extract_range_limit( $request );
		return $this->cached_envelope( 'revenue_by_landing', compact( 'from', 'to', 'limit' ), static fn(): array => ReportQueryService::by_landing( $from, $to, $limit ), $from, $to );
	}

	/**
	 * GET /statnive/v1/revenue/products
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`, `limit`.
	 */
	public function get_products( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to, $limit ] = $this->extract_range_limit( $request );
		return $this->cached_envelope( 'revenue_products', compact( 'from', 'to', 'limit' ), static fn(): array => ReportQueryService::top_products( $from, $to, $limit ), $from, $to );
	}

	/**
	 * GET /statnive/v1/revenue/funnel
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`.
	 */
	public function get_funnel( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to ] = $this->extract_range( $request );
		return $this->cached_envelope(
			'revenue_funnel',
			[
				'from' => $from,
				'to'   => $to,
			],
			static fn(): array => ReportQueryService::funnel( $from, $to ),
			$from,
			$to
		);
	}

	/**
	 * GET /statnive/v1/revenue/coupons
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`, `limit`.
	 */
	public function get_coupons( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to, $limit ] = $this->extract_range_limit( $request );
		return $this->cached_envelope( 'revenue_coupons', compact( 'from', 'to', 'limit' ), static fn(): array => ReportQueryService::coupons( $from, $to, $limit ), $from, $to );
	}

	/**
	 * GET /statnive/v1/revenue/refunds
	 *
	 * @param WP_REST_Request $request REST request with `from`, `to`.
	 */
	public function get_refunds( WP_REST_Request $request ): WP_REST_Response {
		[ $from, $to ] = $this->extract_range( $request );
		return $this->cached_envelope(
			'revenue_refunds',
			[
				'from' => $from,
				'to'   => $to,
			],
			static fn(): array => ReportQueryService::refunds( $from, $to ),
			$from,
			$to
		);
	}

	/**
	 * Pull `from` + `to` from a validated request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array{0:string,1:string}
	 */
	private function extract_range( WP_REST_Request $request ): array {
		return [ (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ) ];
	}

	/**
	 * Pull `from` + `to` + `limit` from a validated request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array{0:string,1:string,2:int}
	 */
	private function extract_range_limit( WP_REST_Request $request ): array {
		return [
			(string) $request->get_param( 'from' ),
			(string) $request->get_param( 'to' ),
			(int) $request->get_param( 'limit' ),
		];
	}

	/**
	 * Wrap cached/uncached fetch behind one helper to keep callbacks small.
	 *
	 * @param string               $endpoint Cache namespace.
	 * @param array<string, mixed> $params   Cache key inputs.
	 * @param callable             $fetch    Closure returning the payload.
	 * @param string               $from     Period start.
	 * @param string               $to       Period end.
	 */
	private function cached_envelope( string $endpoint, array $params, callable $fetch, string $from, string $to ): WP_REST_Response {
		$cached = $this->get_cached_response( $endpoint, $params );
		if ( null !== $cached ) {
			return $this->envelope( $cached, $from, $to );
		}
		$data = $fetch();
		$this->set_cached_response( $endpoint, $params, $data, $from, $to );
		return $this->envelope( $data, $from, $to );
	}

	/**
	 * Build the shared response envelope `{ data, meta }`.
	 *
	 * @param mixed       $data Endpoint payload.
	 * @param string|null $from Period start (null for endpoints with no range).
	 * @param string|null $to   Period end (null for endpoints with no range).
	 */
	private function envelope( $data, ?string $from, ?string $to ): WP_REST_Response {
		$meta = [
			'request_id'          => self::request_id(),
			'currency'            => Currency::code(),
			'currency_minor_unit' => Currency::decimals(),
			'currency_symbol'     => Currency::symbol(),
			'timezone'            => wp_timezone_string(),
			'generated_at'        => gmdate( 'c' ),
		];
		if ( null !== $from && null !== $to ) {
			$meta['period'] = [
				'start' => $from,
				'end'   => $to,
			];
		}
		return new WP_REST_Response(
			[
				'data' => $data,
				'meta' => $meta,
			],
			200
		);
	}

	/**
	 * Generate a request ID for tracing.
	 */
	private static function request_id(): string {
		return 'stv_' . substr( wp_hash( wp_generate_uuid4() ), 0, 12 );
	}
}
