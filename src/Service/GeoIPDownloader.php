<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GeoIP database downloader.
 *
 * Two databases supported, mutually exclusive:
 *   • MaxMind GeoLite2-City — requires a license key and EULA accept
 *   • DB-IP IP-to-City Lite  — anonymously downloadable, CC-BY-4.0
 *
 * Both ship as `.mmdb` files consumed by the same `\GeoIp2\Database\Reader`.
 * The active provider is determined by which file is on disk in
 * `wp_upload_dir()/statnive/`.
 *
 * @see https://www.maxmind.com/en/geolite2/eula
 * @see https://db-ip.com/db/about/
 */
final class GeoIPDownloader {

	/**
	 * WP-Cron hook name.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'statnive_weekly_geoip_update';

	/**
	 * Heartbeat option written by the cron callback in CronRegistrar
	 * and read by Statnive\Admin\CronHealth.
	 *
	 * @var string
	 */
	public const LAST_RUN_OPTION = 'statnive_last_geoip_update';

	/**
	 * Transient key set by enable_dbip_city() so the cron callback knows to
	 * attempt the first DB-IP download before the .mmdb file exists. Cleared
	 * by the cron callback once the download succeeds (file presence then
	 * becomes the steady-state signal).
	 *
	 * @var string
	 */
	public const DBIP_PENDING_TRANSIENT = 'statnive_geoip_dbip_pending';

	/**
	 * MaxMind .mmdb filename inside wp_upload_dir()/statnive/.
	 *
	 * @var string
	 */
	public const MAXMIND_FILENAME = 'GeoLite2-City.mmdb';

	/**
	 * DB-IP IP-to-City Lite .mmdb filename inside wp_upload_dir()/statnive/.
	 *
	 * @var string
	 */
	public const DBIP_FILENAME = 'dbip-city-lite.mmdb';

	/**
	 * Download the GeoIP database to the uploads directory.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function download(): bool {
		$target_dir  = dirname( GeoIPService::get_database_path() );
		$target_path = GeoIPService::get_database_path();

		if ( ! self::prepare_target_dir( $target_dir ) ) {
			return false;
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

		if ( ! self::respect_backoff() ) {
			return false;
		}

		$url = sprintf(
			'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=%s&suffix=tar.gz',
			rawurlencode( $license_key )
		);

		return self::record_attempt(
			self::download_to_target( $url, $target_path, 'tar.gz', 'MaxMind' )
		);
	}

	/**
	 * Ensure the GeoIP uploads directory exists and is protected from direct
	 * web access. Idempotent across both downloaders running on the same tick.
	 *
	 * @param string $target_dir Absolute directory path.
	 * @return bool True if the directory exists and is ready to receive a write.
	 */
	private static function prepare_target_dir( string $target_dir ): bool {
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return false;
		}
		$htaccess = $target_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}
		return true;
	}

	/**
	 * Exponential-backoff guard shared by both downloaders. Bumps the
	 * `statnive_geoip_last_attempt` heartbeat as a side effect on the
	 * permitted path so subsequent calls within the same window short-circuit.
	 *
	 * @return bool True when a download attempt is permitted, false when the
	 *              caller should skip due to recent failures.
	 */
	private static function respect_backoff(): bool {
		$failures = (int) get_option( 'statnive_geoip_failures', 0 );
		if ( $failures > 0 ) {
			$last_attempt    = (int) get_option( 'statnive_geoip_last_attempt', 0 );
			$backoff_seconds = min( pow( 2, $failures ) * HOUR_IN_SECONDS, WEEK_IN_SECONDS );
			if ( time() - $last_attempt < $backoff_seconds ) {
				return false;
			}
		}
		update_option( 'statnive_geoip_last_attempt', time(), false );
		return true;
	}

	/**
	 * Record the outcome of a download attempt — reset failure counter on
	 * success, increment on failure. Returns the same bool unchanged so
	 * callers can `return self::record_attempt( $result );` in one line.
	 *
	 * @param bool $ok Outcome of the download.
	 * @return bool Same as $ok.
	 */
	private static function record_attempt( bool $ok ): bool {
		if ( $ok ) {
			delete_option( 'statnive_geoip_failures' );
			return true;
		}
		$failures = (int) get_option( 'statnive_geoip_failures', 0 );
		update_option( 'statnive_geoip_failures', $failures + 1, false );
		return false;
	}

	/**
	 * Download an archive from a URL and extract the .mmdb file to $target.
	 *
	 * Two archive formats are supported:
	 *   • 'tar.gz' — MaxMind: archive contains a directory with the .mmdb inside.
	 *   • 'gz'     — DB-IP:   gzip-compressed .mmdb file directly (no inner archive).
	 *
	 * @param string $url     URL to download.
	 * @param string $target  Target path for the extracted .mmdb file.
	 * @param string $format  'tar.gz' | 'gz'.
	 * @param string $log_tag Short tag for error messages ('MaxMind' / 'DB-IP').
	 * @return bool True on success.
	 */
	private static function download_to_target( string $url, string $target, string $format, string $log_tag ): bool {
		$tmp_file = download_url( $url, 300 );

		if ( is_wp_error( $tmp_file ) ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Statnive][GeoIP] ' . $log_tag . ' download failed: ' . $tmp_file->get_error_message() );
			}
			return false;
		}

		try {
			$ok = ( 'tar.gz' === $format )
				? self::extract_targz_to( $tmp_file, $target )
				: self::extract_gz_to( $tmp_file, $target );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $tmp_file );
			return $ok;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Statnive][GeoIP] ' . $log_tag . ' extraction failed: ' . $e->getMessage() );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $tmp_file );
			return false;
		}
	}

	/**
	 * Decompress a tar.gz archive and write its inner .mmdb file to $target.
	 *
	 * @param string $tmp_file Path to the downloaded tar.gz archive.
	 * @param string $target   Target .mmdb path.
	 * @return bool True if a .mmdb was found and written.
	 */
	private static function extract_targz_to( string $tmp_file, string $target ): bool {
		$phar = new \PharData( $tmp_file );
		$phar->decompress();

		$tar_path = preg_replace( '/\.gz$/', '', $tmp_file );
		if ( ! $tar_path || ! file_exists( $tar_path ) ) {
			return false;
		}

		$tar   = new \PharData( $tar_path );
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $tar_path );
		return $found;
	}

	/**
	 * Decompress a plain .gz file (no tar wrapper) directly to $target.
	 *
	 * Uses PHP's built-in `compress.zlib://` stream wrapper so the entire
	 * file does not need to be loaded into memory — relevant for the ~80 MB
	 * DB-IP IP-to-City Lite database.
	 *
	 * @param string $tmp_file Path to the downloaded .gz file.
	 * @param string $target   Target .mmdb path.
	 * @return bool True on success.
	 */
	private static function extract_gz_to( string $tmp_file, string $target ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		$copied = copy( 'compress.zlib://' . $tmp_file, $target );
		if ( ! $copied || ! file_exists( $target ) || filesize( $target ) < 1024 ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		chmod( $target, 0640 );
		return true;
	}

	/**
	 * Absolute path of the DB-IP IP-to-City Lite database in the uploads dir.
	 *
	 * @return string
	 */
	public static function get_dbip_city_path(): string {
		return GeoIPService::get_database_dir() . self::DBIP_FILENAME;
	}

	/**
	 * Download the DB-IP IP-to-City Lite database to the uploads directory.
	 *
	 * Anonymously downloadable from db-ip.com — no license key, no account.
	 * The URL embeds the current year-month; mid-month 404s self-resolve when
	 * the next month's file appears (existing exponential-backoff handles it).
	 *
	 * @return bool True on success.
	 */
	public static function download_dbip_city(): bool {
		$target_path = self::get_dbip_city_path();

		if ( ! self::prepare_target_dir( dirname( $target_path ) ) ) {
			return false;
		}

		if ( ! self::respect_backoff() ) {
			return false;
		}

		$url = sprintf(
			'https://download.db-ip.com/free/dbip-city-lite-%s.mmdb.gz',
			gmdate( 'Y-m' )
		);

		$ok = self::download_to_target( $url, $target_path, 'gz', 'DB-IP' );
		if ( $ok ) {
			// First download succeeded; the file is now the steady-state signal.
			delete_transient( self::DBIP_PENDING_TRANSIENT );
		}
		return self::record_attempt( $ok );
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
	 * Whether DB-IP IP-to-City is active for this install.
	 *
	 * Active when either (a) the .mmdb file is on disk, or (b) the user has
	 * just clicked Enable and the first download is pending. Pure-read; safe
	 * to call from any context.
	 *
	 * @return bool
	 */
	public static function is_dbip_city_active(): bool {
		return file_exists( self::get_dbip_city_path() )
			|| (bool) get_transient( self::DBIP_PENDING_TRANSIENT );
	}

	/**
	 * Enable the DB-IP IP-to-City Lite path.
	 *
	 * Must only be called from an explicit user-click REST POST — never from
	 * activation. Arms a 1-hour pending transient (so the cron callback knows
	 * to attempt the first install before the .mmdb lands), schedules a
	 * single-event firing of the existing weekly cron hook for ~5 seconds
	 * out, and makes sure the recurring weekly schedule is in place. Once the
	 * file is on disk, file presence becomes the steady-state signal and the
	 * transient is no longer consulted.
	 */
	public static function enable_dbip_city(): void {
		if ( ! file_exists( self::get_dbip_city_path() ) ) {
			set_transient( self::DBIP_PENDING_TRANSIENT, 1, HOUR_IN_SECONDS );
		}
		// `wp_schedule_single_event` with the same hook within ~10 minutes is
		// deduped by core, so a double-click is safe.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) || wp_next_scheduled( self::CRON_HOOK ) > time() + 60 ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
		self::schedule();
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
