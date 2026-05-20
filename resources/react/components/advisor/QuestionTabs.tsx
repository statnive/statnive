import { useRef, useCallback, useEffect, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Pin,
	BarChart3,
	Activity,
	FileText,
	Share2,
	Send,
	Globe,
	Monitor,
	Gauge,
	DollarSign,
	Zap,
	type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AdvisorCategory } from '@/types/api';

/**
 * In-page tab strip for the Ask me! page.
 *
 * 11 tabs: a first "Ask me!" pinned-tab (separated by a vertical divider)
 * + the 10 ordered categories. ARIA tablist semantics, ←/→ + Home / End
 * keyboard cycling, single-row with overflow-x scroll on narrow viewports.
 *
 * Design refinements per plan §A:
 *   - Pinned tab leftmost in LTR, rightmost in RTL (rtl:flex-row-reverse).
 *   - Active state: border-primary + text-primary, matches the dashboard
 *     top-nav button-group convention from dashboard-layout.tsx.
 *   - Long category labels collapse to short labels — full names in
 *     `aria-label`.
 */
const PINNED_TAB_ID = '__pinned__';

const SHORT_LABEL: Record<string, string> = {
	traffic_overview: 'Traffic',
	real_time_tracking_health: 'Real-time',
	pages_and_content: 'Pages',
	referrers_and_channels: 'Referrers',
	campaigns_and_utm: 'Campaigns',
	geography_and_language: 'Geography',
	devices_and_browsers: 'Devices',
	engagement_and_quality: 'Engagement',
	revenue: 'Revenue',
	events_and_privacy: 'Events',
};

const CATEGORY_ICON: Record<string, LucideIcon> = {
	traffic_overview: BarChart3,
	real_time_tracking_health: Activity,
	pages_and_content: FileText,
	referrers_and_channels: Share2,
	campaigns_and_utm: Send,
	geography_and_language: Globe,
	devices_and_browsers: Monitor,
	engagement_and_quality: Gauge,
	revenue: DollarSign,
	events_and_privacy: Zap,
};

function shortLabelFor(category: AdvisorCategory): string {
	return SHORT_LABEL[category.id] ?? category.label;
}

function iconFor(category: AdvisorCategory): LucideIcon {
	return CATEGORY_ICON[category.id] ?? BarChart3;
}

interface QuestionTabsProps {
	categories: AdvisorCategory[];
	active: string;
	onChange: (next: string) => void;
	children: ReactNode;
}

export function QuestionTabs({ categories, active, onChange, children }: QuestionTabsProps) {
	const listRef = useRef<HTMLDivElement | null>(null);

	// Keyboard navigation: ←/→ cycle, Home / End jump.
	const onKeyDown = useCallback(
		(e: React.KeyboardEvent) => {
			const order = [PINNED_TAB_ID, ...categories.map((c) => c.id)];
			const idx = order.indexOf(active);
			if (idx < 0) return;
			const goTo = (i: number) => {
				const next = order[i];
				if (next !== undefined) onChange(next);
			};
			if (e.key === 'ArrowRight') {
				e.preventDefault();
				if (idx < order.length - 1) goTo(idx + 1);
			} else if (e.key === 'ArrowLeft') {
				e.preventDefault();
				if (idx > 0) goTo(idx - 1);
			} else if (e.key === 'Home') {
				e.preventDefault();
				goTo(0);
			} else if (e.key === 'End') {
				e.preventDefault();
				goTo(order.length - 1);
			}
		},
		[active, categories, onChange],
	);

	// Auto-scroll the active tab into view when it changes (overflow-x).
	useEffect(() => {
		if (!listRef.current) return;
		const el = listRef.current.querySelector<HTMLElement>(`[data-tab-id="${active}"]`);
		el?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
	}, [active]);

	const activePanelId = `statnive-advisor-panel-${active}`;

	return (
		<>
			<nav
				className="border-b border-border bg-card"
				aria-label={__('Ask me! categories', 'statnive')}
			>
				<div
					ref={listRef}
					role="tablist"
					aria-orientation="horizontal"
					onKeyDown={onKeyDown}
					className="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 rtl:flex-row-reverse"
				>
					<button
						type="button"
						role="tab"
						aria-selected={active === PINNED_TAB_ID}
						aria-controls={activePanelId}
						data-tab-id={PINNED_TAB_ID}
						tabIndex={active === PINNED_TAB_ID ? 0 : -1}
						onClick={() => onChange(PINNED_TAB_ID)}
						className={cn(
							'flex items-center gap-1.5 whitespace-nowrap border-b-[3px] px-3 py-2.5 text-sm font-medium transition-colors duration-150',
							active === PINNED_TAB_ID
								? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)]/5 text-primary'
								: 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
						)}
					>
						<Pin
							className={cn(
								'h-4 w-4',
								active === PINNED_TAB_ID && 'text-[color:var(--color-accent)]',
							)}
						/>
						{__('Ask me!', 'statnive')}
					</button>

					{categories.map((category) => {
						const isActive = active === category.id;
						const Icon = iconFor(category);
						return (
							<button
								key={category.id}
								type="button"
								role="tab"
								aria-selected={isActive}
								aria-controls={activePanelId}
								aria-label={category.label}
								data-tab-id={category.id}
								tabIndex={isActive ? 0 : -1}
								onClick={() => onChange(category.id)}
								className={cn(
									'flex items-center gap-1.5 whitespace-nowrap border-b-[3px] px-3 py-2.5 text-sm font-medium transition-colors duration-150',
									isActive
										? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)]/5 text-primary'
										: 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
								)}
							>
								<Icon
									className={cn(
										'h-4 w-4',
										isActive && 'text-[color:var(--color-accent)]',
									)}
									aria-hidden="true"
								/>
								{shortLabelFor(category)}
							</button>
						);
					})}
				</div>
			</nav>

			<div
				role="tabpanel"
				id={activePanelId}
				aria-labelledby={`statnive-advisor-tab-${active}`}
				tabIndex={0}
				className="mx-auto max-w-7xl px-4 py-6"
			>
				{children}
			</div>
		</>
	);
}

export { PINNED_TAB_ID };
