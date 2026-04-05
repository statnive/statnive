<?php

declare(strict_types=1);

namespace Statnive\Addon\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API key manager.
 *
 * Generates, stores, validates, and revokes API keys for external access.
 * Keys are stored as SHA-256 hashes in wp_options (no plaintext persistence).
 */
final class ApiKeyManager {

	private const OPTION_KEY = 'statnive_api_keys';

	/**
	 * Generate a new API key.
	 *
	 * @param string $name     Key name/description.
	 * @param int    $user_id  Owner user ID.
	 * @return array{key: string, id: string, name: string} The raw key (shown once) and metadata.
	 */
	public static function generate_key( string $name, int $user_id ): array {
		$raw_key = 'stn_' . bin2hex( random_bytes( 24 ) );
		$id      = wp_generate_uuid4();

		$keys   = self::get_all_keys();
		$keys[] = [
			'id'         => $id,
			'name'       => $name,
			'user_id'    => $user_id,
			'key_hash'   => hash( 'sha256', $raw_key ),
			'key_prefix' => substr( $raw_key, 0, 8 ),
			'created_at' => current_time( 'mysql', true ),
		];

		update_option( self::OPTION_KEY, $keys, false );

		return [
			'key'  => $raw_key,
			'id'   => $id,
			'name' => $name,
		];
	}

	/**
	 * Validate an API key and return the associated user ID.
	 *
	 * @param string $raw_key The raw API key.
	 * @return int|false User ID if valid, false otherwise.
	 */
	public static function validate_key( string $raw_key ) {
		$hash = hash( 'sha256', $raw_key );
		$keys = self::get_all_keys();

		foreach ( $keys as $key_data ) {
			if ( hash_equals( $key_data['key_hash'], $hash ) ) {
				return (int) $key_data['user_id'];
			}
		}

		return false;
	}

	/**
	 * Revoke an API key by ID.
	 *
	 * @param string $id Key UUID.
	 * @return bool True if revoked.
	 */
	public static function revoke_key( string $id ): bool {
		$keys    = self::get_all_keys();
		$initial = count( $keys );

		$keys = array_values(
			array_filter(
				$keys,
				static fn( array $k ): bool => $k['id'] !== $id
			)
		);

		if ( count( $keys ) < $initial ) {
			update_option( self::OPTION_KEY, $keys, false );
			return true;
		}

		return false;
	}

	/**
	 * List all API keys (without hashes, for admin display).
	 *
	 * @return array<int, array{id: string, name: string, key_prefix: string, created_at: string}>
	 */
	public static function list_keys(): array {
		$keys = self::get_all_keys();

		return array_map(
			static fn( array $k ): array => [
				'id'         => $k['id'],
				'name'       => $k['name'],
				'key_prefix' => $k['key_prefix'] ?? '',
				'created_at' => $k['created_at'],
			],
			$keys
		);
	}

	/**
	 * Get all stored keys.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_all_keys(): array {
		$keys = get_option( self::OPTION_KEY, [] );
		return is_array( $keys ) ? $keys : [];
	}
}
