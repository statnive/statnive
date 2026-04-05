<?php

declare(strict_types=1);

namespace Statnive\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License status value object.
 *
 * Represents the result of a license validation check.
 */
final class LicenseStatus {

	public const STATUS_VALID   = 'valid';
	public const STATUS_INVALID = 'invalid';
	public const STATUS_EXPIRED = 'expired';
	public const STATUS_ERROR   = 'error';
	public const STATUS_FREE    = 'free';

	public readonly string $status;
	public readonly string $plan_tier;
	public readonly ?string $expires_at;
	public readonly string $license_key_masked;

	/**
	 * Constructor.
	 *
	 * @param string  $status             Status constant.
	 * @param string  $plan_tier          Plan tier (free, starter, professional, agency).
	 * @param ?string $expires_at         ISO 8601 expiry date, or null.
	 * @param string  $license_key_masked Last 4 chars of license key.
	 */
	public function __construct(
		string $status,
		string $plan_tier = 'free',
		?string $expires_at = null,
		string $license_key_masked = ''
	) {
		$this->status             = $status;
		$this->plan_tier          = $plan_tier;
		$this->expires_at         = $expires_at;
		$this->license_key_masked = $license_key_masked;
	}

	/**
	 * Create a "valid" status.
	 *
	 * @param string $tier       Plan tier.
	 * @param string $expires_at Expiry date.
	 * @param string $key_masked Masked license key.
	 * @return self
	 */
	public static function valid( string $tier, string $expires_at, string $key_masked = '' ): self {
		return new self( self::STATUS_VALID, $tier, $expires_at, $key_masked );
	}

	/**
	 * Create a "free" status (no license).
	 *
	 * @return self
	 */
	public static function free(): self {
		return new self( self::STATUS_FREE, 'free' );
	}

	/**
	 * Create an "expired" status.
	 *
	 * @param string $key_masked Masked license key.
	 * @return self
	 */
	public static function expired( string $key_masked = '' ): self {
		return new self( self::STATUS_EXPIRED, 'free', null, $key_masked );
	}

	/**
	 * Create an "invalid" status.
	 *
	 * @return self
	 */
	public static function invalid(): self {
		return new self( self::STATUS_INVALID, 'free' );
	}

	/**
	 * Create an "error" status (API unreachable).
	 *
	 * @return self
	 */
	public static function error(): self {
		return new self( self::STATUS_ERROR, 'free' );
	}

	/**
	 * Check if the license is active (valid and not expired).
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return self::STATUS_VALID === $this->status;
	}

	/**
	 * Serialize to array for REST response.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'status'     => $this->status,
			'plan_tier'  => $this->plan_tier,
			'expires_at' => $this->expires_at,
			'key_masked' => $this->license_key_masked,
		];
	}

	/**
	 * Create from cached array.
	 *
	 * @param array<string, mixed> $data Cached data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			$data['status'] ?? self::STATUS_FREE,
			$data['plan_tier'] ?? 'free',
			$data['expires_at'] ?? null,
			$data['key_masked'] ?? ''
		);
	}
}
