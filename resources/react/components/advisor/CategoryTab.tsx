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

	// Three-bucket ordering inside every category tab: the user's pinned
	// rows surface first (so they appear consistently across category +
	// "Ask me!" tabs), then the remaining actionable rows, then the
	// Coming-soon rows at the tail. Within each bucket the original
	// inventory order is preserved.
	const pinnedRows: AdvisorQuestion[] = [];
	const liveRows: AdvisorQuestion[] = [];
	const soonRows: AdvisorQuestion[] = [];
	for (const q of questions) {
		if (pinnedSet.has(q.id)) {
			pinnedRows.push(q);
		} else if (isComingSoon(q)) {
			soonRows.push(q);
		} else {
			liveRows.push(q);
		}
	}
	const ordered = [...pinnedRows, ...liveRows, ...soonRows];
	// The parent QuestionTabs tabpanel already centers + caps width; this
	// component only owns the card-list visual shell.
	return (
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
	);
}
