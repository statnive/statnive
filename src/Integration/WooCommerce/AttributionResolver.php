<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snapshots WooCommerce 8.5+ Order Attribution meta into the
 * `statnive_order_attribution` table at order-record time.
 *
 * Critical invariant: READ-ONLY against WooCommerce. Only uses
 * `$order->get_meta()` getters; never writes back, never modifies
 * post meta, never touches WC tables.
 *
 * WC < 8.5 (or sites that disabled Order Attribution) → all meta
 * comes back empty; the attribution row is written with NULL fields
 * and `channel = 'Unknown'`. Revenue is still counted on the order
 * row regardless.
 */
final class AttributionResolver {

	/**
	 * Meta-key prefix for WC's Order Attribution feature.
	 */
	private const META_PREFIX = '_wc_order_attribution_';

	/**
	 * Channel classifier — order matters: Paid Social before Social.
	 *
	 * @var array<int, string>
	 */
	private const SOCIAL_HOSTS = [
		'facebook.com',
		'instagram.com',
		'twitter.com',
		'x.com',
		'tiktok.com',
		'linkedin.com',
		'pinterest.com',
		'reddit.com',
		'youtube.com',
	];

	/**
	 * Hosts classified as the "AI Assistants" channel.
	 *
	 * @var array<int, string>
	 */
	private const AI_ASSISTANT_HOSTS = [
		'chat.openai.com',
		'chatgpt.com',
		'perplexity.ai',
		'claude.ai',
		'gemini.google.com',
		'copilot.microsoft.com',
	];

	/**
	 * Build the attribution row for a WC order.
	 *
	 * Returns an array keyed by `statnive_order_attribution` column name,
	 * ready to be passed to $wpdb->insert / replace.
	 *
	 * @param \WC_Order $order WooCommerce order (any subclass).
	 * @return array<string, scalar|null>
	 */
	public static function resolve( \WC_Order $order ): array {
		$source_type   = self::meta( $order, 'source_type' );
		$utm_source    = self::meta( $order, 'utm_source' );
		$utm_medium    = self::meta( $order, 'utm_medium' );
		$utm_campaign  = self::meta( $order, 'utm_campaign' );
		$utm_term      = self::meta( $order, 'utm_term' );
		$utm_content   = self::meta( $order, 'utm_content' );
		$referrer      = self::meta( $order, 'referrer' );
		$session_entry = self::meta( $order, 'session_entry' );
		$session_pages = self::meta( $order, 'session_pages' );
		$session_count = self::meta( $order, 'session_count' );
		$device_type   = self::meta( $order, 'device_type' );
		$referrer_host = self::registrable_host( $referrer );

		$utm_medium_lower   = '' !== $utm_medium ? strtolower( $utm_medium ) : '';
		$utm_source_lower   = '' !== $utm_source ? strtolower( $utm_source ) : '';
		$utm_campaign_lower = '' !== $utm_campaign ? strtolower( $utm_campaign ) : '';

		$channel = self::classify(
			$source_type,
			$utm_source_lower,
			$utm_medium_lower,
			$referrer_host
		);

		return [
			'order_id'           => (int) $order->get_id(),
			'source_type'        => '' === $source_type ? null : substr( $source_type, 0, 32 ),
			'channel'            => substr( $channel, 0, 32 ),
			'utm_source'         => '' === $utm_source ? null : substr( $utm_source, 0, 100 ),
			'utm_medium'         => '' === $utm_medium ? null : substr( $utm_medium, 0, 100 ),
			'utm_campaign'       => '' === $utm_campaign ? null : substr( $utm_campaign, 0, 100 ),
			'utm_term'           => '' === $utm_term ? null : substr( $utm_term, 0, 100 ),
			'utm_content'        => '' === $utm_content ? null : substr( $utm_content, 0, 100 ),
			'utm_source_lower'   => '' === $utm_source_lower ? null : substr( $utm_source_lower, 0, 100 ),
			'utm_medium_lower'   => '' === $utm_medium_lower ? null : substr( $utm_medium_lower, 0, 100 ),
			'utm_campaign_lower' => '' === $utm_campaign_lower ? null : substr( $utm_campaign_lower, 0, 100 ),
			'referrer_host'      => null === $referrer_host ? null : substr( $referrer_host, 0, 255 ),
			'first_landing_path' => self::landing_path( $session_entry ),
			'last_landing_path'  => null,
			'session_pages'      => '' === $session_pages ? null : (int) $session_pages,
			'session_count'      => '' === $session_count ? null : (int) $session_count,
			'device_type'        => '' === $device_type ? null : substr( $device_type, 0, 16 ),
			'captured_at_gmt'    => gmdate( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * Classify the order's channel.
	 *
	 * Allow third-party overrides via the `statnive/attribution/channel`
	 * filter.
	 *
	 * @param string      $source_type   WC source_type meta.
	 * @param string      $utm_source    Lowercased utm_source.
	 * @param string      $utm_medium    Lowercased utm_medium.
	 * @param string|null $referrer_host Registrable host of referrer.
	 */
	private static function classify(
		string $source_type,
		string $utm_source,
		string $utm_medium,
		?string $referrer_host
	): string {
		$paid_mediums = [ 'cpc', 'ppc', 'paid', 'paidsearch' ];
		$is_paid      = in_array( $utm_medium, $paid_mediums, true ) ||
						str_ends_with( $utm_source, '-ads' );
		$is_social    = self::host_in_list( $referrer_host, self::SOCIAL_HOSTS ) ||
						in_array( $utm_medium, [ 'social', 'social-network', 'sm' ], true );

		$channel = 'Direct';

		if ( $is_paid && $is_social ) {
			$channel = 'Paid Social';
		} elseif ( $is_paid ) {
			$channel = 'Paid Search';
		} elseif ( 'organic' === $source_type || 'organic' === $utm_medium ) {
			$channel = 'Organic Search';
		} elseif ( $is_social ) {
			$channel = 'Social';
		} elseif ( in_array( $utm_medium, [ 'email', 'e-mail', 'newsletter' ], true ) ) {
			$channel = 'Email';
		} elseif ( self::host_in_list( $referrer_host, self::AI_ASSISTANT_HOSTS ) ) {
			$channel = 'AI Assistants';
		} elseif ( 'referral' === $source_type ) {
			$channel = 'Referral';
		} elseif ( 'typein' === $source_type || ( '' === $source_type && null === $referrer_host ) ) {
			$channel = 'Direct';
		}

		/**
		 * Filter the resolved channel.
		 *
		 * @param string                  $channel   Classified channel name.
		 * @param array<string, mixed>    $context   { source_type, utm_*, referrer_host }.
		 */
		return (string) apply_filters(
			'statnive/attribution/channel',
			$channel,
			[
				'source_type'   => $source_type,
				'utm_source'    => $utm_source,
				'utm_medium'    => $utm_medium,
				'referrer_host' => $referrer_host,
			]
		);
	}

	/**
	 * Read a WC order-attribution meta key as a string (defaulting to '').
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $key   Meta key suffix (without the `_wc_order_attribution_` prefix).
	 */
	private static function meta( \WC_Order $order, string $key ): string {
		$value = $order->get_meta( self::META_PREFIX . $key, true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Extract a registrable host from a referrer URL.
	 *
	 * Returns null for empty / unparseable inputs.
	 *
	 * @param string $referrer Full referrer URL.
	 */
	private static function registrable_host( string $referrer ): ?string {
		if ( '' === $referrer ) {
			return null;
		}
		$host = wp_parse_url( $referrer, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return null;
		}
		return strtolower( $host );
	}

	/**
	 * Pull the path component from a `session_entry` URL.
	 *
	 * @param string $entry session_entry meta value.
	 */
	private static function landing_path( string $entry ): ?string {
		if ( '' === $entry ) {
			return null;
		}
		$path = wp_parse_url( $entry, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return null;
		}
		return substr( $path, 0, 255 );
	}

	/**
	 * Does the host match any entry in the list (suffix match)?
	 *
	 * @param string|null       $host Host to check.
	 * @param array<int,string> $list Allowlist.
	 */
	private static function host_in_list( ?string $host, array $list ): bool {
		if ( null === $host ) {
			return false;
		}
		foreach ( $list as $known ) {
			if ( $host === $known || str_ends_with( $host, '.' . $known ) ) {
				return true;
			}
		}
		return false;
	}
}
