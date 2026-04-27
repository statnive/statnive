/**
 * Consent-banner event helpers.
 *
 * Each function dispatches the exact `CustomEvent` shape that
 * `resources/tracker/modules/consent-banners.js` listens for. Use after
 * `page.goto()` but before asserting on analytics writes — the tracker
 * boots on DOMContentLoaded, then the event flips it into "tracking on".
 */

import type { Page } from '@playwright/test';

export type BannerKind = 'rcb' | 'cmplz' | 'cookieyes';

export async function grantConsent(page: Page, banner: BannerKind): Promise<void> {
	await page.evaluate((kind) => {
		switch (kind) {
			case 'rcb':
				document.dispatchEvent(
					new CustomEvent('rcb-consent-change', {
						detail: { cookie: { consent: { statistics: true } } },
					})
				);
				return;
			case 'cmplz':
				document.dispatchEvent(
					new CustomEvent('cmplz_fire_categories', {
						detail: { categories: ['statistics'] },
					})
				);
				return;
			case 'cookieyes':
				document.dispatchEvent(
					new CustomEvent('cookieyes_consent_update', {
						detail: { accepted: ['analytics'] },
					})
				);
				return;
		}
	}, banner);
}

export async function revokeConsent(page: Page): Promise<void> {
	await page.evaluate(() => {
		document.dispatchEvent(
			new CustomEvent('rcb-consent-change', {
				detail: { cookie: { consent: { statistics: false } } },
			})
		);
	});
}
