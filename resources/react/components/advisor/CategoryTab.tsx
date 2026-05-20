import type { AdvisorQuestion } from '@/types/api';
import { QuestionCard } from './QuestionCard';

/**
 * Body for a single category tab: a vertical list of question rows,
 * collapsed by default. Each row lazy-loads its answer on expand via
 * `useSingleAdvisorAnswer` inside the card.
 */
interface CategoryTabProps {
	questions: AdvisorQuestion[];
	pinnedIds: string[];
}

export function CategoryTab({ questions, pinnedIds }: CategoryTabProps) {
	const pinnedSet = new Set(pinnedIds);
	return (
		<div className="mx-auto max-w-7xl">
			<div className="rounded-lg border border-border bg-card">
				{questions.map((q) => (
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
