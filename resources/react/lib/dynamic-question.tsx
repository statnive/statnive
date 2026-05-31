import { type ReactNode } from 'react';
import { useDateRange } from '@/hooks/use-date-range';
import { formatDateRangeLabel, formatPriorRangeLabel } from '@/lib/date-range-label';
import type { AdvisorQuestion } from '@/types/api';

/**
 * Shared helpers for the dynamic-window question text + highlight render.
 *
 * Three Ask me! surfaces need the same substitution: the in-tab QuestionCard,
 * the AnswerModal opened from search, and the SearchSuggestions dropdown.
 * Keeping the date-picker subscription + sprintf-split logic here means the
 * three sites can't drift apart, and the predicate for "is this a dynamic
 * question?" lives in one place.
 */

/**
 * Subscribe to the date range and return the localised window phrase
 * for a question — `null` if the question isn't dynamic.
 */
export function useDynamicQuestionLabel(question: AdvisorQuestion): string | null {
	const labels = useDynamicWindowLabels();
	return pickDynamicLabel(question, labels);
}

interface DynamicWindowLabels {
	current: string;
	prior: string;
}

/**
 * Subscribe once and return BOTH window phrases (current + prior). Use
 * this from list-rendering surfaces (e.g. SearchSuggestions) where the
 * row loop can't call a per-question hook; pair with {@link pickDynamicLabel}.
 */
export function useDynamicWindowLabels(): DynamicWindowLabels {
	const { range, params } = useDateRange();
	return {
		current: formatDateRangeLabel(range, params),
		prior: formatPriorRangeLabel(range, params),
	};
}

/** Pure label-picker. `null` when the question isn't dynamic. */
export function pickDynamicLabel(
	question: AdvisorQuestion,
	labels: DynamicWindowLabels,
): string | null {
	if (!question.dynamic_window) return null;
	return question.dynamic_window === 'prior' ? labels.prior : labels.current;
}

/**
 * Substitute `label` into the question's `%s` and return both the
 * highlighted ReactNode (for visible rendering) and the resolved plain
 * string (for `title` / aria-label / search-index uses).
 *
 * When `label` is `null` the function returns the raw template unchanged
 * so callers can branch on whether substitution actually fired.
 *
 * @param highlightClassName Optional className for the tinted-accent
 *   highlight span. Defaults to QuestionCard's styling; SearchSuggestions
 *   overrides to a less-prominent variant inside the dropdown.
 */
export function resolveDynamicQuestion(
	template: string,
	label: string | null,
	highlightClassName = 'rounded bg-[color:var(--color-accent)]/10 px-1 font-bold text-foreground',
): { node: ReactNode; text: string } {
	if (label === null) return { node: template, text: template };
	const idx = template.indexOf('%s');
	if (idx < 0) return { node: template, text: template };
	const prefix = template.slice(0, idx);
	const suffix = template.slice(idx + 2);
	const text = prefix + label + suffix;
	const node = (
		<>
			{prefix}
			<span className={highlightClassName}>{label}</span>
			{suffix}
		</>
	);
	return { node, text };
}
