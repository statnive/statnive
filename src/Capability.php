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
	 * Grant `statnive_view_reports` to anyone who has
	 * `view_woocommerce_reports` (WC) OR `manage_options` (WP core).
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

		$allcaps[ self::VIEW_REPORTS ] =
			! empty( $allcaps['view_woocommerce_reports'] ) ||
			! empty( $allcaps['manage_options'] );

		return $allcaps;
	}

	/**
	 * Convenience accessor for templates / hook callbacks.
	 */
	public static function can_view_reports(): bool {
		return current_user_can( self::VIEW_REPORTS );
	}
}
