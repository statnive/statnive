// Generated from BDD scenarios — Feature: Dashboard Overview — Accessibility (REQ-1.12)

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Mocks — DashboardLayout uses TanStack Router and keyboard shortcuts
// ---------------------------------------------------------------------------

vi.mock('@tanstack/react-router', () => ({
	Link: ({ to, children, ...props }: { to: string; children: React.ReactNode; className?: string; 'aria-current'?: string }) => (
		<a href={to} {...props}>{children}</a>
	),
	useRouter: () => ({
		navigate: vi.fn(),
	}),
	useRouterState: () => ({
		location: { pathname: '/' },
	}),
	useSearch: () => ({ range: '7d' }),
	useNavigate: () => vi.fn(),
}));

vi.mock('@/hooks/use-keyboard-shortcuts', () => ({
	useKeyboardShortcuts: vi.fn(),
}));

vi.mock('@/lib/wp-commands', () => ({
	registerWpCommands: vi.fn(),
}));

vi.mock('lucide-react', () => {
	const Icon = ({ children, ...props }: Record<string, unknown>) => <svg {...props} />;
	return {
		BarChart3: Icon,
		FileText: Icon,
		Share2: Icon,
		Globe: Icon,
		Monitor: Icon,
		Languages: Icon,
		Activity: Icon,
		Settings: Icon,
	};
});

import { DashboardLayout } from '@/components/layouts/dashboard-layout';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DashboardLayout accessibility', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.12 — Skip-to-content link present in DOM
	it('renders a skip-to-content link that targets the main content area', () => {
		render(
			<DashboardLayout>
				<p>Dashboard content</p>
			</DashboardLayout>,
		);

		const skipLink = screen.getByText('Skip to content');
		expect(skipLink).toBeInTheDocument();
		expect(skipLink.tagName).toBe('A');
		expect(skipLink.getAttribute('href')).toBe('#statnive-content');
	});

	// REQ-1.12 — Link targets main content area
	it('has a main content area with matching id "statnive-content"', () => {
		const { container } = render(
			<DashboardLayout>
				<p>Dashboard content</p>
			</DashboardLayout>,
		);

		const main = container.querySelector('#statnive-content');
		expect(main).toBeInTheDocument();
		expect(main?.tagName).toBe('MAIN');
	});

	// Navigation landmark
	it('renders dashboard navigation with aria-label', () => {
		render(
			<DashboardLayout>
				<p>Dashboard content</p>
			</DashboardLayout>,
		);

		const nav = screen.getByRole('navigation', { name: 'Dashboard navigation' });
		expect(nav).toBeInTheDocument();
	});

	// All 8 nav tabs present
	it('renders all 8 navigation tabs', () => {
		render(
			<DashboardLayout>
				<p>Dashboard content</p>
			</DashboardLayout>,
		);

		const expectedTabs = ['Overview', 'Pages', 'Referrers', 'Geography', 'Devices', 'Languages', 'Real-time', 'Settings'];
		expectedTabs.forEach((tab) => {
			expect(screen.getByText(tab)).toBeInTheDocument();
		});
	});

	// Active tab has aria-current="page"
	it('marks the current active tab with aria-current="page"', () => {
		render(
			<DashboardLayout>
				<p>Dashboard content</p>
			</DashboardLayout>,
		);

		// The mock sets pathname to "/" so Overview should be active
		const overviewLink = screen.getByText('Overview').closest('a');
		expect(overviewLink).toHaveAttribute('aria-current', 'page');
	});
});
