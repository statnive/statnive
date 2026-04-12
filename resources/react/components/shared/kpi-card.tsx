import { __, sprintf } from '@wordpress/i18n';
import { cn, formatPercentChange } from '@/lib/utils';

interface KpiCardProps {
	label: string;
	value: string;
	change?: number;
	isLoading?: boolean;
}

export function KpiCard({ label, value, change, isLoading = false }: KpiCardProps) {
	if (isLoading) {
		return (
			<div className="rounded-lg border border-border bg-card p-4">
				<div className="mb-2 h-4 w-20 animate-pulse rounded bg-muted" />
				<div className="h-8 w-24 animate-pulse rounded bg-muted" />
			</div>
		);
	}

	return (
		<div className="rounded-lg border border-border bg-card p-4">
			<p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
				{label}
			</p>
			<div className="mt-1 flex items-baseline gap-2">
				<span className="text-3xl font-bold tabular-nums">{value}</span>
				{change !== undefined && (
					<span
						className={cn(
							'rounded-full px-1.5 py-0.5 text-xs font-medium',
							change >= 0
								? 'bg-green-100 text-green-800'
								: 'bg-red-100 text-red-800',
						)}
						aria-label={sprintf(
							/* translators: 1: "up" or "down", 2: percentage change */
							__('Change %1$s %2$s versus previous period', 'statnive'),
							change >= 0 ? __('up', 'statnive') : __('down', 'statnive'),
							formatPercentChange(Math.abs(change)),
						)}
					>
						<span aria-hidden="true">{change >= 0 ? '↑ ' : '↓ '}</span>
						{formatPercentChange(change)}
					</span>
				)}
			</div>
		</div>
	);
}
