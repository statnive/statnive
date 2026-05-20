<?php

declare(strict_types=1);

namespace Statnive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves to view_woocommerce_reports OR manage_options so admins
 * on non-WooCommerce sites still see the Statnive menu.
 *
 * PR 1 wires the synthetic cap into admin menus + the GeoIP notice.
 * Migration of REST permission_callbacks from manage_options to this
 * constant lands in PR 2 (WooCommerce detection).
 */
final class Capability {

	/**
	 * Meta capability checked by every Statnive admin + REST surface.
	 */
	public const VIEW_REPORTS = 'statnive_view_reports';

	/**
	 * Wire the runtime fallback.
	 */
	public static function init(): void {
		add_filter( 'user_has_cap', [ self::class, 'grant_view_reports' ], 10, 4 );
	}

	/**
	 * Grant `statnive_view_reports` based on the configured capability chain.
	 *
	 * Default chain: `view_woocommerce_reports` (WC Shop Manager) OR
	 * `manage_options` (WP Administrator). Site owners who want admin-only
	 * access — for example, sites that don't trust their Shop Managers with
	 * analytics — can filter to e.g. `[ 'manage_options' ]` via the
	 * `statnive_view_reports_caps` filter. The chain is OR-combined.
	 *
	 * @param array<string, bool> $allcaps All capabilities of the user.
	 * @param array<int, string>  $caps    Capabilities required by the current check.
	 * @param array<int, mixed>   $args    Arguments passed to the check.
	 * @param \WP_User|null       $user    User object.
	 * @return array<string, bool>
	 */
	public static function grant_view_reports( array $allcaps, array $caps, array $args, $user ): array {
		unset( $args, $user );

		if ( ! in_array( self::VIEW_REPORTS, $caps, true ) ) {
			return $allcaps;
		}

		/**
		 * Filter the capability chain that grants Statnive report access.
		 *
		 * Return an array of WordPress capability strings; the user is
		 * granted `statnive_view_reports` if they have ANY of them.
		 *
		 * @param array<int, string> $chain Default: ['view_woocommerce_reports', 'manage_options'].
		 */
		$chain = (array) apply_filters(
			'statnive_view_reports_caps',
			[ 'view_woocommerce_reports', 'manage_options' ]
		);

		$granted = false;
		foreach ( $chain as $cap ) {
			if ( is_string( $cap ) && ! empty( $allcaps[ $cap ] ) ) {
				$granted = true;
				break;
			}
		}

		$allcaps[ self::VIEW_REPORTS ] = $granted;

		return $allcaps;
	}

	/**
	 * Convenience accessor for templates / hook callbacks.
	 */
	public static function can_view_reports(): bool {
		return current_user_can( self::VIEW_REPORTS );
	}
}
