<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ensures version numbers stay in sync across all project files (bug #11).
 *
 * Parses actual files to detect version mismatches between the plugin header,
 * the STATNIVE_VERSION constant, and package.json.
 *
 * @covers \Statnive\Service\DeviceService
 */
final class VersionConsistencyTest extends TestCase {

	/**
	 * Path to the plugin root directory.
	 */
	private string $plugin_root;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 2 );
	}

	public function test_plugin_header_version_matches_constant(): void {
		$contents = file_get_contents( $this->plugin_root . '/statnive.php' );
		$this->assertNotFalse( $contents, 'statnive.php must be readable.' );

		// Extract Version from plugin header comment.
		$header_found = preg_match( '/Version:\s+(\S+)/', $contents, $header_match );
		$this->assertSame( 1, $header_found, 'Plugin header must contain a Version line.' );
		$header_version = $header_match[1];

		// Extract STATNIVE_VERSION constant.
		$const_found = preg_match( "/define\(\s*'STATNIVE_VERSION',\s*'([^']+)'\s*\)/", $contents, $const_match );
		$this->assertSame( 1, $const_found, 'statnive.php must define STATNIVE_VERSION constant.' );
		$const_version = $const_match[1];

		$this->assertSame(
			$header_version,
			$const_version,
			"Plugin header Version ({$header_version}) must match STATNIVE_VERSION constant ({$const_version})."
		);
	}

	public function test_package_json_version_matches_constant(): void {
		$package_path = $this->plugin_root . '/package.json';
		$this->assertFileExists( $package_path, 'package.json must exist.' );

		$package_json = json_decode( (string) file_get_contents( $package_path ), true );
		$this->assertIsArray( $package_json, 'package.json must be valid JSON.' );
		$this->assertArrayHasKey( 'version', $package_json, 'package.json must have a version field.' );
		$package_version = $package_json['version'];

		// Extract STATNIVE_VERSION constant from statnive.php.
		$contents   = file_get_contents( $this->plugin_root . '/statnive.php' );
		$this->assertNotFalse( $contents, 'statnive.php must be readable.' );

		$const_found = preg_match( "/define\(\s*'STATNIVE_VERSION',\s*'([^']+)'\s*\)/", $contents, $const_match );
		$this->assertSame( 1, $const_found, 'statnive.php must define STATNIVE_VERSION constant.' );
		$const_version = $const_match[1];

		$this->assertSame(
			$package_version,
			$const_version,
			"package.json version ({$package_version}) must match STATNIVE_VERSION constant ({$const_version})."
		);
	}
}
