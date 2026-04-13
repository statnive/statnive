<?php
/**
 * Generated from BDD scenarios (03-analytics-enrichment.feature) — adjust when source classes are implemented.
 */

declare(strict_types=1);

namespace Statnive\Tests\Integration\Service;

use Statnive\Database\DatabaseFactory;
use Statnive\Service\DeviceService;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for device detection service.
 *
 * @covers \Statnive\Service\DeviceService
 */
final class DeviceServiceTest extends WP_UnitTestCase {

	/** @var string Unique correlation ID for test isolation. */
	private string $correlation_id;

	public function set_up(): void {
		parent::set_up();
		DatabaseFactory::create_tables();
		$this->correlation_id = 'TEST_' . uniqid( '', true );
		DeviceService::clear_cache();
	}

	/**
	 * @testdox Device type classification from User-Agent
	 * @dataProvider device_type_provider
	 *
	 * @param string $user_agent   User-Agent string.
	 * @param string $expected_type Expected device type.
	 */
	public function test_device_type_classification( string $user_agent, string $expected_type ): void {
		if ( ! class_exists( '\DeviceDetector\DeviceDetector' ) ) {
			$this->markTestSkipped( 'DeviceDetector library not available (Mozart dependency not built).' );
		}

		$result = DeviceService::parse( $user_agent );

		$this->assertSame( $expected_type, $result['device_type'], "User-Agent should be classified as {$expected_type}" );
	}

	/**
	 * Data provider for device type classification.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function device_type_provider(): array {
		return [
			'Desktop (Windows Chrome)' => [
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
				'Desktop',
			],
			'Mobile (iPhone Safari)' => [
				'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1',
				'Mobile',
			],
			'Tablet (iPad Safari)' => [
				'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1',
				'Tablet',
			],
		];
	}

	/**
	 * @testdox Browser name and version extraction
	 */
	public function test_browser_name_and_version_extraction(): void {
		if ( ! class_exists( '\DeviceDetector\DeviceDetector' ) ) {
			$this->markTestSkipped( 'DeviceDetector library not available (Mozart dependency not built).' );
		}

		$result = DeviceService::parse(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'
		);

		$this->assertSame( 'Chrome', $result['browser_name'], 'Browser name should be "Chrome"' );
		$this->assertStringStartsWith( '120', $result['browser_version'], 'Browser version should start with "120"' );
	}

	/**
	 * @testdox OS detection from User-Agent
	 * @dataProvider os_detection_provider
	 *
	 * @param string $user_agent   User-Agent string.
	 * @param string $expected_os  Expected OS name.
	 */
	public function test_os_detection( string $user_agent, string $expected_os ): void {
		if ( ! class_exists( '\DeviceDetector\DeviceDetector' ) ) {
			$this->markTestSkipped( 'DeviceDetector library not available (Mozart dependency not built).' );
		}

		$result = DeviceService::parse( $user_agent );

		$this->assertSame( $expected_os, $result['os_name'], "OS should be detected as {$expected_os}" );
	}

	/**
	 * Data provider for OS detection.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function os_detection_provider(): array {
		return [
			'Windows' => [
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
				'Windows',
			],
			'macOS' => [
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
				'Mac',
			],
			'Linux' => [
				'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
				'GNU/Linux',
			],
			'iOS' => [
				'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1',
				'iOS',
			],
			'Android' => [
				'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
				'Android',
			],
		];
	}

	/**
	 * @testdox Empty UA returns empty results
	 */
	public function test_empty_ua_returns_empty_results(): void {
		$result = DeviceService::parse( '' );

		$this->assertSame( '', $result['device_type'], 'Empty UA should return empty device_type' );
		$this->assertSame( '', $result['browser_name'], 'Empty UA should return empty browser_name' );
		$this->assertSame( '', $result['os_name'], 'Empty UA should return empty os_name' );
	}
}
