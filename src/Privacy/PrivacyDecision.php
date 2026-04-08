<?php

declare(strict_types=1);

namespace Statnive\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object representing a privacy check decision.
 *
 * Returned by PrivacyManager::check_request_privacy().
 */
final class PrivacyDecision {

	/**
	 * Whether tracking is allowed.
	 *
	 * @var bool
	 */
	private bool $allowed;

	/**
	 * Reason for blocking (empty if allowed).
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * The consent mode that was active.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Constructor.
	 *
	 * @param bool   $allowed Whether tracking is allowed.
	 * @param string $reason  Reason for blocking.
	 * @param string $mode    Active consent mode.
	 */
	public function __construct( bool $allowed, string $reason, string $mode ) {
		$this->allowed = $allowed;
		$this->reason  = $reason;
		$this->mode    = $mode;
	}

	/**
	 * Whether tracking is allowed.
	 *
	 * @return bool
	 */
	public function allowed(): bool {
		return $this->allowed;
	}

	/**
	 * Reason for blocking (empty if allowed).
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * The consent mode that was active.
	 *
	 * @return string
	 */
	public function mode(): string {
		return $this->mode;
	}

	/**
	 * Create an "allowed" decision.
	 *
	 * @param string $mode Consent mode.
	 * @return self
	 */
	public static function allow( string $mode ): self {
		return new self( true, '', $mode );
	}

	/**
	 * Create a "blocked" decision.
	 *
	 * @param string $reason Why tracking was blocked.
	 * @param string $mode   Consent mode.
	 * @return self
	 */
	public static function block( string $reason, string $mode ): self {
		return new self( false, $reason, $mode );
	}
}
