<?php
/**
 * Generated from BDD scenarios (07-privacy-compliance.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Privacy;

use Statnive\Database\DatabaseFactory;
use Statnive\Database\TableRegistry;
use Statnive\Privacy\PrivacyDecision;
use Statnive\Privacy\PrivacyManager;
use WP_UnitTestCase;

/**
 * Integration tests for DNT and GPC header handling.
 *
 * @covers \Statnive\Privacy\PrivacyManager
 */
final class PrivacyHeadersTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		update_option( 'statnive_consent_mode', 'cookieless' );
	}

	/**
	 * @testdox DNT header blocks tracking when DNT respect is enabled
	 */
	public function test_dnt_header_blocks_tracking(): void {
		update_option( 'statnive_respect_dnt', '1' );

		$decision = PrivacyManager::check_request_privacy(
			[ 'HTTP_DNT' => '1', 'HTTP_SEC_GPC' => '' ],
			false
		);

		$this->assertFalse( $decision->allowed(), 'DNT header should block tracking when DNT respect is enabled' );
		$this->assertSame( 'dnt', $decision->reason(), 'Block reason should be "dnt"' );
	}

	/**
	 * @testdox GPC header blocks tracking when GPC respect is enabled
	 */
	public function test_gpc_header_blocks_tracking(): void {
		update_option( 'statnive_respect_gpc', '1' );

		$decision = PrivacyManager::check_request_privacy(
			[ 'HTTP_DNT' => '', 'HTTP_SEC_GPC' => '1' ],
			false
		);

		$this->assertFalse( $decision->allowed(), 'GPC header should block tracking when GPC respect is enabled' );
		$this->assertSame( 'gpc', $decision->reason(), 'Block reason should be "gpc"' );
	}

	/**
	 * @testdox DNT header does not block when DNT respect is disabled
	 */
	public function test_dnt_header_does_not_block_when_disabled(): void {
		// Explicitly disable DNT respect. Use '0' string to ensure get_option
		// returns a stored value, not the default.
		delete_option( 'statnive_respect_dnt' );
		update_option( 'statnive_respect_dnt', 0 );

		$decision = PrivacyManager::check_request_privacy(
			[ 'HTTP_DNT' => '1', 'HTTP_SEC_GPC' => '' ],
			false
		);

		$this->assertTrue( $decision->allowed(), 'DNT header should not block when DNT respect is disabled' );
	}

	/**
	 * @testdox GPC header does not block when GPC respect is disabled
	 */
	public function test_gpc_header_does_not_block_when_disabled(): void {
		// Explicitly disable GPC respect. Use '0' string to ensure get_option
		// returns a stored value, not the default.
		delete_option( 'statnive_respect_gpc' );
		update_option( 'statnive_respect_gpc', 0 );

		$decision = PrivacyManager::check_request_privacy(
			[ 'HTTP_DNT' => '', 'HTTP_SEC_GPC' => '1' ],
			false
		);

		$this->assertTrue( $decision->allowed(), 'GPC header should not block when GPC respect is disabled' );
	}
}
