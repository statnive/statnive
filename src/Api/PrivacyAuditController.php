<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Privacy\ComplianceAuditor;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for privacy compliance audit.
 *
 * Endpoint: GET /wp-json/statnive/v1/privacy-audit
 * Returns compliance checks and overall score.
 */
final class PrivacyAuditController extends WP_REST_Controller {

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
	protected $rest_base = 'privacy-audit';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_audit' ],
					'permission_callback' => [ $this, 'get_audit_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Permission check — requires manage_options.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function get_audit_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get privacy audit results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_audit( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'checks' => ComplianceAuditor::audit(),
				'score'  => ComplianceAuditor::score(),
			],
			200
		);
	}
}
