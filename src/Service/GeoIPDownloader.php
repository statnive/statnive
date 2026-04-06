<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GeoIP database downloader.
 *
 * Downloads MaxMind GeoLite2-City .mmdb file from MaxMind using a license key.
 * Requires user to accept MaxMind GeoLite2 EULA and obtain a free license key.
 * Scheduled weekly via WP-Cron.
 *
 * @see https://www.maxmind.com/en/geolite2/eula
 */
final class GeoIPDownloader {

	/**
	 * WP-Cron hook name.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'statnive_weekly_geoip_update';

	/**
	 * Download the GeoIP database to the uploads directory.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function download(): bool {
		$target_dir  = dirname( GeoIPService::get_database_path() );
		$target_path = GeoIPService::get_database_path();

		// Create directory if needed.
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return false;
		}

		// Protect directory from direct access.
		$htaccess = $target_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		// MaxMind license key is required — no third-party mirrors.
		$license_key = get_option( 'statnive_maxmind_license_key', '' );
		if ( empty( $license_key ) ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Statnive][GeoIP] Download skipped: no MaxMind license key configured.' );
			}
			return false;
		}

		$url = sprintf(
			'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=%s&suffix=tar.gz',
			rawurlencode( $license_key )
		);

		return self::download_and_extract_targz( $url, $target_path );
	}

	/**
	 * Download a tar.gz archive and extract the .mmdb file from it.
	 *
	 * MaxMind distributes GeoLite2 databases as tar.gz archives containing
	 * a directory with the .mmdb file inside.
	 *
	 * @param string $url    URL to download.
	 * @param string $target Target path for the extracted .mmdb file.
	 * @return bool True on success.
	 */
	private static function download_and_extract_targz( string $url, string $target ): bool {
		$tmp_file = download_url( $url, 300 );

		if ( is_wp_error( $tmp_file ) ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Statnive][GeoIP] MaxMind download failed: ' . $tmp_file->get_error_message() );
			}
			return false;
		}

		try {
			$phar = new \PharData( $tmp_file );
			$phar->decompress();

			// The decompressed .tar file path.
			$tar_path = preg_replace( '/\.gz$/', '', $tmp_file );
			if ( ! $tar_path || ! file_exists( $tar_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $tmp_file );
				return false;
			}

			$tar = new \PharData( $tar_path );

			// Find the .mmdb file inside the archive.
			$found = false;
			foreach ( new \RecursiveIteratorIterator( $tar ) as $entry ) {
				if ( str_ends_with( $entry->getPathname(), '.mmdb' ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					file_put_contents( $target, file_get_contents( $entry->getPathname() ) );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
					chmod( $target, 0640 );
					$found = true;
					break;
				}
			}

			// Cleanup temp files.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp_file );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tar_path );

			return $found;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Statnive][GeoIP] Archive extraction failed: ' . $e->getMessage() );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp_file );
			return false;
		}
	}

	/**
	 * Check if GeoIP feature is enabled by the user.
	 *
	 * @return bool True if GeoIP downloads are enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( 'statnive_geoip_enabled', false );
	}

	/**
	 * Enable GeoIP feature: set option, schedule cron, trigger first download.
	 *
	 * Called when user enables GeoIP in Settings.
	 * Requires a MaxMind license key to be configured first.
	 */
	public static function enable(): void {
		$license_key = get_option( 'statnive_maxmind_license_key', '' );
		if ( empty( $license_key ) ) {
			update_option( 'statnive_geoip_enabled', false );
			return;
		}

		update_option( 'statnive_geoip_enabled', true );
		self::schedule();
		self::download();
	}

	/**
	 * Disable GeoIP feature: unset option and unschedule cron.
	 */
	public static function disable(): void {
		update_option( 'statnive_geoip_enabled', false );
		self::unschedule();
	}

	/**
	 * Schedule weekly GeoIP database update.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the GeoIP update cron.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
