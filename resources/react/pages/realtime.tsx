import { __, sprintf } from '@wordpress/i18n';
import { useRealtime } from '@/hooks/use-realtime';
import { formatNumber } from '@/lib/utils';
import { RealtimeCounter } from '@/components/shared/realtime-counter';

export function RealtimePage() {
	const { data, isLoading } = useRealtime();

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">{__('Real-time', 'statnive')}</h2>

			{/* Hero Counter */}
			<div className="flex flex-col items-center justify-center rounded-lg border border-border bg-card py-12">
				<div className="mb-4 flex items-center gap-3">
					<span className="relative flex h-4 w-4">
						<span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75 motion-reduce:animate-none" />
						<span className="relative inline-flex h-4 w-4 rounded-full bg-green-500" />
					</span>
					<span className="text-sm font-medium uppercase tracking-wider text-muted-foreground">
						{__('Active Visitors', 'statnive')}
					</span>
				</div>
				<span
					className="text-7xl font-bold tabular-nums"
					aria-live="polite"
					aria-label={sprintf(
						/* translators: %s: number of active visitors */
						__('%s active visitors', 'statnive'),
						String(data?.active_visitors ?? 0),
					)}
				>
					{isLoading ? '—' : formatNumber(data?.active_visitors ?? 0)}
				</span>
			</div>

			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				{/* Active Pages */}
				<div className="rounded-lg border border-border bg-card p-4">
					<h3 className="mb-3 text-sm font-medium text-muted-foreground">{__('Active Pages', 'statnive')}</h3>
					{isLoading ? (
						<div className="space-y-2">
							{Array.from({ length: 3 }).map((_, i) => (
								<div key={i} className="h-8 animate-pulse rounded bg-muted" />
							))}
						</div>
					) : (
						<ul className="space-y-2">
							{(data?.active_pages ?? []).map((page) => (
								<li
									key={page.uri}
									className="flex items-center justify-between rounded px-2 py-1.5 text-sm hover:bg-muted/50"
								>
									<span className="truncate font-medium">{page.uri}</span>
									<RealtimeCounter count={page.visitors} />
								</li>
							))}
							{(data?.active_pages ?? []).length === 0 && (
								<li className="py-4 text-center text-muted-foreground">{__('No active pages', 'statnive')}</li>
							)}
						</ul>
					)}
				</div>

				{/* Recent Feed */}
				<div className="rounded-lg border border-border bg-card p-4">
					<h3 className="mb-3 text-sm font-medium text-muted-foreground">{__('Recent Pageviews', 'statnive')}</h3>
					{isLoading ? (
						<div className="space-y-2">
							{Array.from({ length: 5 }).map((_, i) => (
								<div key={i} className="h-8 animate-pulse rounded bg-muted" />
							))}
						</div>
					) : (
						<ul className="space-y-1">
							{(data?.recent_feed ?? []).map((item, i) => (
								<li
									key={`${item.uri}-${i}`}
									className="flex items-center gap-3 rounded px-2 py-1.5 text-sm hover:bg-muted/50"
								>
									<span className="w-16 shrink-0 text-xs text-muted-foreground">{item.time}</span>
									<span className="truncate font-medium">{item.uri}</span>
									<span className="shrink-0 text-xs text-muted-foreground">{item.country}</span>
									<span className="shrink-0 text-xs text-muted-foreground">{item.browser}</span>
								</li>
							))}
							{(data?.recent_feed ?? []).length === 0 && (
								<li className="py-4 text-center text-muted-foreground">{__('No recent activity', 'statnive')}</li>
							)}
						</ul>
					)}
				</div>
			</div>
		</div>
	);
}
