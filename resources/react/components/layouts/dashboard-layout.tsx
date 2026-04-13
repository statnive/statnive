import { type ReactNode, useEffect, useMemo } from 'react';
import { Link, useRouter, useRouterState } from '@tanstack/react-router';
import { __ } from '@wordpress/i18n';
import { cn } from '@/lib/utils';
import type { DateRange } from '@/types/api';
import { useDateRange } from '@/hooks/use-date-range';
import { DateRangePicker } from '@/components/shared/date-range-picker';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import { registerWpCommands } from '@/lib/wp-commands';
import {
	BarChart3,
	FileText,
	Share2,
	Globe,
	Monitor,
	Languages,
	Activity,
	Settings,
} from 'lucide-react';

const navItems = [
	{ to: '/', label: 'Overview', icon: BarChart3 },
	{ to: '/pages', label: 'Pages', icon: FileText },
	{ to: '/referrers', label: 'Referrers', icon: Share2 },
	{ to: '/geography', label: 'Geography', icon: Globe },
	{ to: '/devices', label: 'Devices', icon: Monitor },
	{ to: '/languages', label: 'Languages', icon: Languages },
	{ to: '/realtime', label: 'Real-time', icon: Activity },
	{ to: '/settings', label: 'Settings', icon: Settings },
] as const;

interface DashboardLayoutProps {
	children: ReactNode;
}

export function DashboardLayout({ children }: DashboardLayoutProps) {
	const routerState = useRouterState();
	const router = useRouter();
	const currentPath = routerState.location.pathname;
	const siteTitle = window.StatniveDashboard?.siteTitle ?? 'WordPress';

	const { range, setDateRange } = useDateRange();

	const showDatePicker =
		!currentPath.startsWith('/realtime') && !currentPath.startsWith('/settings');

	// Register WP Command Palette commands on mount.
	useEffect(() => {
		registerWpCommands((path) =>
			router.navigate({
				to: path,
				search: (prev) => ({ ...prev }) as { range: DateRange },
			}),
		);
	}, [router]);

	// Keyboard shortcuts: 1-8 for tab navigation.
	const shortcuts = useMemo(
		() =>
			Object.fromEntries(
				navItems.map((item, index) => [
					String(index + 1),
					() =>
						router.navigate({
							to: item.to,
							search: (prev) => ({ ...prev }) as { range: DateRange },
						}),
				]),
			),
		[router],
	);
	useKeyboardShortcuts(shortcuts);

	return (
		<div className="min-h-screen bg-background">
			{/* Skip to content — a11y */}
			<a
				href="#statnive-content"
				className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground"
			>
				{__('Skip to content', 'statnive')}
			</a>

			{/* Header */}
			<header className="border-b border-border bg-card px-4 py-3">
				<div className="mx-auto flex max-w-7xl items-center justify-between">
					<div className="flex items-center gap-2">
						<BarChart3 className="h-5 w-5 text-primary" />
						<span className="text-lg font-semibold tracking-tight">Statnive</span>
						<span className="hidden text-sm text-muted-foreground sm:inline">
							— {siteTitle}
						</span>
					</div>
					{showDatePicker && <DateRangePicker value={range} onChange={setDateRange} />}
				</div>
			</header>

			{/* Navigation */}
			<nav className="border-b border-border bg-card" aria-label={__('Dashboard navigation', 'statnive')}>
				<div className="mx-auto max-w-7xl overflow-x-auto px-4">
					<div className="flex gap-1">
						{navItems.map((item) => {
							const isActive =
								item.to === '/'
									? currentPath === '/' || currentPath === ''
									: currentPath.startsWith(item.to);
							const Icon = item.icon;

							return (
								<Link
									key={item.to}
									to={item.to}
									search={(prev) => ({ ...prev }) as { range: DateRange }}
									className={cn(
										'flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors duration-150',
										isActive
											? 'border-primary text-primary'
											: 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
									)}
									aria-current={isActive ? 'page' : undefined}
								>
									<Icon className="h-4 w-4" />
									{__(item.label, 'statnive')}
								</Link>
							);
						})}
					</div>
				</div>
			</nav>

			{/* Content */}
			<main id="statnive-content" className="mx-auto max-w-7xl px-4 py-6">
				{children}
			</main>
		</div>
	);
}
