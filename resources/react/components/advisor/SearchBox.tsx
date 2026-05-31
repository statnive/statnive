import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Search } from 'lucide-react';
import { SearchSuggestions } from './SearchSuggestions';
import { AnswerModal } from './AnswerModal';
import { useAdvisorSearch } from '@/hooks/use-advisor-search';
import type { AdvisorQuestion } from '@/types/api';

/**
 * The home-tab search escape-hatch (plan §F).
 *
 * - 40px input with leading icon + ⌘K/Ctrl+K shortcut hint.
 * - Cmd+K (mac) / Ctrl+K (other) focuses the input from anywhere on the
 *   Ask me! page; the parent component decides when this is mounted so
 *   the global shortcut never collides with WP admin keybinds outside.
 * - Escape with text clears the query + closes the suggestion dropdown.
 * - Clicking a suggestion opens AnswerModal with a single-question fetch.
 */
interface SearchBoxProps {
	questions: AdvisorQuestion[];
	pinnedIds: string[];
}

function isMacPlatform(): boolean {
	if (typeof navigator === 'undefined') return false;
	return /Mac|iPhone|iPad|iPod/i.test(navigator.platform);
}

export function SearchBox({ questions, pinnedIds }: SearchBoxProps) {
	const [query, setQuery] = useState('');
	const [focused, setFocused] = useState(false);
	const [selectedIndex, setSelectedIndex] = useState(0);
	const [modalQuestion, setModalQuestion] = useState<AdvisorQuestion | null>(null);
	const inputRef = useRef<HTMLInputElement | null>(null);

	const results = useAdvisorSearch(questions, query);

	// Cmd+K / Ctrl+K focuses the input.
	useEffect(() => {
		const onKeyDown = (e: KeyboardEvent) => {
			const mod = isMacPlatform() ? e.metaKey : e.ctrlKey;
			if (mod && e.key.toLowerCase() === 'k') {
				e.preventDefault();
				inputRef.current?.focus();
				inputRef.current?.select();
			}
		};
		window.addEventListener('keydown', onKeyDown);
		return () => window.removeEventListener('keydown', onKeyDown);
	}, []);

	const open = focused && query.trim().length >= 2;
	const noMatches = open && results.length === 0;

	const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
		if (!open) return;
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			setSelectedIndex((i) => Math.min(i + 1, Math.max(0, results.length - 1)));
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			setSelectedIndex((i) => Math.max(0, i - 1));
		} else if (e.key === 'Enter') {
			e.preventDefault();
			const result = results[selectedIndex];
			if (result) setModalQuestion(result.question);
		} else if (e.key === 'Escape') {
			e.preventDefault();
			if (query.length > 0) {
				setQuery('');
				setSelectedIndex(0);
			} else {
				inputRef.current?.blur();
			}
		}
	};

	const closeModal = () => {
		setModalQuestion(null);
		// Restore focus to the search input so the user can keep refining.
		setTimeout(() => inputRef.current?.focus(), 0);
	};

	return (
		<div className="relative mx-auto w-full max-w-3xl px-5 pb-4">
			<div className="relative">
				<Search
					className="pointer-events-none absolute top-1/2 -translate-y-1/2 start-3.5 h-4 w-4 text-muted-foreground"
					aria-hidden="true"
				/>
				<input
					ref={inputRef}
					type="search"
					value={query}
					onChange={(e) => {
						setQuery(e.currentTarget.value);
						setSelectedIndex(0);
					}}
					onFocus={() => setFocused(true)}
					onBlur={() => {
						// Delay so a suggestion click registers before we close.
						setTimeout(() => setFocused(false), 100);
					}}
					onKeyDown={handleKeyDown}
					placeholder={__(
						'Search 120 questions about your traffic, sales, or visitors…',
						'statnive',
					)}
					aria-label={__('Search questions', 'statnive')}
					aria-autocomplete="list"
					aria-expanded={open}
					aria-controls="statnive-advisor-suggestions"
					role="combobox"
					className="h-10 w-full rounded-md border border-border bg-card text-sm !ps-11 !pe-20 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
				/>
				<span
					aria-hidden="true"
					className="pointer-events-none absolute top-1/2 -translate-y-1/2 end-3 text-[11px] font-medium text-muted-foreground/60"
				>
					{isMacPlatform() ? '⌘K' : 'Ctrl+K'}
				</span>
			</div>

			{open && (
				<SearchSuggestions
					id="statnive-advisor-suggestions"
					results={results}
					selectedIndex={selectedIndex}
					onHover={setSelectedIndex}
					onSelect={(q) => setModalQuestion(q)}
				/>
			)}

			{noMatches && (
				<p className="px-1 pt-2 text-sm text-muted-foreground" role="status" aria-live="polite">
					{__(
						"No questions match. Try a different keyword, or browse the category tabs.",
						'statnive',
					)}
				</p>
			)}

			{modalQuestion && (
				<AnswerModal
					question={modalQuestion}
					pinned={pinnedIds.includes(modalQuestion.id)}
					onClose={closeModal}
				/>
			)}
		</div>
	);
}
