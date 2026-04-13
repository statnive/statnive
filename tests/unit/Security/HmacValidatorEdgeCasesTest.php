<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Security\HmacValidator;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Edge-case tests for HmacValidator.
 *
 * Exercises the generate/verify round-trip through the real class
 * (using the bootstrap option stubs for get_option / update_option).
 */
#[CoversClass(HmacValidator::class)]
final class HmacValidatorEdgeCasesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset test options so each test starts with a fresh HMAC secret.
		$GLOBALS['statnive_test_options'] = [];
	}

	public function test_empty_signature_fails_verification(): void {
		$this->assertFalse(
			HmacValidator::verify( '', 'post', 42 ),
			'An empty signature must never pass verification.'
		);
	}

	public function test_tampered_signature_fails_verification(): void {
		$valid = HmacValidator::generate( 'post', 42 );

		// Flip the first character to simulate tampering.
		$first    = $valid[0];
		$tampered = ( '0' === $first ? '1' : '0' ) . substr( $valid, 1 );

		$this->assertFalse(
			HmacValidator::verify( $tampered, 'post', 42 ),
			'A tampered signature must not pass verification.'
		);
	}
}
