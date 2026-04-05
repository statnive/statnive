<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Known bot User-Agent patterns for server-side detection.
 *
 * @package Statnive
 *
 * Each entry is a regex fragment (case-insensitive matching).
 *
 * @return string[]
 */

return [
	// Search engine crawlers.
	'googlebot',
	'bingbot',
	'slurp',
	'duckduckbot',
	'baiduspider',
	'yandexbot',
	'sogou',
	'exabot',
	'ia_archiver',

	// AI bots.
	'gptbot',
	'chatgpt-user',
	'claude-web',
	'anthropic-ai',
	'cohere-ai',
	'perplexitybot',
	'youbot',

	// Social media crawlers.
	'facebookexternalhit',
	'twitterbot',
	'linkedinbot',
	'pinterestbot',
	'telegrambot',
	'whatsapp',
	'discordbot',
	'slackbot',

	// SEO/monitoring tools.
	'semrushbot',
	'ahrefsbot',
	'mj12bot',
	'dotbot',
	'rogerbot',
	'screaming frog',
	'seokicks',

	// Feed readers.
	'feedfetcher',
	'feedly',
	'newsblur',

	// Uptime monitors.
	'uptimerobot',
	'pingdom',
	'statuscake',
	'site24x7',
	'newrelicpinger',

	// CLI tools and libraries.
	'curl/',
	'wget/',
	'python-requests',
	'python-urllib',
	'java/',
	'apache-httpclient',
	'go-http-client',
	'node-fetch',
	'axios/',
	'libwww-perl',

	// Headless browsers.
	'headlesschrome',
	'phantomjs',
	'slimerjs',

	// Generic bot indicators.
	'bot/',
	'spider/',
	'crawler/',
	'scraper/',
	'http_request',
];
