<?php

declare(strict_types=1);

namespace Statnive\Tests\unit\Privacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Privacy\PrivacyManager;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

#[CoversClass( PrivacyManager::class )]
final class PrivacyManagerCacheTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		PrivacyManager::reset_cache();
		$GLOBALS['statnive_test_get_option_calls'] = [];
	}

	protected function tearDown(): void {
		PrivacyManager::reset_cache();
		unset( $GLOBALS['statnive_test_get_option_calls'] );
		parent::tearDown();
	}

	public function test_getters_memoise_across_repeated_calls(): void {
		PrivacyManager::get_consent_mode();
		PrivacyManager::get_consent_mode();
		PrivacyManager::should_respect_dnt();
		PrivacyManager::should_respect_dnt();
		PrivacyManager::should_respect_gpc();
		PrivacyManager::should_respect_gpc();
		PrivacyManager::is_tracking_enabled();
		PrivacyManager::is_tracking_enabled();

		$calls = array_count_values( $GLOBALS['statnive_test_get_option_calls'] );

		self::assertSame( 1, $calls['statnive_consent_mode'] ?? 0, 'consent mode should be read once' );
		self::assertSame( 1, $calls['statnive_respect_dnt'] ?? 0, 'respect_dnt should be read once' );
		self::assertSame( 1, $calls['statnive_respect_gpc'] ?? 0, 'respect_gpc should be read once' );
		self::assertSame( 1, $calls['statnive_tracking_enabled'] ?? 0, 'tracking_enabled should be read once' );
	}

	public function test_check_request_privacy_reads_each_option_once(): void {
		PrivacyManager::check_request_privacy(
			[
				'HTTP_DNT'     => '0',
				'HTTP_SEC_GPC' => '0',
			],
			true
		);
		PrivacyManager::check_request_privacy(
			[
				'HTTP_DNT'     => '0',
				'HTTP_SEC_GPC' => '0',
			],
			true
		);

		$calls = array_count_values( $GLOBALS['statnive_test_get_option_calls'] );

		// Each of the four options is read at most once across both calls.
		self::assertSame( 1, $calls['statnive_consent_mode'] ?? 0 );
		self::assertSame( 1, $calls['statnive_respect_dnt'] ?? 0 );
		self::assertSame( 1, $calls['statnive_respect_gpc'] ?? 0 );
		self::assertSame( 1, $calls['statnive_tracking_enabled'] ?? 0 );
	}

	public function test_reset_cache_forces_reread(): void {
		PrivacyManager::get_consent_mode();
		PrivacyManager::reset_cache();
		PrivacyManager::get_consent_mode();

		$calls = array_count_values( $GLOBALS['statnive_test_get_option_calls'] );

		self::assertSame( 2, $calls['statnive_consent_mode'] ?? 0 );
	}
}
