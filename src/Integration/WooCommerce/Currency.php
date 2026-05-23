<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store-currency snapshot helpers.
 *
 * WooCommerce-conditional with safe defaults so the rest of the plugin
 * never has to feature-test for `function_exists`.
 */
final class Currency {

	/**
	 * ISO 4217 currency code from WooCommerce, falling back to 'USD'.
	 */
	public static function code(): string {
		return self::wc_string( 'get_woocommerce_currency', 'USD' );
	}

	/**
	 * Display symbol for the store currency, falling back to '$'.
	 */
	public static function symbol(): string {
		return self::wc_string( 'get_woocommerce_currency_symbol', '$' );
	}

	/**
	 * Currency minor-unit decimals (2 for USD/EUR, 0 for JPY, 3 for BHD).
	 */
	public static function decimals(): int {
		if ( function_exists( 'wc_get_price_decimals' ) ) {
			return (int) wc_get_price_decimals();
		}
		return 2;
	}

	/**
	 * Bundle for `wp_localize_script` consumers.
	 *
	 * @return array{code: string, symbol: string, minorUnit: int}
	 */
	public static function snapshot(): array {
		return [
			'code'      => self::code(),
			'symbol'    => self::symbol(),
			'minorUnit' => self::decimals(),
		];
	}

	/**
	 * Read a WooCommerce string-returning function, with fallback when WC is absent.
	 *
	 * @param string $wc_fn    WooCommerce function name to call when WC is active.
	 * @param string $fallback Value to return when WC is absent or returns empty.
	 */
	private static function wc_string( string $wc_fn, string $fallback ): string {
		if ( function_exists( $wc_fn ) ) {
			$value = (string) call_user_func( $wc_fn );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return $fallback;
	}
}
