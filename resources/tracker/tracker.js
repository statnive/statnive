/**
 * Statnive Tracker — Privacy-first analytics tracking script.
 *
 * IIFE wrapper for zero global pollution.
 * Size target: <5KB gzipped.
 *
 * @package Statnive
 */

// ES imports — Vite bundles these into the IIFE at build time.
// Tree-shaking via __FEATURE_* flags removes unused code.
import { detectBot } from './modules/bot-detect.js';
import { createEngagementTracker } from './modules/engagement.js';
import { registerAutoTracking } from './modules/auto-track.js';
import { registerCssEventTracking } from './modules/css-events.js';
import { registerConsentBannerListeners } from './modules/consent-banners.js';

// Event queue proxy — allows calling statnive() before script loads.
window.statnive = window.statnive || function() {
	(window.statnive.q = window.statnive.q || []).push(arguments);
};

(function (window, document) {
	'use strict';

	// Prevent double-loading.
	if (window.statnive_loaded) {
		return;
	}
	window.statnive_loaded = true;

	// Read configuration injected by FrontendHandler via wp_localize_script.
	var config = window.StatniveConfig || {};
	var options = config.options || {};
	var hitParams = config.hitParams || {};
	var consentGranted = false;

	/**
	 * Check if tracking should be blocked by Do Not Track or Global Privacy Control.
	 */
	function isTrackingBlocked() {
		if (options.dntEnabled) {
			var dnt = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;
			if (dnt === '1' || dnt === 'yes') {
				return true;
			}
		}
		if (options.gpcEnabled && navigator.globalPrivacyControl) {
			return true;
		}
		return false;
	}

	/**
	 * Build the pageview tracking payload.
	 */
	function buildPayload() {
		var payload = {
			resource_type: hitParams.resource_type || 'page',
			resource_id: hitParams.resource_id || 0,
			referrer: document.referrer || '',
			screen_width: window.screen ? window.screen.width : 0,
			screen_height: window.screen ? window.screen.height : 0,
			language: navigator.language || '',
			timezone: Intl && Intl.DateTimeFormat
				? Intl.DateTimeFormat().resolvedOptions().timeZone
				: '',
			signature: hitParams.signature || '',
			page_url: window.location.pathname || '/',
			page_query: window.location.search ? window.location.search.substring(1) : ''
		};
		if (consentGranted) {
			payload.consent_granted = true;
		}
		return payload;
	}

	/**
	 * Apply transformRequest hook if configured.
	 */
	function applyTransform(payload) {
		var cfg = window.StatniveConfig || {};
		if (typeof cfg.transformRequest === 'function') {
			return cfg.transformRequest(payload) || payload;
		}
		return payload;
	}

	/**
	 * Send data to the server via sendBeacon → fetch(keepalive) → XHR fallback.
	 */
	function sendToUrl(url, payload) {
		payload = applyTransform(payload);
		var body = JSON.stringify(payload);

		if (navigator.sendBeacon) {
			var blob = new Blob([body], { type: 'text/plain' });
			if (navigator.sendBeacon(url, blob)) return;
		}

		if (window.fetch) {
			fetch(url, {
				method: 'POST',
				body: body,
				headers: { 'Content-Type': 'text/plain' },
				keepalive: true,
				credentials: 'omit'
			}).catch(function () {
				if (config.ajaxUrl && options.useAjax) {
					sendViaXHR(config.ajaxUrl + '?action=statnive_hit', body);
				}
			});
			return;
		}

		sendViaXHR(url, body);
	}

	function sendViaXHR(url, body) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'text/plain');
		xhr.send(body);
	}

	/**
	 * Send a pageview hit.
	 */
	function sendHit(payload) {
		sendToUrl(config.restUrl, payload);
	}

	/**
	 * Send a custom event.
	 */
	function sendEvent(eventName, properties) {
		if (!config.eventUrl) return;
		var payload = {
			event_name: eventName,
			properties: properties || {},
			resource_type: hitParams.resource_type || 'page',
			resource_id: hitParams.resource_id || 0,
			signature: hitParams.signature || ''
		};
		if (consentGranted) {
			payload.consent_granted = true;
		}
		sendToUrl(config.eventUrl, payload);
	}

	// Engagement tracker instance (created in init if feature enabled).
	var engagementTracker = null;

	/**
	 * Send engagement data on page unload.
	 */
	function flushEngagement() {
		if (!engagementTracker || !config.engagementUrl) return;
		engagementTracker.stop();
		var metrics = engagementTracker.getMetrics();

		// Only send if meaningful engagement occurred.
		if (metrics.scroll_depth <= 0 && metrics.engagement_time < 3) return;

		var payload = {
			engagement_time: metrics.engagement_time,
			scroll_depth: metrics.scroll_depth,
			resource_type: hitParams.resource_type || 'page',
			resource_id: hitParams.resource_id || 0,
			signature: hitParams.signature || ''
		};
		sendToUrl(config.engagementUrl, payload);
	}

	/**
	 * Set consent granted flag (called by consent banner listeners).
	 */
	function setConsentGranted() {
		consentGranted = true;
	}

	/**
	 * Initialize tracker.
	 */
	function init() {
		if (isTrackingBlocked()) return;
		if (!config.restUrl) return;

		// Bot detection (client-side).
		if (__FEATURE_BOT_DETECTION__) {
			var botCheck = detectBot();
			if (botCheck.is_bot) return; // Silent drop for bots.
		}

		// Stage 1: Send pageview immediately.
		sendHit(buildPayload());

		// Stage 2: Start engagement tracking (deferred).
		if (__FEATURE_ENGAGEMENT__) {
			engagementTracker = createEngagementTracker();
			engagementTracker.start();
			document.addEventListener('visibilitychange', function() {
				if (document.hidden) flushEngagement();
			});
			window.addEventListener('beforeunload', flushEngagement);
		}

		// Custom events: auto-tracking + CSS events.
		if (__FEATURE_EVENTS__) {
			if (options.autoTrack) {
				registerAutoTracking(sendEvent);
			}
			registerCssEventTracking(sendEvent);
		}
	}

	// Expose the event API globally.
	if (__FEATURE_EVENTS__) {
		var queue = window.statnive.q || [];
		window.statnive = function(eventName, props) {
			sendEvent(eventName, props);
		};
		// Process queued events.
		for (var i = 0; i < queue.length; i++) {
			sendEvent(queue[i][0], queue[i][1]);
		}
	}

	// SPA support placeholder.
	if (__FEATURE_SPA__) {
		// Will wrap history.pushState/replaceState + popstate.
	}

	// Consent mode: deferred init.
	if (options.consentMode === 'disabled-until-consent') {
		// Don't auto-init. Wait for consent banner signal.
		window.statnive_init = init;
		// Register consent banner listeners (Real Cookie Banner, Complianz, CookieYes).
		registerConsentBannerListeners(init, setConsentGranted);
	} else if (document.readyState === 'complete' || document.readyState === 'interactive') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

})(window, document);
