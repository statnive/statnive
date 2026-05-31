import { __ } from '@wordpress/i18n';
import { useAdvisorAnswers } from '@/hooks/use-advisor-answers';
import type { AdvisorQuestion } from '@/types/api';
import { QuestionCard } from './QuestionCard';
import { SearchBox } from './SearchBox';

/**
 * The home tab — "Ask me!" — shows the user's pinned questions expanded
 * by default, all answers fetched in a single batched POST on mount per
 * plan §D.
 *
 * Pin/unpin from any other tab (or from inside a card) updates this list
 * via the shared TanStack Query cache.
 */
interface PinnedTabProps {
	pinnedIds: string[];
	maxPins: number;
	questions: AdvisorQuestion[];
}

export function PinnedTab({ pinnedIds, maxPins, questions }: PinnedTabProps) {
	const byId = new Map(questions.map((q) => [q.id, q]));
	const pinnedQuestions: AdvisorQuestion[] = pinnedIds
		.map((id) => byId.get(id))
		.filter((q): q is AdvisorQuestion => q !== undefined);

	// Batched fetch — primes per-ID cache so each QuestionCard renders
	// without firing its own request.
	useAdvisorAnswers(pinnedIds, pinnedIds.length > 0);

	// The parent QuestionTabs tabpanel already wraps content in
	// `mx-auto max-w-7xl px-4 py-6`, so this component returns a Fragment
	// and adds only its own layout spacing.
	return (
		<>
			<SearchBox questions={questions} pinnedIds={pinnedIds} />

			{pinnedQuestions.length === 0 ? (
				<EmptyPinned />
			) : (
				<>
					<p className="px-5 pb-5 text-sm text-muted-foreground">
						{__('Your pinned questions. Pin more from any category.', 'statnive')}
					</p>
					<div className="rounded-lg border border-border bg-card">
						{pinnedQuestions.map((q) => (
							<QuestionCard
								key={q.id}
								question={q}
								pinned={true}
								startExpanded={true}
							/>
						))}
					</div>
					<p className="mt-3 px-5 text-xs text-muted-foreground/70">
						{pinnedIds.length}/{maxPins} {__('pinned', 'statnive')}
					</p>
				</>
			)}
		</>
	);
}

function EmptyPinned() {
	return (
		<div className="px-5 py-10 text-center">
			<p className="text-base font-medium">
				{__('No pinned questions yet.', 'statnive')}
			</p>
			<p className="mt-2 text-sm text-muted-foreground">
				{__('Pin any question from a category tab to see it here.', 'statnive')}
			</p>
		</div>
	);
}
