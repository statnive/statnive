import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createElement, type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { QuestionCard } from '@/components/advisor/QuestionCard';
import type { AdvisorQuestion } from '@/types/api';

// Mock lucide-react icons.
vi.mock('lucide-react', () => {
	const Icon = ({ className }: { className?: string }) =>
		createElement('span', { className, 'data-testid': 'icon' });
	return { ChevronDown: Icon, Pin: Icon };
});

// Mock the answer hooks — we test rendering shape, not data fetching here.
vi.mock('@/hooks/use-advisor-answers', () => ({
	useSingleAdvisorAnswer: () => ({ data: undefined, isLoading: false }),
	useCachedAdvisorAnswer: () => undefined,
}));

const pin = vi.fn();
const unpin = vi.fn();
vi.mock('@/hooks/use-advisor-preferences', () => ({
	useAdvisorPinMutations: () => ({ pin, unpin, setPinned: vi.fn(), isPending: false }),
}));

function withQueryClient(children: ReactNode) {
	const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
	return <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
}

function freeQuestion(overrides: Partial<AdvisorQuestion> = {}): AdvisorQuestion {
	return {
		id: 'q2',
		category_id: 'traffic_overview',
		category: 'Traffic Overview',
		category_en: 'Traffic Overview',
		question: 'How many people visited this week?',
		question_en: 'How many people visited this week?',
		keywords: ['traffic'],
		plan: 'free',
		surface: '/summary',
		viz_hint: 'kpi_tile',
		confidence: 'direct',
		searchable: [],
		...overrides,
	};
}

function paidQuestion(): AdvisorQuestion {
	return freeQuestion({
		id: 'q101',
		category_id: 'revenue',
		category: 'Revenue',
		category_en: 'Revenue',
		question: 'How many orders did I get?',
		question_en: 'How many orders did I get?',
		plan: 'paid',
	});
}

function schemaGapQuestion(): AdvisorQuestion {
	return freeQuestion({
		id: 'q40',
		question: 'Which pages have the longest average duration?',
		question_en: 'Which pages have the longest average duration?',
		depends_on_schema: 'avg_time_on_page',
		plan: 'paid',
	});
}

beforeEach(() => {
	pin.mockReset();
	unpin.mockReset();
});

describe('QuestionCard', () => {
	it('renders collapsed by default for a Free question', () => {
		render(withQueryClient(<QuestionCard question={freeQuestion()} pinned={false} />));
		expect(screen.getByText('How many people visited this week?')).toBeInTheDocument();
		// Quieter chip cluster — Free questions show only the confidence
		// glyph (🟢 for `direct`), no redundant "Free" label.
		expect(screen.getByText('🟢')).toBeInTheDocument();
		expect(screen.queryByText('Free')).not.toBeInTheDocument();
	});

	it('expands when the header button is clicked', async () => {
		const user = userEvent.setup();
		render(withQueryClient(<QuestionCard question={freeQuestion()} pinned={false} />));
		const trigger = screen.getByRole('button', {
			name: 'How many people visited this week?',
		});
		expect(trigger).toHaveAttribute('aria-expanded', 'false');
		await user.click(trigger);
		expect(trigger).toHaveAttribute('aria-expanded', 'true');
	});

	it('renders coming-soon chip + caption for a Paid question and disables expansion', async () => {
		const user = userEvent.setup();
		render(withQueryClient(<QuestionCard question={paidQuestion()} pinned={false} />));
		expect(screen.getByText('Coming soon')).toBeInTheDocument();
		expect(screen.getByText(/Unlocks in Statnive Growth v2/i)).toBeInTheDocument();

		const trigger = screen.getByRole('button', { name: 'How many orders did I get?' });
		expect(trigger).toHaveAttribute('aria-disabled', 'true');
		await user.click(trigger);
		expect(trigger).toHaveAttribute('aria-expanded', 'false');
	});

	it('renders v1.1 schema-gap caption for schema-gap questions', () => {
		render(withQueryClient(<QuestionCard question={schemaGapQuestion()} pinned={false} />));
		expect(screen.getByText('Coming soon')).toBeInTheDocument();
		expect(screen.getByText(/Live in v1\.1/i)).toBeInTheDocument();
	});

	it('calls pin() when the pin button is clicked on an un-pinned Free question', async () => {
		const user = userEvent.setup();
		render(withQueryClient(<QuestionCard question={freeQuestion()} pinned={false} />));
		const pinBtn = screen.getByRole('button', { name: /^Pin "How many people visited this week\?"$/i });
		await user.click(pinBtn);
		expect(pin).toHaveBeenCalledWith('q2');
	});

	it('calls unpin() when the pin button is clicked on a pinned Free question', async () => {
		const user = userEvent.setup();
		render(withQueryClient(<QuestionCard question={freeQuestion()} pinned={true} />));
		const pinBtn = screen.getByRole('button', { name: /^Unpin "How many people visited this week\?"$/i });
		await user.click(pinBtn);
		expect(unpin).toHaveBeenCalledWith('q2');
	});

	it('disables the pin button for a coming-soon (Paid) question', async () => {
		const user = userEvent.setup();
		render(withQueryClient(<QuestionCard question={paidQuestion()} pinned={false} />));
		const pinBtn = screen.getByRole('button', { name: /^Pin "How many orders did I get\?"$/i });
		expect(pinBtn).toBeDisabled();
		await user.click(pinBtn);
		expect(pin).not.toHaveBeenCalled();
	});
});
