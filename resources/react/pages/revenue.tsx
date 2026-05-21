import { useEffect, useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useQueryClient } from '@tanstack/react-query';
import { ShoppingCart, ExternalLink } from 'lucide-react';
import { KpiCard } from '@/components/shared/kpi-card';
import { DataTable, type Column } from '@/components/shared/data-table';
import { useDateRange } from '@/hooks/use-date-range';
import {
	useRevenueSummary,
	useRevenueByChannel,
	useTopProducts,
	useFunnel,
} from '@/hooks/use-revenue';
import { useBackfillStatus, useBackfillTrigger } from '@/hooks/use-backfill';
import { BackfillProgress } from '@/components/revenue/backfill-progress';
import { HEADING_H2, HEADING_H3 } from '@/lib/typography';
import { formatNumber } from '@/lib/utils';
import { formatMoney, formatMoneyPrecise, formatPercent } from '@/lib/revenue-format';
import type { RevenueChannelRow, RevenueProductRow, RevenueFunnelStep } from '@/types/revenue';

/**
 * Statnive — Revenue Report page (v1.0.0).
 *
 * Free WooCommerce revenue dashboard. Reads from /statnive/v1/revenue/*.
 * Empty states cover WC-absent and zero-orders cases. Full 9-section
 * mockup port (sparklines, refund-rate trend chart, etc.) lands as
 * v1.x follow-up — this PR ships a functional core: KPIs, by-channel,
 * top products, funnel.
 */
export function RevenuePage() {
	const { params } = useDateRange();
	const { from, to } = params;
	const queryClient = useQueryClient();

	const { wcStatus, state: backfillState } = useBackfillStatus();
	const backfillTrigger = useBackfillTrigger();
	const summary = useRevenueSummary(from, to);
	const channels = useRevenueByChannel(from, to);
	const products = useTopProducts(from, to, 10);
	const funnel = useFunnel(from, to);

	const inFlight = backfillState?.status === 'pending' || backfillState?.status === 'running';

	// While a backfill is running, refetch the revenue queries every 10s
	// so the user sees partial data filling in. No work at all when idle.
	useEffect(() => {
		if (!inFlight) return undefined;
		const id = window.setInterval(() => {
			void queryClient.invalidateQueries({ queryKey: ['revenue', 'summary'] });
			void queryClient.invalidateQueries({ queryKey: ['revenue', 'by-channel'] });
			void queryClient.invalidateQueries({ queryKey: ['revenue', 'products'] });
			void queryClient.invalidateQueries({ queryKey: ['revenue', 'funnel'] });
		}, 10000);
		return () => window.clearInterval(id);
	}, [inFlight, queryClient]);

	// WooCommerce not installed — full-page empty state.
	if (wcStatus && !wcStatus.woocommerce_active) {
		return <NoWooCommerceState />;
	}

	// During backfill, suppress the bare "no orders" state — data is
	// actively being imported. Show the progress strip instead so the
	// user understands why KPIs are thin.
	const hasGap = wcStatus?.backfill?.has_gap ?? false;
	const noOrders = summary.isSuccess && summary.data?.data?.orders === 0 && !inFlight && !hasGap;

	return (
		<div className="space-y-5">
			<header>
				<h1 className={HEADING_H2}>{__('Revenue Report', 'statnive')}</h1>
				<p className="mt-1 text-sm text-muted-foreground">
					{__(
						'WooCommerce revenue, channels, products, funnel — net of refunds, tax and shipping excluded.',
						'statnive'
					)}
				</p>
			</header>

			<BackfillProgress
				state={backfillState}
				onRetry={() => backfillTrigger.mutate()}
				retryDisabled={backfillTrigger.isPending}
			/>

			<KpiStrip summary={summary.data?.data} isLoading={summary.isLoading} />

			{noOrders ? (
				<NoOrdersInPeriodState />
			) : (
				<>
					<ChannelTable rows={channels.data?.data ?? []} isLoading={channels.isLoading} />
					<TopProductsCard rows={products.data?.data ?? []} isLoading={products.isLoading} />
					<FunnelCard data={funnel.data?.data} isLoading={funnel.isLoading} />
				</>
			)}

			<footer className="py-4 text-center text-xs text-muted-foreground">
				{__(
					'Net revenue excludes tax and shipping; refunds applied. Counts orders in Processing or Completed.',
					'statnive'
				)}
			</footer>
		</div>
	);
}

// --- Section components ------------------------------------------------------

interface KpiStripProps {
	summary?: import('@/types/revenue').RevenueSummary;
	isLoading: boolean;
}

function KpiStrip({ summary, isLoading }: KpiStripProps) {
	if (isLoading || !summary) {
		return (
			<div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
				{[0, 1, 2, 3, 4].map((i) => (
					<KpiCard key={i} label="" value="" isLoading />
				))}
			</div>
		);
	}
	return (
		<div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
			<KpiCard
				label={__('Revenue (net)', 'statnive')}
				value={formatMoney(summary.net_revenue)}
				helper={sprintf(__('Gross: %s', 'statnive'), formatMoney(summary.gross_revenue))}
			/>
			<KpiCard
				label={__('Orders', 'statnive')}
				value={formatNumber(summary.orders)}
			/>
			<KpiCard
				label={__('Average Order Value', 'statnive')}
				value={formatMoneyPrecise(summary.aov)}
			/>
			<KpiCard
				label={__('Refund total', 'statnive')}
				value={formatMoney(summary.refund_total)}
				helper={sprintf(__('Rate: %s', 'statnive'), formatPercent(summary.refund_rate))}
			/>
			<KpiCard
				label={__('Tax + Shipping', 'statnive')}
				value={formatMoney(summary.tax_total + summary.shipping_total)}
				helper={__('Excluded from revenue', 'statnive')}
			/>
		</div>
	);
}

interface ChannelTableProps {
	rows: RevenueChannelRow[];
	isLoading: boolean;
}

function ChannelTable({ rows, isLoading }: ChannelTableProps) {
	const columns: Column<RevenueChannelRow>[] = useMemo(
		() => [
			{
				key: 'channel',
				header: __('Channel', 'statnive'),
				render: (r) => <span className="font-medium">{r.channel}</span>,
			},
			{
				key: 'orders',
				header: __('Orders', 'statnive'),
				sortable: true,
				render: (r) => formatNumber(r.orders),
			},
			{
				key: 'revenue',
				header: __('Revenue', 'statnive'),
				sortable: true,
				render: (r) => <span className="font-medium">{formatMoney(r.revenue)}</span>,
			},
			{
				key: 'aov',
				header: __('AOV', 'statnive'),
				sortable: true,
				render: (r) => formatMoneyPrecise(r.aov),
			},
		],
		[]
	);
	return (
		<section className="rounded-lg border border-border bg-card">
			<header className="border-b border-border px-5 py-4">
				<h2 className={`${HEADING_H3} text-foreground`}>
					{__('Revenue by channel', 'statnive')}
				</h2>
			</header>
			<div className="p-5">
				<DataTable<RevenueChannelRow>
					data={rows}
					columns={columns}
					isLoading={isLoading}
					emptyMessage={__('No attributed channels in this period.', 'statnive')}
					getRowKey={(r) => r.channel}
				/>
			</div>
		</section>
	);
}

interface TopProductsCardProps {
	rows: RevenueProductRow[];
	isLoading: boolean;
}

function TopProductsCard({ rows, isLoading }: TopProductsCardProps) {
	const totalRevenue = useMemo(
		() => rows.reduce((acc, r) => acc + r.revenue, 0),
		[rows]
	);
	return (
		<section className="rounded-lg border border-border bg-card">
			<header className="border-b border-border px-5 py-4">
				<h2 className={`${HEADING_H3} text-foreground`}>
					{__('Top products', 'statnive')}
				</h2>
			</header>
			<div className="p-5">
				{isLoading ? (
					<ul className="divide-y divide-border -mx-5">
						{[0, 1, 2, 3, 4].map((i) => (
							<li key={i} className="flex items-center gap-4 px-5 py-3">
								<div className="h-10 w-10 shrink-0 animate-pulse rounded-md bg-muted" />
								<div className="flex-1 space-y-2">
									<div className="h-3 w-2/3 animate-pulse rounded bg-muted" />
									<div className="h-3 w-1/3 animate-pulse rounded bg-muted" />
								</div>
							</li>
						))}
					</ul>
				) : rows.length === 0 ? (
					<p className="py-6 text-center text-sm text-muted-foreground">
						{__('No product sales in this period.', 'statnive')}
					</p>
				) : (
					<ul className="-mx-5 divide-y divide-border">
						{rows.map((p) => {
							const share = totalRevenue > 0 ? (p.revenue / totalRevenue) * 100 : 0;
							const editUrl = `/wp-admin/post.php?post=${p.product_id}&action=edit`;
							return (
								<li key={p.product_id} className="flex items-center gap-4 px-5 py-3 hover:bg-muted/40">
									<div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
										<ShoppingCart size={16} aria-hidden="true" />
									</div>
									<div className="min-w-0 flex-1">
										<a
											href={editUrl}
											className="inline-flex items-center gap-1 truncate text-sm font-medium text-foreground hover:text-revenue-dark"
										>
											{p.product_name || __('(unnamed product)', 'statnive')}
											<ExternalLink size={11} aria-hidden="true" />
										</a>
										<p className="text-xs text-muted-foreground tabular-nums">
											{sprintf(
												/* translators: 1: units sold, 2: percent of revenue */
												__('%1$s units · %2$s of period revenue', 'statnive'),
												formatNumber(p.units),
												`${share.toFixed(1)}%`
											)}
										</p>
									</div>
									<div className="text-right">
										<p className="text-sm font-medium tabular-nums">{formatMoney(p.revenue)}</p>
									</div>
								</li>
							);
						})}
					</ul>
				)}
			</div>
		</section>
	);
}

interface FunnelCardProps {
	data?: import('@/types/revenue').RevenueFunnelResponse;
	isLoading: boolean;
}

const STEP_LABELS: Record<string, string> = {
	wc_product_view: 'Viewed product',
	wc_add_to_cart: 'Added to cart',
	wc_checkout_start: 'Started checkout',
	wc_purchase: 'Completed purchase',
};

function FunnelCard({ data, isLoading }: FunnelCardProps) {
	const steps = data?.steps ?? [];
	// Anchor every bar to the LARGEST step (not just step 0) so the funnel
	// still renders sensibly when the tracker hasn't captured product-view
	// events yet but orders ARE present from the backfill — in that case
	// "Completed purchase" is the max, gets 100% width, and the zero-event
	// rows render as the full funnel drop (0% width). When step 0 is the
	// max (healthy funnel) the layout is identical to before.
	const max = Math.max( ...steps.map( ( s ) => s.sessions ), 1 );
	return (
		<section className="rounded-lg border border-border bg-card">
			<header className="flex items-baseline justify-between border-b border-border px-5 py-4">
				<h2 className={`${HEADING_H3} text-foreground`}>
					{__('Cart to purchase funnel', 'statnive')}
				</h2>
				{data && (
					<span className="text-sm text-muted-foreground tabular-nums">
						{sprintf(
							__('Overall: %s', 'statnive'),
							data.overall_conversion === null
								? '—'
								: `${(data.overall_conversion * 100).toFixed(2)}%`
						)}
					</span>
				)}
			</header>
			<div className="p-5">
				{isLoading || !data ? (
					<div className="space-y-2.5">
						{[0, 1, 2, 3].map((i) => (
							<div key={i} className="h-9 animate-pulse rounded bg-muted" />
						))}
					</div>
				) : (
					<ul className="space-y-2.5">
						{steps.map((s: RevenueFunnelStep, i: number) => {
							const width = (s.sessions / max) * 100;
							const prev = i === 0 ? null : ( steps[i - 1]?.sessions ?? null );
							// Per-step CONVERSION rate (current ÷ previous).
							// Step 0 has no previous step → null → renders as "—".
							// prev = 0 (no source population) → null → "—".
							// > 100% is possible when the orders count exceeds the
							// upstream event count (events not captured yet). We
							// surface that honestly rather than clamping.
							const conv =
								prev !== null && prev > 0 ? (s.sessions / prev) * 100 : null;
							const isLast = i === steps.length - 1;
							return (
								<li key={s.step} className="grid grid-cols-[160px_1fr_4rem] items-center gap-3">
									<span className="text-sm text-foreground">
										{__(STEP_LABELS[s.step] ?? s.step, 'statnive')}
									</span>
									<div className="relative h-9 overflow-hidden rounded bg-muted">
										<div
											className="flex h-full items-center rounded px-3 text-sm font-medium text-primary-foreground transition-all"
											style={{
												width: `${width}%`,
												background: isLast ? 'var(--color-revenue, #00A693)' : 'var(--color-primary, #0A2540)',
											}}
										>
											<span className="tabular-nums">{formatNumber(s.sessions)}</span>
										</div>
									</div>
									<span
										className="w-16 text-right text-xs tabular-nums text-muted-foreground"
										title={
											conv === null || i === 0
												? undefined
												: sprintf(
													/* translators: 1: current step label, 2: previous step label */
													__('Conversion from %2$s → %1$s', 'statnive'),
													__(STEP_LABELS[s.step] ?? s.step, 'statnive'),
													__(STEP_LABELS[steps[i - 1]?.step ?? ''] ?? steps[i - 1]?.step ?? '', 'statnive')
												)
										}
									>
										{conv !== null ? `${conv.toFixed(0)}%` : '—'}
									</span>
								</li>
							);
						})}
					</ul>
				)}
			</div>
		</section>
	);
}

// --- Empty states ------------------------------------------------------------

function NoWooCommerceState() {
	return (
		<div className="mx-auto flex max-w-2xl flex-col items-center rounded-lg border border-border bg-card p-10 text-center">
			<div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted text-muted-foreground">
				<ShoppingCart size={28} aria-hidden="true" />
			</div>
			<h2 className={`${HEADING_H3} mb-2`}>
				{__('Install WooCommerce to see your revenue', 'statnive')}
			</h2>
			<p className="mb-6 max-w-lg text-sm text-muted-foreground">
				{__(
					'The Revenue Report becomes available the moment WooCommerce is active. Statnive auto-detects it and starts attributing orders on the next checkout — no setup needed.',
					'statnive'
				)}
			</p>
			<a
				href="/wp-admin/plugin-install.php?s=woocommerce&tab=search&type=term"
				className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
			>
				{__('Install WooCommerce', 'statnive')}
			</a>
		</div>
	);
}

function NoOrdersInPeriodState() {
	return (
		<div className="mx-auto max-w-2xl rounded-lg border border-border bg-card p-8 text-center">
			<h2 className={`${HEADING_H3} mb-2`}>{__('No orders in this period yet', 'statnive')}</h2>
			<p className="text-sm text-muted-foreground">
				{__(
					"As soon as a customer completes checkout, you'll see revenue, AOV, channel attribution, and product breakdowns here.",
					'statnive'
				)}
			</p>
		</div>
	);
}
