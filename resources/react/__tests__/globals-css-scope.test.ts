import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Regression guard for the "admin asset scoping rule".
 *
 * Tailwind v4's default preflight targets bare `html`, `body`, `*`,
 * `button`, `input`, `a`, `table`, `h1..h6`, `hr` — loading that
 * unscoped stylesheet on the Statnive admin page restyles WP admin
 * chrome (admin bar, sidebar, notices, etc.) even though our PHP
 * enqueue guard only loads the CSS on `toplevel_page_statnive`.
 *
 * `resources/react/globals.css` intentionally skips the default
 * preflight import and ships a copy prefixed with `#statnive-app`.
 * This test locks that in: every style rule (anything outside an
 * at-rule header) must descend from `#statnive-app`.
 *
 * If this test fails, read the Admin Asset Scoping Rule in CLAUDE.md
 * before "fixing" it.
 */
describe('globals.css scoping', () => {
	const cssPath = resolve(__dirname, '..', 'globals.css');
	const css = readFileSync(cssPath, 'utf8');

	it('never re-imports the unscoped Tailwind preflight', () => {
		expect(css).not.toMatch(/@import\s+["']tailwindcss\/preflight/);
		expect(css).not.toMatch(/@import\s+["']tailwindcss["'](?!\s*\/)/);
	});

	it('every style rule descends from #statnive-app', () => {
		// Strip comments so they can't contain "html {" examples that trip
		// the parser.
		const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '');

		// Walk the file block-by-block. For each `{`, look back to find
		// the selector list (or at-rule). Skip at-rules and @theme/@layer
		// wrappers — only leaf style rules matter. Every individual
		// selector in the list must start with `#statnive-app`.
		const violations: string[] = [];
		let depth = 0;
		let ruleStart = 0;
		for (let i = 0; i < stripped.length; i++) {
			const ch = stripped[i];
			if (ch === '{') {
				const selector = stripped.slice(ruleStart, i).trim();
				const isAtRule = selector.startsWith('@');
				if (!isAtRule && depth >= 1) {
					// Split the selector list on top-level commas, respecting
					// brackets and parens so `:is(a, b)` / `[type='a', 'b']`
					// don't get split mid-group.
					const parts: string[] = [];
					let paren = 0;
					let bracket = 0;
					let current = '';
					for (const sc of selector) {
						if (sc === '(') paren++;
						else if (sc === ')') paren--;
						else if (sc === '[') bracket++;
						else if (sc === ']') bracket--;
						if (sc === ',' && paren === 0 && bracket === 0) {
							if (current.trim()) parts.push(current.trim());
							current = '';
						} else {
							current += sc;
						}
					}
					if (current.trim()) parts.push(current.trim());
					for (const part of parts) {
						if (!part.startsWith('#statnive-app')) {
							violations.push(part);
						}
					}
				}
				depth++;
				ruleStart = i + 1;
			} else if (ch === '}') {
				depth--;
				ruleStart = i + 1;
			} else if (ch === ';' && depth >= 1) {
				// Reset rule start after declarations so property names
				// aren't mistaken for selectors.
				ruleStart = i + 1;
			}
		}

		expect(violations, 'Selectors not rooted at #statnive-app').toEqual([]);
	});

	it('defines the scoped preflight under #statnive-app', () => {
		// Spot-check a few anchor rules from the scoped preflight copy.
		expect(css).toMatch(/#statnive-app\s+button/);
		expect(css).toMatch(/#statnive-app\s+input/);
		expect(css).toMatch(/#statnive-app\s+a\b/);
		expect(css).toMatch(/#statnive-app\s+table/);
		expect(css).toMatch(/#statnive-app\s+\*/);
	});
});
