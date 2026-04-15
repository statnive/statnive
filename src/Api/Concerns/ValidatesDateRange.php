<?php

declare(strict_types=1);

namespace Statnive\Api\Concerns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for validating YYYY-MM-DD date range parameters in REST routes.
 *
 * Use as a validate_callback on `from` and `to` route args:
 *
 *     'from' => [
 *         'required'          => true,
 *         'type'              => 'string',
 *         'validate_callback' => [ $this, 'validate_date' ],
 *         'sanitize_callback' => 'sanitize_text_field',
 *     ],
 */
trait ValidatesDateRange {

	/**
	 * Validate that a value is a YYYY-MM-DD date string.
	 *
	 * @param string $value Date string to validate.
	 * @return bool True if valid YYYY-MM-DD format.
	 */
	public function validate_date( $value ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}
}
