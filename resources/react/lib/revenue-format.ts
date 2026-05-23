/**
 * Currency / percent / number formatters for the Revenue Report.
 *
 * Reads currency code + minor unit once from `window.StatniveDashboard`
 * (PHP wires this via `wp_localize_script` in PR 2). Falls back to USD/2
 * when WC isn't installed.
 */

function storeCurrencyCode(): string {
	return window.StatniveDashboard?.currency ?? 'USD';
}

function storeCurrencyMinorUnit(): number {
	return window.StatniveDashboard?.currencyMinorUnit ?? 2;
}

function browserLocale(): string {
	return typeof navigator !== 'undefined' && navigator.language ? navigator.language : 'en-US';
}

export function formatMoney(value: number): string {
	return new Intl.NumberFormat(browserLocale(), {
		style: 'currency',
		currency: storeCurrencyCode(),
		maximumFractionDigits: 0,
	}).format(value);
}

export function formatMoneyPrecise(value: number): string {
	return new Intl.NumberFormat(browserLocale(), {
		style: 'currency',
		currency: storeCurrencyCode(),
		maximumFractionDigits: storeCurrencyMinorUnit(),
	}).format(value);
}

export function formatPercent(value: number, digits = 1): string {
	return `${(value * 100).toFixed(digits)}%`;
}

export function formatPercentRaw(value: number, digits = 1): string {
	return `${value.toFixed(digits)}%`;
}
