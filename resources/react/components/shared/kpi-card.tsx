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
			<p className="font-display text-[11px] font-medium uppercase leading-[1.4] tracking-[0.12em] text-muted-foreground">
				{label}
			</p>
			<div className="mt-2.5 flex items-baseline gap-2">
				<span className="font-display text-[34px] font-medium leading-none tracking-[-0.5px] tabular-nums">{value}</span>
				{change !== undefined && (
					<span
						className={cn(
							'rounded-full px-1.5 py-0.5 text-xs font-medium',
							change >= 0
								? 'bg-revenue/10 text-revenue-dark'
								: 'bg-destructive/10 text-destructive',
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
