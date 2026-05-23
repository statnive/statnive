<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soft WooCommerce detection + compatibility declarations.
 *
 * `before_woocommerce_init` is the canonical hook for plugins to
 * declare HPOS + Block Checkout compatibility. WC reads these
 * declarations to decide whether to expose HPOS to the site.
 */
final class Detector {

	/**
	 * Minimum supported WooCommerce version for the Revenue Report.
	 *
	 * WC 7.0 ships HPOS-stable CRUD APIs. Below this, the Revenue Report
	 * shows a "Requires WooCommerce 7.0 or later" empty state.
	 */
	public const MIN_WC_VERSION = '7.0';

	/**
	 * WC 8.5 shipped first-party Order Attribution; below this we degrade
	 * to Statnive's session-referrer fallback, above this we snapshot
	 * `_wc_order_attribution_*` meta into Statnive's tables.
	 */
	public const MIN_ATTRIBUTION_VERSION = '8.5';

	/**
	 * Hook into WordPress.
	 *
	 * The compatibility declaration is routed through SafeHook so that
	 * any unexpected exception (e.g. WC class autoloader weirdness on a
	 * broken install) can't interrupt the `before_woocommerce_init` chain.
	 */
	public static function init(): void {
		add_action(
			'before_woocommerce_init',
			SafeHook::wrap( [ self::class, 'declare_compatibility' ] )
		);
	}

	/**
	 * Declare HPOS + Block Checkout compatibility.
	 *
	 * Must run on `before_woocommerce_init` so WC sees the declaration
	 * before it decides whether to expose HPOS.
	 */
	public static function declare_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', STATNIVE_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', STATNIVE_FILE, true );
	}

	/**
	 * Is WooCommerce active AND at or above the minimum supported version?
	 */
	public static function is_active(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		return version_compare( self::wc_version(), self::MIN_WC_VERSION, '>=' );
	}

	/**
	 * Does the active WooCommerce ship first-party Order Attribution?
	 */
	public static function has_order_attribution(): bool {
		if ( ! self::is_active() ) {
			return false;
		}
		return version_compare( self::wc_version(), self::MIN_ATTRIBUTION_VERSION, '>=' );
	}

	/**
	 * Is HPOS enabled on this site?
	 */
	public static function is_hpos_enabled(): bool {
		if ( ! self::is_active() ) {
			return false;
		}
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}
		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Active WooCommerce version, or empty string when WC is absent.
	 */
	public static function wc_version(): string {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}
		// Prefer the constant when defined (cheaper); fall back to plugin meta.
		if ( defined( 'WC_VERSION' ) ) {
			return (string) WC_VERSION;
		}
		return '';
	}

	/**
	 * Snapshot for the `/wc-status` REST endpoint + boot data.
	 *
	 * @return array{
	 *   active: bool,
	 *   version: string,
	 *   hpos: bool,
	 *   attribution: bool,
	 *   min_required: string,
	 * }
	 */
	public static function status(): array {
		return [
			'active'       => self::is_active(),
			'version'      => self::wc_version(),
			'hpos'         => self::is_hpos_enabled(),
			'attribution'  => self::has_order_attribution(),
			'min_required' => self::MIN_WC_VERSION,
		];
	}
}
