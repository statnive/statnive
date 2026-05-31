import type { AdvisorQuestion } from '@/types/api';

/**
 * Single source of truth for "is this question coming-soon".
 *
 * A question is coming-soon when it requires the Growth-tier paid plan
 * (`plan === 'paid'`) OR when it depends on a schema column that v1
 * hasn't shipped yet (`depends_on_schema` is set). Both branches render
 * with the same caption + disabled accordion in the UI.
 *
 * Duplicated inline before this helper existed — keep callers using the
 * function so adding a third trigger (e.g. `depends_on_table`) is a
 * one-line edit instead of a six-site grep.
 */
export function isQuestionComingSoon(q: AdvisorQuestion): boolean {
	return q.plan === 'paid' || typeof q.depends_on_schema === 'string';
}
