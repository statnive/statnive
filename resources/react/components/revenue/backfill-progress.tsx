/**
 * Inline backfill progress strip shown above the Revenue Report KPIs.
 *
 * Pure-presentational — reads state, renders the right variant, never
 * fetches. The hook that drives it lives in use-backfill.ts.
 *
 * Variants:
 *   - pending  → "Starting up…" + spinner
 *   - running  → "Imported 4,200 / 12,000 (35%). Data is updating live."
 *                + progress bar
 *   - failed   → "Import didn't finish: <last_error>." + Retry button
 *   - done / idle / no-gap → renders nothing
 */
import { __ } from '@wordpress/i18n';
import { Loader2, AlertTriangle, RefreshCw } from 'lucide-react';
import type { BackfillState } from '@/types/revenue';

interface BackfillProgressProps {
	state: BackfillState | undefined;
	onRetry: () => void;
	retryDisabled: boolean;
}

export function BackfillProgress({ state, onRetry, retryDisabled }: BackfillProgressProps) {
	if (!state) {
		return null;
	}
	const status = state.status;

	if (status === 'pending') {
		return (
			<div
				role="status"
				className="mb-4 flex items-center gap-3 rounded-md border border-border bg-card px-4 py-3 text-sm"
			>
				<Loader2 className="h-4 w-4 animate-spin text-muted-foreground" aria-hidden="true" />
				<span>{__('Statnive is starting to import your existing WooCommerce orders…', 'statnive')}</span>
			</div>
		);
	}

	if (status === 'running') {
		const total = Math.max(0, state.total);
		const processed = Math.max(0, state.processed);
		const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		return (
			<div
				role="status"
				aria-live="polite"
				className="mb-4 rounded-md border border-border bg-card p-4"
			>
				<div className="mb-2 flex items-center gap-2 text-sm font-medium">
					<Loader2 className="h-4 w-4 animate-spin text-muted-foreground" aria-hidden="true" />
					<span>
						{total > 0
							? // translators: 1: processed count, 2: total count, 3: percent done
								__('Imported %1$s of %2$s orders (%3$d%%). Data updates live as the import runs.', 'statnive')
									.replace('%1$s', new Intl.NumberFormat().format(processed))
									.replace('%2$s', new Intl.NumberFormat().format(total))
									.replace('%3$d', String(percent))
							: __('Importing your existing WooCommerce orders…', 'statnive')}
					</span>
				</div>
				<div
					className="h-1.5 w-full overflow-hidden rounded bg-muted"
					role="progressbar"
					aria-valuenow={percent}
					aria-valuemin={0}
					aria-valuemax={100}
				>
					<div
						className="h-full bg-primary transition-all"
						style={{ width: `${percent}%` }}
					/>
				</div>
			</div>
		);
	}

	if (status === 'failed') {
		return (
			<div
				role="alert"
				className="mb-4 rounded-md border border-destructive bg-destructive/5 p-4 text-sm"
			>
				<div className="mb-2 flex items-center gap-2 font-medium text-destructive">
					<AlertTriangle className="h-4 w-4" aria-hidden="true" />
					<span>{__("Statnive couldn't finish importing your WooCommerce orders.", 'statnive')}</span>
				</div>
				{state.last_error ? (
					<p className="mb-2 break-words font-mono text-xs text-muted-foreground">
						{state.last_error}
					</p>
				) : null}
				<button
					type="button"
					onClick={onRetry}
					disabled={retryDisabled}
					className="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
				>
					<RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />
					{__('Try again', 'statnive')}
				</button>
			</div>
		);
	}

	return null;
}
