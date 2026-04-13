<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Frontend;

use Statnive\Frontend\FrontendHandler;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

/**
 * Integration tests for the FrontendHandler tracker loading.
 *
 * Validates async strategy, inline config injection, SRI caching,
 * and conditional enqueue logic.
 *
 * @covers \Statnive\Frontend\FrontendHandler
 */
final class FrontendHandlerTest extends WP_UnitTestCase {

	/**
	 * Tracker script file path for test purposes.
	 */
	private string $tracker_path;

	public function set_up(): void {
		parent::set_up();

		$this->tracker_path = STATNIVE_PATH . 'public/tracker/statnive.js';

		// Ensure tracking is enabled.
		update_option( 'statnive_tracking_enabled', true );

		// Reset script registry.
		wp_deregister_script( 'statnive-tracker' );

		// Clear SRI transients.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_statnive_sri_%'" );
	}

	public function tear_down(): void {
		wp_deregister_script( 'statnive-tracker' );
		parent::tear_down();
	}

	/**
	 * Test that the tracker script is registered with async strategy.
	 */
	public function test_tracker_script_has_async_strategy(): void {
		if ( ! file_exists( $this->tracker_path ) ) {
			$this->markTestSkipped( 'Tracker JS not built.' );
		}

		$this->go_to( '/' );
		FrontendHandler::enqueue_tracker();

		$scripts  = wp_scripts();
		$registered = $scripts->registered['statnive-tracker'] ?? null;

		$this->assertNotNull( $registered, 'statnive-tracker should be registered.' );

		// WordPress 6.3+ stores strategy in extra['strategy'].
		$strategy = $registered->extra['strategy'] ?? '';
		$this->assertSame( 'async', $strategy, 'Tracker must use async strategy.' );
	}

	/**
	 * Test that config is injected via wp_add_inline_script('before'),
	 * NOT wp_localize_script.
	 */
	public function test_config_uses_inline_before_not_localize(): void {
		if ( ! file_exists( $this->tracker_path ) ) {
			$this->markTestSkipped( 'Tracker JS not built.' );
		}

		$this->go_to( '/' );
		FrontendHandler::enqueue_tracker();

		$scripts    = wp_scripts();
		$registered = $scripts->registered['statnive-tracker'] ?? null;

		$this->assertNotNull( $registered );

		// wp_localize_script stores data in $registered->extra['data'].
		// wp_add_inline_script('before') stores in $registered->extra['before'].
		$localized = $registered->extra['data'] ?? '';
		$this->assertEmpty( $localized, 'Must NOT use wp_localize_script (forces blocking).' );

		$before = $registered->extra['before'] ?? [];
		$this->assertNotEmpty( $before, 'Must use wp_add_inline_script with before position.' );

		// Verify the inline script sets window.StatniveConfig.
		$inline_content = implode( "\n", $before );
		$this->assertStringContainsString( 'window.StatniveConfig=', $inline_content );
	}

	/**
	 * Test that StatniveConfig contains all required keys.
	 */
	public function test_config_contains_required_keys(): void {
		if ( ! file_exists( $this->tracker_path ) ) {
			$this->markTestSkipped( 'Tracker JS not built.' );
		}

		$this->go_to( '/' );
		FrontendHandler::enqueue_tracker();

		$scripts = wp_scripts();
		$before  = $scripts->registered['statnive-tracker']->extra['before'] ?? [];
		$inline  = implode( "\n", $before );

		// Extract JSON from 'window.StatniveConfig={...};'
		preg_match( '/window\.StatniveConfig=(.+);/', $inline, $matches );
		$this->assertNotEmpty( $matches[1] ?? '', 'Config JSON not found in inline script.' );

		$config = json_decode( $matches[1], true );
		$this->assertIsArray( $config );

		// Required top-level keys.
		$this->assertArrayHasKey( 'restUrl', $config );
		$this->assertArrayHasKey( 'eventUrl', $config );
		$this->assertArrayHasKey( 'engagementUrl', $config );
		$this->assertArrayHasKey( 'ajaxUrl', $config );
		$this->assertArrayHasKey( 'hitParams', $config );
		$this->assertArrayHasKey( 'options', $config );

		// hitParams must include resource_type, resource_id, signature.
		$this->assertArrayHasKey( 'resource_type', $config['hitParams'] );
		$this->assertArrayHasKey( 'resource_id', $config['hitParams'] );
		$this->assertArrayHasKey( 'signature', $config['hitParams'] );

		// Signature must be 64 hex chars (SHA-256 HMAC).
		$this->assertMatchesRegularExpression(
			'/^[a-f0-9]{64}$/',
			$config['hitParams']['signature']
		);
	}

	/**
	 * Test that SRI hash is cached in a transient after first computation.
	 */
	public function test_sri_hash_cached_in_transient(): void {
		if ( ! file_exists( $this->tracker_path ) ) {
			$this->markTestSkipped( 'Tracker JS not built.' );
		}

		$mtime     = (int) filemtime( $this->tracker_path );
		$cache_key = 'statnive_sri_' . $mtime;

		// Ensure transient does not exist yet.
		$this->assertFalse( get_transient( $cache_key ) );

		// Trigger SRI computation.
		$tag = '<script src="test.js"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- test fixture string, not actual script output.
		FrontendHandler::add_sri_attribute( $tag, 'statnive-tracker' );

		// Transient should now be set.
		$cached_hash = get_transient( $cache_key );
		$this->assertNotFalse( $cached_hash );
		$this->assertStringStartsWith( 'sha256-', $cached_hash );
	}

	/**
	 * Test that SRI hash transient invalidates when file changes.
	 */
	public function test_sri_hash_invalidates_on_file_change(): void {
		if ( ! file_exists( $this->tracker_path ) ) {
			$this->markTestSkipped( 'Tracker JS not built.' );
		}

		$mtime     = (int) filemtime( $this->tracker_path );
		$cache_key = 'statnive_sri_' . $mtime;

		// Set a stale transient with different mtime.
		$stale_key = 'statnive_sri_' . ( $mtime - 1 );
		set_transient( $stale_key, 'sha256-stalevalue', MONTH_IN_SECONDS );

		// Trigger SRI computation — should NOT use stale value.
		$tag = '<script src="test.js"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- test fixture string, not actual script output.
		FrontendHandler::add_sri_attribute( $tag, 'statnive-tracker' );

		// New transient should exist with correct mtime key.
		$cached_hash = get_transient( $cache_key );
		$this->assertNotFalse( $cached_hash );
		$this->assertNotSame( 'sha256-stalevalue', $cached_hash );
	}

	/**
	 * Test that tracker is not enqueued when tracking is disabled.
	 */
	public function test_tracker_not_enqueued_when_disabled(): void {
		update_option( 'statnive_tracking_enabled', false );

		$this->go_to( '/' );
		FrontendHandler::enqueue_tracker();

		$this->assertFalse(
			wp_script_is( 'statnive-tracker', 'enqueued' ),
			'Tracker must not enqueue when tracking is disabled.'
		);
	}
}
