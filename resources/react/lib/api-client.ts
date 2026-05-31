declare global {
	interface Window {
		StatniveDashboard: {
			restUrl: string;
			nonce: string;
			siteTitle: string;
			version: string;
			currentIp: string;
			/** Initial SPA route, derived server-side from the `?page=` slug. */
			initialRoute?: string;
			/** Store currency code (e.g. 'USD'); falls back to 'USD' when WooCommerce is absent. */
			currency?: string;
			/** Currency minor-unit decimals (2 for USD/EUR, 0 for JPY, 3 for BHD). */
			currencyMinorUnit?: number;
			/** Currency symbol — defensive; React uses Intl.NumberFormat directly. */
			currencySymbol?: string;
			/** WP user locale (e.g. 'en_US', 'de_DE'). Drives `Intl.DateTimeFormat` so the dynamic-window labels match the rest of the page's language. */
			locale?: string;
		};
	}
}

function getConfig() {
	return window.StatniveDashboard;
}

export function getCurrentIp(): string {
	return window.StatniveDashboard?.currentIp ?? '';
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

export async function apiPost<T>(path: string, body: unknown = null): Promise<T> {
	const config = getConfig();
	const url = config.restUrl + path;

	const response = await fetch(url, {
		method: 'POST',
		headers: {
			'X-WP-Nonce': config.nonce,
			'Content-Type': 'application/json',
		},
		body: body === null ? null : JSON.stringify(body),
	});

	if (!response.ok) {
		throw new Error(`API error: ${response.status} ${response.statusText}`);
	}

	return response.json() as Promise<T>;
}
