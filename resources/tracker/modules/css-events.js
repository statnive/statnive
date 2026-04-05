/**
 * CSS class-based event tagging module.
 *
 * Detects clicks on elements with class patterns like:
 *   statnive-event-name=Signup
 *   statnive-event-plan--pro  (alternative separator for page builders)
 *
 * @param {Function} sendEvent - Function to fire a custom event.
 */
export function registerCssEventTracking(sendEvent) {
	var pattern = /statnive-event-(.+?)(--|=)(.+)/;

	document.addEventListener('click', function(e) {
		var el = e.target;
		var props = {};
		var eventName = null;

		// Walk up the DOM tree to find event classes.
		while (el && el !== document.body) {
			if (el.className && typeof el.className === 'string') {
				var classes = el.className.split(/\s+/);
				for (var i = 0; i < classes.length; i++) {
					var match = classes[i].match(pattern);
					if (match) {
						var key = match[1];
						var value = match[3];
						if (key === 'name') {
							eventName = value;
						} else {
							props[key] = value;
						}
					}
				}
			}
			el = el.parentElement;
		}

		if (eventName) {
			sendEvent(eventName, props);
		}
	}, true);
}
