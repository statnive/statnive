<?php

declare(strict_types=1);

namespace Statnive\Privacy;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypted data archiver for the "archive" retention mode.
 *
 * Archives monthly aggregates before deletion using libsodium encryption.
 * Stores archives in wp_options with HMAC integrity verification.
 */
final class DataArchiver {

	/**
	 * Archive data for a month before it is purged.
	 *
	 * @param string $year_month Month to archive (YYYY-MM format).
	 * @return bool True on success.
	 */
	public static function archive_month( string $year_month ): bool {
		$aggregates = self::collect_monthly_aggregates( $year_month );

		if ( empty( $aggregates ) ) {
			return true;
		}

		$key = self::get_encryption_key();
		if ( null === $key ) {
			return false;
		}

		$plaintext = wp_json_encode( $aggregates );
		if ( false === $plaintext ) {
			return false;
		}

		// Encrypt with libsodium.
		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		// HMAC for integrity.
		$hmac = hash_hmac( 'sha256', $ciphertext, $key );

		$archive = [
			'nonce'      => base64_encode( $nonce ),
			'ciphertext' => base64_encode( $ciphertext ),
			'hmac'       => $hmac,
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
			'month'      => $year_month,
		];

		$option_key = 'statnive_archive_' . str_replace( '-', '_', $year_month );
		return update_option( $option_key, $archive, false );
	}

	/**
	 * Collect monthly aggregate data for archiving.
	 *
	 * @param string $year_month Month in YYYY-MM format.
	 * @return array<string, mixed> Aggregated data.
	 */
	private static function collect_monthly_aggregates( string $year_month ): array {
		global $wpdb;

		$summary = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date, visitors, sessions, views, total_duration, bounces
				FROM `{$summary}`
				WHERE date LIKE %s
				ORDER BY date ASC",
				$year_month . '%'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Get or create the archive encryption key.
	 *
	 * @return string|null 32-byte key or null on failure.
	 */
	private static function get_encryption_key(): ?string {
		$stored = get_option( 'statnive_archive_key', '' );

		if ( ! empty( $stored ) ) {
			$decoded = base64_decode( $stored, true );
			if ( false !== $decoded && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen( $decoded ) ) {
				return $decoded;
			}
		}

		// Generate new key.
		$key = sodium_crypto_secretbox_keygen();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		update_option( 'statnive_archive_key', base64_encode( $key ), false );

		return $key;
	}
}
