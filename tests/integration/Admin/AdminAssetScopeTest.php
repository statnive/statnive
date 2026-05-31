<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Admin;

use Statnive\Admin\ReactHandler;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Regression guard for the "admin asset scoping rule".
 *
 * The Statnive React dashboard bundle and its CSS must load **only**
 * on the Statnive admin page (`toplevel_page_statnive`). Loading them
 * anywhere else in wp-admin would restyle WordPress core chrome via
 * Tailwind preflight.
 *
 * See CLAUDE.md → "Admin Asset Scoping Rule".
 *
 * @covers \Statnive\Admin\ReactHandler::enqueue_assets
 */
final class AdminAssetScopeTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_deregister_script( 'statnive-dashboard' );
	}

	public function tear_down(): void {
		wp_deregister_script( 'statnive-dashboard' );
		parent::tear_down();
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function non_statnive_admin_pages(): array {
		return [
			// Representative WP core admin page.
			'dashboard'           => [ 'index.php' ],
			// A look-alike `toplevel_page_*` hook from another plugin —
			// proves the guard does an exact match, not a prefix match.
			'other plugin'        => [ 'toplevel_page_some-other-plugin' ],
			// A look-alike submenu hook from another plugin.
			'other plugin submenu' => [ 'statnive_page_some-other-plugin-tab' ],
			// Empty string (defensive).
			'empty hook'          => [ '' ],
		];
	}

	/**
	 * @dataProvider non_statnive_admin_pages
	 */
	public function test_react_dashboard_assets_do_not_load_on_other_admin_pages( string $hook_suffix ): void {
		ReactHandler::enqueue_assets( $hook_suffix );

		$this->assertFalse(
			wp_script_is( 'statnive-dashboard', 'enqueued' ),
			"statnive-dashboard JS must not enqueue on {$hook_suffix}."
		);
		$this->assertFalse(
			wp_script_is( 'statnive-dashboard', 'registered' ),
			"statnive-dashboard JS must not register on {$hook_suffix}."
		);
		$this->assertFalse(
			wp_style_is( 'statnive-dashboard-0', 'enqueued' ),
			"statnive-dashboard-0 CSS must not enqueue on {$hook_suffix}."
		);
	}

	/**
	 * Data provider: every Statnive admin hook should enqueue assets.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function statnive_admin_pages(): array {
		return [
			'overview' => [ 'toplevel_page_statnive' ],
			'ask'      => [ 'statnive_page_statnive-ask' ],
			'revenue'  => [ 'statnive_page_statnive-revenue' ],
			'settings' => [ 'statnive_page_statnive-settings' ],
		];
	}

	/**
	 * @dataProvider statnive_admin_pages
	 */
	public function test_react_dashboard_assets_load_on_every_statnive_page( string $hook_suffix ): void {
		$manifest_path = STATNIVE_PATH . 'public/react/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			$this->markTestSkipped( 'React bundle not built; run `npm run build` first.' );
		}

		ReactHandler::enqueue_assets( $hook_suffix );

		$this->assertTrue(
			wp_script_is( 'statnive-dashboard', 'enqueued' ),
			"statnive-dashboard JS must enqueue on {$hook_suffix}."
		);
		$this->assertTrue(
			wp_style_is( 'statnive-dashboard-0', 'enqueued' ),
			"statnive-dashboard-0 CSS must enqueue on {$hook_suffix}."
		);
	}

	public function test_hook_suffixes_constant_contains_all_statnive_pages(): void {
		$this->assertContains( 'toplevel_page_statnive', ReactHandler::HOOK_SUFFIXES );
		$this->assertContains( 'statnive_page_statnive-ask', ReactHandler::HOOK_SUFFIXES );
		$this->assertContains( 'statnive_page_statnive-revenue', ReactHandler::HOOK_SUFFIXES );
		$this->assertContains( 'statnive_page_statnive-settings', ReactHandler::HOOK_SUFFIXES );
		$this->assertCount( 4, ReactHandler::HOOK_SUFFIXES );
	}
}
