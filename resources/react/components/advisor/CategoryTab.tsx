import type { AdvisorQuestion } from '@/types/api';
import { QuestionCard } from './QuestionCard';

/**
 * Body for a single category tab: a vertical list of question rows,
 * collapsed by default. Each row lazy-loads its answer on expand via
 * `useSingleAdvisorAnswer` inside the card.
 *
 * Coming-soon questions float to the bottom of the list so the owner
 * sees actionable rows first.
 */
interface CategoryTabProps {
	questions: AdvisorQuestion[];
	pinnedIds: string[];
}

function isComingSoon(q: AdvisorQuestion): boolean {
	return q.plan === 'paid' || typeof q.depends_on_schema === 'string';
}

export function CategoryTab({ questions, pinnedIds }: CategoryTabProps) {
	const pinnedSet = new Set(pinnedIds);
	const live = questions.filter((q) => !isComingSoon(q));
	const soon = questions.filter((q) => isComingSoon(q));
	const ordered = [...live, ...soon];
	return (
		<div className="mx-auto max-w-7xl">
			<div className="rounded-lg border border-border bg-card">
				{ordered.map((q) => (
					<QuestionCard
						key={q.id}
						question={q}
						pinned={pinnedSet.has(q.id)}
						startExpanded={false}
					/>
				))}
			</div>
		</div>
	);
}
