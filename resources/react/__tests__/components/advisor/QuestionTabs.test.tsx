import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createElement } from 'react';
import { QuestionTabs, PINNED_TAB_ID } from '@/components/advisor/QuestionTabs';
import type { AdvisorCategory } from '@/types/api';

// Mock lucide-react icons to keep the snapshot DOM noise-free.
vi.mock('lucide-react', () => {
	const Icon = ({ className }: { className?: string }) =>
		createElement('span', { className, 'data-testid': 'icon' });
	return {
		Pin: Icon,
		BarChart3: Icon,
		Activity: Icon,
		FileText: Icon,
		Share2: Icon,
		Send: Icon,
		Globe: Icon,
		Monitor: Icon,
		Gauge: Icon,
		DollarSign: Icon,
		Zap: Icon,
	};
});

const categories: AdvisorCategory[] = [
	{ id: 'traffic_overview', label: 'Traffic Overview', label_en: 'Traffic Overview' },
	{ id: 'pages_and_content', label: 'Pages & Content', label_en: 'Pages & Content' },
	{ id: 'revenue', label: 'Revenue', label_en: 'Revenue' },
];

beforeEach(() => {
	// jsdom doesn't implement scrollIntoView.
	Element.prototype.scrollIntoView = vi.fn();
});

describe('QuestionTabs', () => {
	it('renders the pinned tab + every category tab', () => {
		render(
			<QuestionTabs categories={categories} active={PINNED_TAB_ID} onChange={() => {}}>
				<div>child</div>
			</QuestionTabs>,
		);
		expect(screen.getByRole('tab', { name: /Ask me!/i })).toBeInTheDocument();
		expect(screen.getByRole('tab', { name: 'Traffic Overview' })).toBeInTheDocument();
		expect(screen.getByRole('tab', { name: 'Pages & Content' })).toBeInTheDocument();
		expect(screen.getByRole('tab', { name: 'Revenue' })).toBeInTheDocument();
	});

	it('marks the active tab as aria-selected="true"', () => {
		render(
			<QuestionTabs categories={categories} active="revenue" onChange={() => {}}>
				<div />
			</QuestionTabs>,
		);
		const revenue = screen.getByRole('tab', { name: 'Revenue' });
		const traffic = screen.getByRole('tab', { name: 'Traffic Overview' });
		expect(revenue.getAttribute('aria-selected')).toBe('true');
		expect(traffic.getAttribute('aria-selected')).toBe('false');
	});

	it('calls onChange when a tab is clicked', async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();
		render(
			<QuestionTabs categories={categories} active={PINNED_TAB_ID} onChange={onChange}>
				<div />
			</QuestionTabs>,
		);
		await user.click(screen.getByRole('tab', { name: 'Revenue' }));
		expect(onChange).toHaveBeenCalledWith('revenue');
	});

	it('cycles tabs with ArrowRight / ArrowLeft (no wrap)', async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();
		const { rerender } = render(
			<QuestionTabs categories={categories} active={PINNED_TAB_ID} onChange={onChange}>
				<div />
			</QuestionTabs>,
		);

		// Focus the tablist via the active tab so keyboard events route there.
		const askTab = screen.getByRole('tab', { name: /Ask me!/i });
		askTab.focus();
		await user.keyboard('{ArrowRight}');
		expect(onChange).toHaveBeenLastCalledWith('traffic_overview');

		// Simulate parent updating the active tab.
		rerender(
			<QuestionTabs categories={categories} active="traffic_overview" onChange={onChange}>
				<div />
			</QuestionTabs>,
		);
		screen.getByRole('tab', { name: 'Traffic Overview' }).focus();
		await user.keyboard('{ArrowLeft}');
		expect(onChange).toHaveBeenLastCalledWith(PINNED_TAB_ID);
	});

	it('jumps to first/last tab with Home / End', async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();
		render(
			<QuestionTabs categories={categories} active="pages_and_content" onChange={onChange}>
				<div />
			</QuestionTabs>,
		);
		screen.getByRole('tab', { name: 'Pages & Content' }).focus();
		await user.keyboard('{Home}');
		expect(onChange).toHaveBeenLastCalledWith(PINNED_TAB_ID);
		await user.keyboard('{End}');
		expect(onChange).toHaveBeenLastCalledWith('revenue');
	});

	it('sets ARIA tablist + tabpanel roles', () => {
		render(
			<QuestionTabs categories={categories} active={PINNED_TAB_ID} onChange={() => {}}>
				<div data-testid="panel-content">panel</div>
			</QuestionTabs>,
		);
		expect(screen.getByRole('tablist')).toHaveAttribute('aria-orientation', 'horizontal');
		const panel = screen.getByRole('tabpanel');
		expect(panel).toBeInTheDocument();
		expect(panel.querySelector('[data-testid="panel-content"]')).not.toBeNull();
	});

	it('marks tabs in comingSoonCategoryIds via data-coming-soon', () => {
		const comingSoon = new Set<string>(['revenue']);
		render(
			<QuestionTabs
				categories={categories}
				active={PINNED_TAB_ID}
				onChange={() => {}}
				comingSoonCategoryIds={comingSoon}
			>
				<div />
			</QuestionTabs>,
		);
		expect(screen.getByRole('tab', { name: 'Revenue' })).toHaveAttribute(
			'data-coming-soon',
			'true',
		);
		expect(screen.getByRole('tab', { name: 'Traffic Overview' })).not.toHaveAttribute(
			'data-coming-soon',
		);
	});
});
