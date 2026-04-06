<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Device detection service using matomo/device-detector.
 *
 * Parses User-Agent strings server-side to extract device type,
 * browser, browser version, and OS. Never stores raw UA strings.
 *
 * Dependency: matomo/device-detector (Mozart-scoped to Statnive\Dependencies\DeviceDetector).
 */
final class DeviceService {

	/**
	 * In-memory cache of parsed UA strings (per-request).
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $cache = [];

	/**
	 * Parse a User-Agent string into device categories.
	 *
	 * @param string $user_agent Raw User-Agent string.
	 * @return array{device_type: string, browser_name: string, browser_version: string, os_name: string}
	 */
	public static function parse( string $user_agent ): array {
		$empty = [
			'device_type'     => '',
			'browser_name'    => '',
			'browser_version' => '',
			'os_name'         => '',
		];

		if ( empty( $user_agent ) ) {
			return $empty;
		}

		// Check per-request cache.
		$cache_key = md5( $user_agent );
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		try {
			// Use matomo/device-detector (Mozart-scoped).
			if ( ! class_exists( '\DeviceDetector\DeviceDetector' ) ) {
				$result                    = self::parse_basic( $user_agent );
				self::$cache[ $cache_key ] = $result;
				return $result;
			}

			$dd = new \DeviceDetector\DeviceDetector( $user_agent );
			$dd->parse();

			$device_type = self::normalize_device_type( $dd->getDeviceName() );
			if ( $dd->isBot() ) {
				$device_type = 'Bot';
			}

			$client = $dd->getClient();
			$os     = $dd->getOs();

			$result = [
				'device_type'     => $device_type,
				'browser_name'    => is_array( $client ) ? ( $client['name'] ?? '' ) : '',
				'browser_version' => is_array( $client ) ? ( $client['version'] ?? '' ) : '',
				'os_name'         => is_array( $os ) ? ( $os['name'] ?? '' ) : '',
			];

			self::$cache[ $cache_key ] = $result;
			return $result;

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Statnive][Device] Detection failed: ' . $e->getMessage() );
			}
			return $empty;
		}
	}

	/**
	 * Normalize device type names to a standard set.
	 *
	 * @param string $type Raw device type from detector.
	 * @return string Normalized type: Desktop, Mobile, Tablet, TV, Console, Wearable, Camera, Other.
	 */
	private static function normalize_device_type( string $type ): string {
		$map = [
			'desktop'               => 'Desktop',
			'smartphone'            => 'Mobile',
			'tablet'                => 'Tablet',
			'feature phone'         => 'Mobile',
			'phablet'               => 'Mobile',
			'console'               => 'Console',
			'tv'                    => 'TV',
			'car browser'           => 'Other',
			'smart display'         => 'Other',
			'smart speaker'         => 'Other',
			'camera'                => 'Camera',
			'portable media player' => 'Other',
			'wearable'              => 'Wearable',
		];

		$lower = strtolower( $type );
		return $map[ $lower ] ?? 'Other';
	}

	/**
	 * Basic regex-based UA parser fallback when DeviceDetector is unavailable.
	 *
	 * @param string $ua User-Agent string.
	 * @return array{device_type: string, browser_name: string, browser_version: string, os_name: string}
	 */
	private static function parse_basic( string $ua ): array {
		// Browser detection (order matters: Edge before Chrome, Chrome before Safari).
		$browser_name    = '';
		$browser_version = '';
		$patterns        = [
			'Edg'     => '/Edg(?:e|A|iOS)?\/(\d+[\.\d]*)/',
			'Firefox' => '/Firefox\/(\d+[\.\d]*)/',
			'Opera'   => '/(?:OPR|Opera)\/(\d+[\.\d]*)/',
			'Chrome'  => '/Chrome\/(\d+[\.\d]*)/',
			'Safari'  => '/Version\/(\d+[\.\d]*).*Safari/',
		];

		foreach ( $patterns as $name => $pattern ) {
			if ( preg_match( $pattern, $ua, $m ) ) {
				$browser_name    = 'Edg' === $name ? 'Edge' : $name;
				$browser_version = $m[1];
				break;
			}
		}

		// OS detection.
		$os_name = '';
		if ( preg_match( '/Windows/', $ua ) ) {
			$os_name = 'Windows';
		} elseif ( preg_match( '/Android/', $ua ) ) {
			$os_name = 'Android';
		} elseif ( preg_match( '/iPhone|iPad|iPod/', $ua ) ) {
			$os_name = 'iOS';
		} elseif ( preg_match( '/Macintosh|Mac OS/', $ua ) ) {
			$os_name = 'Mac';
		} elseif ( preg_match( '/Linux/', $ua ) ) {
			$os_name = 'GNU/Linux';
		}

		// Device type detection.
		$device_type = 'Desktop';
		if ( preg_match( '/Mobile|Android.*Mobile|iPhone/', $ua ) ) {
			$device_type = 'Mobile';
		} elseif ( preg_match( '/iPad|Android(?!.*Mobile)|Tablet/', $ua ) ) {
			$device_type = 'Tablet';
		}

		return [
			'device_type'     => $device_type,
			'browser_name'    => $browser_name,
			'browser_version' => $browser_version,
			'os_name'         => $os_name,
		];
	}

	/**
	 * Clear the per-request cache.
	 */
	public static function clear_cache(): void {
		self::$cache = [];
	}
}
