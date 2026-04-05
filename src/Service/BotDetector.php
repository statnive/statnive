<?php

declare(strict_types=1);

namespace Statnive\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side bot detection via User-Agent pattern matching.
 *
 * Matches against ~200 known bot UA patterns.
 */
final class BotDetector {

	/**
	 * Cached compiled regex.
	 *
	 * @var string|null
	 */
	private static ?string $regex = null;

	/**
	 * Check if a User-Agent belongs to a bot.
	 *
	 * @param string $user_agent User-Agent string.
	 * @return bool True if bot detected.
	 */
	public static function is_bot( string $user_agent ): bool {
		if ( empty( $user_agent ) ) {
			return true;
		}

		return 1 === preg_match( self::get_regex(), $user_agent );
	}

	/**
	 * Get the bot category/reason for a User-Agent.
	 *
	 * @param string $user_agent User-Agent string.
	 * @return string Bot category or empty string.
	 */
	public static function get_reason( string $user_agent ): string {
		if ( empty( $user_agent ) ) {
			return 'empty_ua';
		}

		$ua = strtolower( $user_agent );

		if ( str_contains( $ua, 'googlebot' ) || str_contains( $ua, 'bingbot' ) || str_contains( $ua, 'yandexbot' ) ) {
			return 'search_crawler';
		}
		if ( str_contains( $ua, 'gptbot' ) || str_contains( $ua, 'chatgpt' ) || str_contains( $ua, 'claude' ) || str_contains( $ua, 'anthropic' ) ) {
			return 'ai_bot';
		}
		if ( str_contains( $ua, 'curl' ) || str_contains( $ua, 'wget' ) || str_contains( $ua, 'python' ) || str_contains( $ua, 'java/' ) ) {
			return 'cli_tool';
		}

		return 'bot';
	}

	/**
	 * Get the compiled bot detection regex.
	 *
	 * @return string Regex pattern.
	 */
	private static function get_regex(): string {
		if ( null === self::$regex ) {
			$patterns    = require __DIR__ . '/../Data/bot-patterns.php';
			self::$regex = '#' . implode( '|', $patterns ) . '#i';
		}

		return self::$regex;
	}
}
