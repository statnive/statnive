import { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useAdvisorQuestions } from '@/hooks/use-advisor-questions';
import { useAdvisorPreferences } from '@/hooks/use-advisor-preferences';
import { QuestionTabs, PINNED_TAB_ID } from '@/components/advisor/QuestionTabs';
import { PinnedTab } from '@/components/advisor/PinnedTab';
import { CategoryTab } from '@/components/advisor/CategoryTab';

/**
 * "Ask me!" — Statnive Advisor v1 page.
 *
 * Plan: ~/.claude/plans/now-based-on-all-luminous-rivest.md
 * Research: #71 (owner inventory) + #73 (per-category specs).
 *
 * 11 in-page tabs: 1 pinned "Ask me!" tab + 10 categories. Each tab body
 * is an accordion of question cards that lazy-load their answer.
 */
export function AskPage() {
	const { data: inv, isLoading, error } = useAdvisorQuestions();
	const { data: prefs } = useAdvisorPreferences();

	const [active, setActive] = useState<string>(PINNED_TAB_ID);

	const questionsByCategory = useMemo(() => {
		const map: Record<string, typeof inv extends { questions: infer T } ? T : never> =
			{} as never;
		if (!inv) return map;
		for (const q of inv.questions) {
			if (!map[q.category_id]) map[q.category_id] = [] as never;
			(map[q.category_id] as unknown as Array<(typeof inv.questions)[0]>).push(q);
		}
		return map as Record<string, (typeof inv.questions)[0][]>;
	}, [inv]);

	if (isLoading || !inv) {
		return (
			<div className="px-5 py-10">
				<div className="mx-auto h-32 max-w-7xl animate-pulse rounded bg-muted" />
			</div>
		);
	}

	if (error) {
		return (
			<div className="px-5 py-10 text-center text-sm text-destructive">
				{__('Could not load Ask me! questions.', 'statnive')}
			</div>
		);
	}

	const pinnedIds = prefs?.pinned_questions ?? [];
	const maxPins = prefs?.max_pins ?? 20;

	return (
		<QuestionTabs categories={inv.categories} active={active} onChange={setActive}>
			{active === PINNED_TAB_ID ? (
				<PinnedTab pinnedIds={pinnedIds} maxPins={maxPins} questions={inv.questions} />
			) : (
				<CategoryTab
					questions={questionsByCategory[active] ?? []}
					pinnedIds={pinnedIds}
				/>
			)}
		</QuestionTabs>
	);
}
