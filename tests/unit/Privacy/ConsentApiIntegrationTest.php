<?php

declare(strict_types=1);

/*
 * Bracketed namespaces let us define `wp_has_consent` in the global
 * namespace alongside the test class. Production code in
 * Statnive\Privacy\ConsentApiIntegration calls `function_exists( 'wp_has_consent' )`
 * which only resolves global-namespace functions — defining the stub
 * inside `namespace Statnive\Tests\unit\Privacy { … }` would not satisfy
 * that guard.
 *
 * The "function missing" branch in ConsentApiIntegration::has_consent()
 * is behaviourally identical to "function returns false" — both fall
 * through to `return false`. The grant-via-banner branch (function
 * returns true) is the only path where function presence changes the
 * outcome, and that path is covered explicitly below.
 */
namespace {
	if ( ! function_exists( 'wp_has_consent' ) ) {
		function wp_has_consent( string $category ): bool {
			return (bool) ( $GLOBALS['statnive_test_wp_has_consent_return'] ?? false );
		}
	}
}

namespace Statnive\Tests\unit\Privacy {

	use PHPUnit\Framework\Attributes\CoversClass;
	use PHPUnit\Framework\TestCase;
	use Statnive\Privacy\ConsentApiIntegration;
	use Statnive\Privacy\ConsentMode;
	use Statnive\Privacy\PrivacyManager;

	defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

	#[CoversClass(ConsentApiIntegration::class)]
	final class ConsentApiIntegrationTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();
			PrivacyManager::reset_cache();
			$GLOBALS['statnive_test_options']               = [];
			$GLOBALS['statnive_test_wp_has_consent_return'] = false;
		}

		protected function tearDown(): void {
			PrivacyManager::reset_cache();
			unset(
				$GLOBALS['statnive_test_options'],
				$GLOBALS['statnive_test_wp_has_consent_return']
			);
			parent::tearDown();
		}

		public function test_grants_in_cookieless_mode_regardless_of_payload_signal(): void {
			update_option( 'statnive_consent_mode', ConsentMode::COOKIELESS );

			$this->assertTrue( ConsentApiIntegration::has_consent( false ) );
			$this->assertTrue( ConsentApiIntegration::has_consent( true ) );
		}

		public function test_grants_in_disabled_until_consent_when_payload_signal_present(): void {
			update_option( 'statnive_consent_mode', ConsentMode::DISABLED_UNTIL_CONSENT );

			$this->assertTrue(
				ConsentApiIntegration::has_consent( true ),
				'Tracker payload consent flag must short-circuit before WP Consent API.'
			);
		}

		public function test_blocks_in_disabled_until_consent_when_consent_api_returns_false(): void {
			update_option( 'statnive_consent_mode', ConsentMode::DISABLED_UNTIL_CONSENT );
			$GLOBALS['statnive_test_wp_has_consent_return'] = false;

			$this->assertFalse( ConsentApiIntegration::has_consent( false ) );
		}

		public function test_grants_in_disabled_until_consent_when_consent_api_returns_true(): void {
			update_option( 'statnive_consent_mode', ConsentMode::DISABLED_UNTIL_CONSENT );
			$GLOBALS['statnive_test_wp_has_consent_return'] = true;

			$this->assertTrue( ConsentApiIntegration::has_consent( false ) );
		}
	}

}
