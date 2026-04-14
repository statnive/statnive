<?php

declare(strict_types=1);

namespace Statnive\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Import\CsvImporter;
use Statnive\Import\WPStatisticsImporter;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for data import operations.
 *
 * Handles CSV and WP Statistics imports with progress tracking.
 */
final class ImportController extends WP_REST_Controller {

	protected $namespace = 'statnive/v1';
	protected $rest_base = 'import';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// CSV import.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/csv/start',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'start_csv' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'file_path' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => [ $this, 'validate_file_path' ],
						],
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/csv/progress',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'csv_progress' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [],
				],
			]
		);

		// WP Statistics import.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/wp-statistics/start',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'start_wp_statistics' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/wp-statistics/progress',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'wp_statistics_progress' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [],
				],
			]
		);
	}

	/**
	 * Permission check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate that a file_path is within the uploads directory and is a .csv file.
	 *
	 * Prevents path traversal attacks by requiring the resolved real path
	 * to be within wp_upload_dir()['basedir'].
	 *
	 * @param string          $value   File path.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public function validate_file_path( $value, $request, $param ) {
		$path = sanitize_text_field( (string) $value );

		if ( empty( $path ) ) {
			return new \WP_Error( 'invalid_file_path', 'file_path is required.', [ 'status' => 400 ] );
		}

		// Must be a .csv file.
		if ( '.csv' !== strtolower( substr( $path, -4 ) ) ) {
			return new \WP_Error( 'invalid_file_type', 'Only .csv files are allowed.', [ 'status' => 400 ] );
		}

		// Resolve real path to prevent directory traversal.
		$real_path = realpath( $path );
		if ( false === $real_path ) {
			return new \WP_Error( 'file_not_found', 'File does not exist.', [ 'status' => 400 ] );
		}

		$upload_dir = wp_upload_dir();
		$base_dir   = realpath( $upload_dir['basedir'] );
		if ( false === $base_dir || ! str_starts_with( $real_path, $base_dir ) ) {
			return new \WP_Error( 'path_traversal', 'File must be within the uploads directory.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Start CSV import.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function start_csv( WP_REST_Request $request ): WP_REST_Response {
		$file_path = sanitize_text_field( $request->get_param( 'file_path' ) ?? '' );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return new WP_REST_Response( [ 'message' => 'File not found.' ], 400 );
		}

		$importer = new CsvImporter();
		$importer->start( [ 'file_path' => $file_path ] );

		return new WP_REST_Response( [ 'status' => 'started' ], 200 );
	}

	/**
	 * Get CSV import progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function csv_progress( WP_REST_Request $request ): WP_REST_Response {
		$importer = new CsvImporter();
		return new WP_REST_Response( $importer->get_progress(), 200 );
	}

	/**
	 * Start WP Statistics import.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function start_wp_statistics( WP_REST_Request $request ): WP_REST_Response {
		if ( ! WPStatisticsImporter::is_available() ) {
			return new WP_REST_Response( [ 'message' => 'WP Statistics tables not found.' ], 400 );
		}

		$importer = new WPStatisticsImporter();
		$importer->start( [] );

		return new WP_REST_Response( [ 'status' => 'started' ], 200 );
	}

	/**
	 * Get WP Statistics import progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function wp_statistics_progress( WP_REST_Request $request ): WP_REST_Response {
		$importer = new WPStatisticsImporter();
		return new WP_REST_Response( $importer->get_progress(), 200 );
	}
}
