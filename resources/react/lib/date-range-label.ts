import { __, sprintf } from '@wordpress/i18n';
import type { DateRange } from '@/types/api';

/**
 * Localised window phrases for the dynamic-window Advisor questions.
 *
 * Two flavours:
 * - {@link formatDateRangeLabel} — current-window phrase ("today",
 *   "in the last 30 days", "this month", or a custom date-range string).
 * - {@link formatPriorRangeLabel} — comparison-window phrase
 *   ("with yesterday", "with the previous 30 days", "with last month").
 *
 * For the `custom` preset (no UI yet — type-level only) we fall back to
 * `Intl.DateTimeFormat` with the user's WP locale so the rendered range
 * matches the rest of the page's language. The locale comes from
 * `window.StatniveDashboard.locale` which `ReactHandler` populates from
 * `determine_locale()` server-side.
 */

interface RangeParams {
	from: string;
	to: string;
}

function browserLocale(): string {
	const wp = typeof window !== 'undefined' ? window.StatniveDashboard?.locale : undefined;
	return (wp ?? 'en_US').replace('_', '-');
}

function formatIsoDate(iso: string): string {
	const d = new Date(`${iso}T00:00:00Z`);
	return new Intl.DateTimeFormat(browserLocale(), {
		day: 'numeric',
		month: 'short',
		year: 'numeric',
		timeZone: 'UTC',
	}).format(d);
}

function rangeDays(params: RangeParams): number {
	const ms = new Date(`${params.to}T00:00:00Z`).getTime() - new Date(`${params.from}T00:00:00Z`).getTime();
	return Math.round(ms / 86_400_000) + 1;
}

export function formatDateRangeLabel(range: DateRange, params: RangeParams): string {
	switch (range) {
		case 'today':
			return __('today', 'statnive');
		case '7d':
			return __('in the last 7 days', 'statnive');
		case '30d':
			return __('in the last 30 days', 'statnive');
		case 'this-month':
			return __('this month', 'statnive');
		case 'last-month':
			return __('last month', 'statnive');
		case 'custom':
			return `${formatIsoDate(params.from)} – ${formatIsoDate(params.to)}`;
	}
}

export function formatPriorRangeLabel(range: DateRange, params: RangeParams): string {
	switch (range) {
		case 'today':
			return __('with yesterday', 'statnive');
		case '7d':
			return __('with the previous 7 days', 'statnive');
		case '30d':
			return __('with the previous 30 days', 'statnive');
		case 'this-month':
			return __('with last month', 'statnive');
		case 'last-month':
			return __('with the month before', 'statnive');
		case 'custom':
			return sprintf(
				/* translators: %d is the day-count of the user-picked custom date range */
				__('with the previous %d days', 'statnive'),
				rangeDays(params),
			);
	}
}
