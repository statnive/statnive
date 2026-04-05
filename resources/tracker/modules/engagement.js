/**
 * Engagement tracking module — scroll depth + time-on-page via Visibility API.
 *
 * No heartbeats — event-driven measurement only.
 * Scroll depth in 5% increments. Time via visibilitychange (excludes hidden tabs).
 *
 * @returns {{getMetrics: Function, start: Function, stop: Function}}
 */
export function createEngagementTracker() {
	var engagementStart = 0;
	var engagementTime = 0;
	var maxScrollDepth = 0;
	var running = false;

	function onVisibilityChange() {
		if (document.hidden) {
			if (running && engagementStart > 0) {
				engagementTime += Date.now() - engagementStart;
				engagementStart = 0;
			}
		} else {
			if (running) {
				engagementStart = Date.now();
			}
		}
	}

	function onScroll() {
		if (!running) return;
		var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
		var docHeight = Math.max(
			document.documentElement.scrollHeight,
			document.body.scrollHeight
		) - window.innerHeight;

		if (docHeight <= 0) return;

		var pct = Math.floor((scrollTop / docHeight) * 100);
		pct = Math.floor(pct / 5) * 5; // Round to nearest 5%.
		if (pct > maxScrollDepth) {
			maxScrollDepth = Math.min(pct, 100);
		}
	}

	return {
		start: function() {
			running = true;
			engagementStart = Date.now();
			engagementTime = 0;
			maxScrollDepth = 0;
			document.addEventListener('visibilitychange', onVisibilityChange);
			window.addEventListener('scroll', onScroll, { passive: true });
			onScroll(); // Initial measurement.
		},
		stop: function() {
			if (running && engagementStart > 0) {
				engagementTime += Date.now() - engagementStart;
			}
			running = false;
			document.removeEventListener('visibilitychange', onVisibilityChange);
			window.removeEventListener('scroll', onScroll);
		},
		getMetrics: function() {
			var totalTime = engagementTime;
			if (running && engagementStart > 0) {
				totalTime += Date.now() - engagementStart;
			}
			return {
				engagement_time: Math.round(totalTime / 1000),
				scroll_depth: maxScrollDepth
			};
		}
	};
}
