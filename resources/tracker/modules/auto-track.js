/**
 * Auto-tracking module — outbound links, file downloads, form submissions.
 *
 * @param {Function} sendEvent - Function to fire a custom event.
 */
export function registerAutoTracking(sendEvent) {
	var fileExtensions = /\.(pdf|zip|xlsx?|docx?|pptx?|rar|7z|csv|mp3|mp4|wav|avi|mov|dmg|exe|msi|apk|iso)$/i;
	var currentHost = window.location.hostname;

	// Delegated click handler for links.
	document.addEventListener('click', function(e) {
		var el = e.target;
		while (el && el.tagName !== 'A') {
			el = el.parentElement;
		}
		if (!el || !el.href) return;

		var href = el.href;

		// File download detection.
		var match = href.match(fileExtensions);
		if (match) {
			sendEvent('File_Download', { url: href, type: match[1].toLowerCase() });
			return;
		}

		// Outbound link detection.
		try {
			var linkHost = new URL(href).hostname;
			if (linkHost && linkHost !== currentHost) {
				sendEvent('Outbound_Link', { url: href, domain: linkHost });
			}
		} catch (err) {
			// Invalid URL, ignore.
		}
	}, true);

	// Delegated form submission handler.
	document.addEventListener('submit', function(e) {
		var form = e.target;
		if (!form || form.tagName !== 'FORM') return;

		sendEvent('Form_Submit', {
			id: form.id || '',
			action: (form.action || '').split('?')[0]
		});
	}, true);
}
