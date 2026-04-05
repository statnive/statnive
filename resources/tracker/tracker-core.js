/**
 * Statnive Core Tracker — Minimal inline pageview tracker.
 *
 * Designed to be inlined in wp_footer for zero-external-request pageview tracking.
 * The full tracker (tracker.js) loads async and handles engagement, events, etc.
 *
 * Size target: <300 bytes minified, <180 bytes gzipped.
 *
 * @package Statnive
 */
(function(w, d, n) {
	'use strict';
	if (w.statnive_loaded) return;

	var c = w.StatniveConfig || {};
	var o = c.options || {};
	var h = c.hitParams || {};

	// DNT / GPC privacy check.
	if (o.dntEnabled && (n.doNotTrack === '1' || w.doNotTrack === '1' || n.msDoNotTrack === '1')) return;
	if (o.gpcEnabled && n.globalPrivacyControl) return;

	// Consent mode: don't fire if waiting for consent.
	if (o.consentMode === 'disabled-until-consent') return;

	// Bot detection (4 fast checks).
	if (n.webdriver) return;
	if (typeof w.callPhantom === 'function' || w.__nightmare) return;
	if (!n.languages || n.languages.length === 0) return;

	// Build payload.
	var p = {
		resource_type: h.resource_type || 'page',
		resource_id: h.resource_id || 0,
		referrer: d.referrer || '',
		screen_width: w.screen ? w.screen.width : 0,
		screen_height: w.screen ? w.screen.height : 0,
		language: n.language || '',
		timezone: w.Intl && Intl.DateTimeFormat ? Intl.DateTimeFormat().resolvedOptions().timeZone : '',
		signature: h.signature || '',
		page_url: w.location.pathname || '/',
		page_query: w.location.search ? w.location.search.substring(1) : ''
	};

	// Send via sendBeacon (preferred) or fetch(keepalive).
	var url = c.restUrl;
	if (!url) return;
	var body = JSON.stringify(p);

	if (n.sendBeacon) {
		var blob = new Blob([body], { type: 'text/plain' });
		if (n.sendBeacon(url, blob)) { w.statnive_hit_sent = true; return; }
	}
	if (w.fetch) {
		w.fetch(url, { method: 'POST', body: body, headers: { 'Content-Type': 'text/plain' }, keepalive: true, credentials: 'omit' });
		w.statnive_hit_sent = true;
	}
})(window, document, navigator);
