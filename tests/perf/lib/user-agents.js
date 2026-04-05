/**
 * User-Agent string database categorized by device type.
 *
 * Provides realistic UA rotation for traffic simulation.
 * Plugin-agnostic — works for any WordPress site.
 */

const DESKTOP_UAS = [
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0.0',
	'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
	'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 OPR/110.0.0.0',
];

const MOBILE_UAS = [
	'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
	'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.82 Mobile Safari/537.36',
	'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Mobile/15E148 Safari/604.1',
	'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.82 Mobile Safari/537.36',
	'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/124.0.6367.88 Mobile/15E148 Safari/604.1',
	'Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.82 Mobile Safari/537.36',
];

const TABLET_UAS = [
	'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
	'Mozilla/5.0 (iPad; CPU OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Mobile/15E148 Safari/604.1',
	'Mozilla/5.0 (Linux; Android 14; SM-X810) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.82 Safari/537.36',
];

const BOT_UAS = [
	'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
	'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
	'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
	'ClaudeBot/1.0; +https://www.anthropic.com/claude-bot',
	'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
	'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)',
	'Mozilla/5.0 (compatible; DotBot/1.2; +https://opensiteexplorer.org/dotbot)',
	'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
];

const ALL_UAS = { desktop: DESKTOP_UAS, mobile: MOBILE_UAS, tablet: TABLET_UAS, bot: BOT_UAS };

/** Known bot UA substrings for detection. */
const BOT_SIGNATURES = [
	'Googlebot', 'bingbot', 'GPTBot', 'ClaudeBot', 'AhrefsBot',
	'SemrushBot', 'DotBot', 'YandexBot', 'Baiduspider', 'facebookexternalhit',
	'Twitterbot', 'LinkedInBot', 'Slurp', 'DuckDuckBot', 'Applebot',
];

/**
 * Pick a random element from an array.
 */
function pick(arr) {
	return arr[Math.floor(Math.random() * arr.length)];
}

/**
 * Get a random User-Agent for a given device type.
 * @param {'desktop'|'mobile'|'tablet'|'bot'} deviceType
 * @returns {string}
 */
export function getUA(deviceType) {
	const pool = ALL_UAS[deviceType] || DESKTOP_UAS;
	return pick(pool);
}

/**
 * Get a random bot User-Agent.
 * @returns {string}
 */
export function getBotUA() {
	return pick(BOT_UAS);
}

/**
 * Check if a User-Agent string belongs to a known bot.
 * @param {string} ua
 * @returns {boolean}
 */
export function isBot(ua) {
	return BOT_SIGNATURES.some((sig) => ua.includes(sig));
}

/**
 * Get a random UA weighted by realistic traffic distribution.
 * Desktop 60%, Mobile 30%, Tablet 10%.
 * @returns {{ ua: string, deviceType: string }}
 */
export function getWeightedUA() {
	const roll = Math.random();
	if (roll < 0.6) return { ua: getUA('desktop'), deviceType: 'desktop' };
	if (roll < 0.9) return { ua: getUA('mobile'), deviceType: 'mobile' };
	return { ua: getUA('tablet'), deviceType: 'tablet' };
}
