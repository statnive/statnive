import { useMemo, useRef, useState, useEffect } from 'react';
import { isQuestionComingSoon } from '@/lib/advisor';
import type { AdvisorQuestion } from '@/types/api';

/**
 * Ranked-substring search across the full Ask me! question inventory.
 *
 * Plan §F.2 / §F.3 — ranking weights per match site, with a per-keyword
 * de-duplication so multi-word queries (e.g. "traffic this week") rank
 * higher when both terms hit a question's searchable index.
 *
 * Index is built once on mount from the inventory; lookup is O(n) over
 * ≤120 entries — finishes in <2ms in modern browsers.
 *
 * Bilingual (plan §G.3): `searchable[]` already includes the translated
 * question + English source + category labels + English keywords, so the
 * same query string matches against either language.
 */
const W_EXACT_PREFIX_QUESTION = 3.0;
const W_WORD_PREFIX_QUESTION = 2.0;
const W_SUBSTRING_QUESTION = 1.0;
const W_KEYWORD = 0.7;
const W_CATEGORY = 0.5;
const COMING_SOON_PENALTY = 0.4;

export interface SearchResult {
	question: AdvisorQuestion;
	score: number;
}

interface SearchIndex {
	question: AdvisorQuestion;
	questionLower: string;
	keywordsLower: string[];
	categoryLower: string;
	searchableLower: string[];
	isComingSoon: boolean;
}

function buildIndex(questions: AdvisorQuestion[]): SearchIndex[] {
	return questions.map((q) => ({
		question: q,
		questionLower: q.question.toLowerCase(),
		keywordsLower: q.keywords.map((k) => k.toLowerCase()),
		categoryLower: q.category.toLowerCase(),
		searchableLower: q.searchable.map((s) => s.toLowerCase()),
		isComingSoon: isQuestionComingSoon(q),
	}));
}

function scoreOne(entry: SearchIndex, term: string): number {
	if (term.length === 0) return 0;

	let best = 0;

	// Question text matches.
	if (entry.questionLower.startsWith(term)) {
		best = Math.max(best, W_EXACT_PREFIX_QUESTION);
	}
	if (entry.questionLower.split(/\s+/).some((w) => w.startsWith(term))) {
		best = Math.max(best, W_WORD_PREFIX_QUESTION);
	}
	if (entry.questionLower.includes(term)) {
		best = Math.max(best, W_SUBSTRING_QUESTION);
	}

	// English-source question fallback via searchable[] — any non-question
	// entry that contains the term contributes a keyword-tier match.
	for (const s of entry.searchableLower) {
		if (s === entry.questionLower) continue; // Already scored.
		if (s.includes(term)) {
			best = Math.max(best, W_KEYWORD);
			break;
		}
	}

	// Keyword exact match boosts the keyword tier.
	if (entry.keywordsLower.includes(term)) {
		best = Math.max(best, W_KEYWORD);
	}

	// Category fallback (lowest tier).
	if (entry.categoryLower.includes(term)) {
		best = Math.max(best, W_CATEGORY);
	}

	return best;
}

export function searchInventory(
	index: SearchIndex[],
	rawQuery: string,
	limit = 8,
): SearchResult[] {
	const query = rawQuery.trim().toLowerCase();
	if (query.length < 2) return [];

	// Split into terms; require every term to contribute to keep results tight.
	const terms = query.split(/\s+/).filter((t) => t.length > 0);
	if (terms.length === 0) return [];

	const results: SearchResult[] = [];
	for (const entry of index) {
		let total = 0;
		let matchedAll = true;
		for (const term of terms) {
			const s = scoreOne(entry, term);
			if (s === 0) {
				matchedAll = false;
				break;
			}
			total += s;
		}
		if (!matchedAll) continue;
		if (entry.isComingSoon) total *= COMING_SOON_PENALTY;
		results.push({ question: entry.question, score: total });
	}

	results.sort((a, b) => {
		if (b.score !== a.score) return b.score - a.score;
		return a.question.id.localeCompare(b.question.id);
	});
	return results.slice(0, limit);
}

/**
 * Reactive search hook. Builds the index once when `questions` changes,
 * then debounces the input by 100ms before recomputing the ranked list.
 */
export function useAdvisorSearch(questions: AdvisorQuestion[], query: string, limit = 8) {
	const index = useMemo(() => buildIndex(questions), [questions]);
	const [debounced, setDebounced] = useState(query);
	const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

	useEffect(() => {
		if (timerRef.current) clearTimeout(timerRef.current);
		timerRef.current = setTimeout(() => setDebounced(query), 100);
		return () => {
			if (timerRef.current) clearTimeout(timerRef.current);
		};
	}, [query]);

	return useMemo(() => searchInventory(index, debounced, limit), [index, debounced, limit]);
}
