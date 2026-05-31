import { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useAdvisorQuestions } from '@/hooks/use-advisor-questions';
import { useAdvisorPreferences } from '@/hooks/use-advisor-preferences';
import { QuestionTabs, PINNED_TAB_ID } from '@/components/advisor/QuestionTabs';
import { PinnedTab } from '@/components/advisor/PinnedTab';
import { CategoryTab } from '@/components/advisor/CategoryTab';
import { PinCapHint } from '@/components/advisor/PinCapHint';
import { isQuestionComingSoon } from '@/lib/advisor';

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

	const { questionsByCategory, comingSoonCategoryIds } = useMemo(() => {
		type QuestionRow = NonNullable<typeof inv>['questions'][number];
		const map: Record<string, QuestionRow[]> = {};
		const comingSoon = new Set<string>();
		if (!inv) return { questionsByCategory: map, comingSoonCategoryIds: comingSoon };
		for (const q of inv.questions) {
			(map[q.category_id] ||= []).push(q);
		}
		// Auto-recomputes when Phase 14 flips Paid questions to Free —
		// no follow-up code change needed.
		for (const [catId, rows] of Object.entries(map)) {
			if (rows.every(isQuestionComingSoon)) {
				comingSoon.add(catId);
			}
		}
		return { questionsByCategory: map, comingSoonCategoryIds: comingSoon };
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
	const maxPins = prefs?.max_pins ?? 10;

	return (
		<>
			<QuestionTabs
				categories={inv.categories}
				active={active}
				onChange={setActive}
				comingSoonCategoryIds={comingSoonCategoryIds}
			>
				{active === PINNED_TAB_ID ? (
					<PinnedTab pinnedIds={pinnedIds} maxPins={maxPins} questions={inv.questions} />
				) : (
					<CategoryTab
						questions={questionsByCategory[active] ?? []}
						pinnedIds={pinnedIds}
					/>
				)}
			</QuestionTabs>
			<PinCapHint />
		</>
	);
}
