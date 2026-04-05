/**
 * Client-side bot detection module.
 *
 * Uses multiple heuristics to detect automated browsers and bots.
 * Results are sent in the tracker payload for server-side confirmation.
 *
 * @returns {{is_bot: boolean, reason: string}}
 */
export function detectBot() {
	// 1. WebDriver check (Selenium, Puppeteer, Playwright).
	if (navigator.webdriver) {
		return { is_bot: true, reason: 'webdriver' };
	}

	// 2. Entropy test — deterministic PRNGs in automation tools fail this.
	if (Math.random() === Math.random()) {
		return { is_bot: true, reason: 'entropy' };
	}

	// 3. Headless browser detection.
	if (window.callPhantom || window._phantom || window.__nightmare) {
		return { is_bot: true, reason: 'headless' };
	}

	// 4. Missing navigator.languages (common in bots).
	if (!navigator.languages || navigator.languages.length === 0) {
		return { is_bot: true, reason: 'no_languages' };
	}

	return { is_bot: false, reason: '' };
}
