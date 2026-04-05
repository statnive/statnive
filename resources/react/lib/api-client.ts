declare global {
	interface Window {
		StatniveDashboard: {
			restUrl: string;
			nonce: string;
			siteTitle: string;
			version: string;
		};
	}
}

function getConfig() {
	return window.StatniveDashboard;
}

export async function apiGet<T>(path: string, params?: Record<string, string>): Promise<T> {
	const config = getConfig();
	const url = new URL(config.restUrl + path, window.location.origin);

	if (params) {
		for (const [key, value] of Object.entries(params)) {
			url.searchParams.set(key, value);
		}
	}

	const response = await fetch(url.toString(), {
		headers: {
			'X-WP-Nonce': config.nonce,
		},
	});

	if (!response.ok) {
		throw new Error(`API error: ${response.status} ${response.statusText}`);
	}

	return response.json() as Promise<T>;
}

export async function apiPut<T>(path: string, body: unknown): Promise<T> {
	const config = getConfig();
	const url = config.restUrl + path;

	const response = await fetch(url, {
		method: 'PUT',
		headers: {
			'X-WP-Nonce': config.nonce,
			'Content-Type': 'application/json',
		},
		body: JSON.stringify(body),
	});

	if (!response.ok) {
		throw new Error(`API error: ${response.status} ${response.statusText}`);
	}

	return response.json() as Promise<T>;
}
