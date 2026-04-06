<?php

declare(strict_types=1);

namespace Statnive\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Thrown by PayloadValidator::decode_json_object() when the body
 * cannot be decoded as a JSON object.
 *
 * Carries the same [code, message, status] shape as the other helpers
 * return so callers can translate uniformly.
 */
final class PayloadValidatorException extends RuntimeException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * HTTP status code to emit.
	 *
	 * @var int
	 */
	private $status_code;

	/**
	 * Constructor.
	 *
	 * @param string $error_code  Machine-readable error code.
	 * @param string $message     Human-readable error message.
	 * @param int    $status_code HTTP status code.
	 */
	public function __construct( string $error_code, string $message, int $status_code ) {
		parent::__construct( $message );
		$this->error_code  = $error_code;
		$this->status_code = $status_code;
	}

	/**
	 * Get the machine-readable error code.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @return int
	 */
	public function get_status_code(): int {
		return $this->status_code;
	}

	/**
	 * Convert this exception into the [code, message, status] tuple
	 * used by the rest of the PayloadValidator API.
	 *
	 * @return array{0: string, 1: string, 2: int}
	 */
	public function to_tuple(): array {
		return [ $this->error_code, $this->getMessage(), $this->status_code ];
	}
}
