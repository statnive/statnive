/**
 * Consent banner integration module.
 *
 * Listens for consent-change events from popular consent banner plugins
 * and triggers or blocks the Statnive tracker accordingly.
 *
 * Supported banners:
 * - Real Cookie Banner (rcb-consent-change)
 * - Complianz (cmplz_fire_categories)
 * - CookieYes (cookieyes_consent_update)
 *
 * @param {Function} initTracker - The tracker init function to call on consent.
 * @param {Function} setConsent  - Sets consent_granted flag on payload.
 */
export function registerConsentBannerListeners(initTracker, setConsent) {
	var consentGranted = false;

	function onConsent() {
		if (consentGranted) return;
		consentGranted = true;
		setConsent(true);
		initTracker();
	}

	// Real Cookie Banner.
	document.addEventListener('rcb-consent-change', function(e) {
		if (e && e.detail && e.detail.cookie) {
			var groups = e.detail.cookie.consent;
			if (groups && groups.statistics) {
				onConsent();
			}
		}
	});

	// Complianz.
	document.addEventListener('cmplz_fire_categories', function(e) {
		if (e && e.detail) {
			var cats = e.detail.categories || e.detail;
			if (Array.isArray(cats) && cats.indexOf('statistics') !== -1) {
				onConsent();
			}
		}
	});

	// CookieYes.
	document.addEventListener('cookieyes_consent_update', function(e) {
		if (e && e.detail) {
			var accepted = e.detail.accepted || [];
			if (accepted.indexOf('analytics') !== -1 || accepted.indexOf('statistics') !== -1) {
				onConsent();
			}
		}
	});
}
