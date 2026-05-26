import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { isQuestionComingSoon } from '@/lib/advisor';
import type { AdvisorQuestion } from '@/types/api';
import type { SearchResult } from '@/hooks/use-advisor-search';

/**
 * Absolutely-positioned dropdown of ranked search suggestions.
 *
 * - role="listbox" with each row role="option" + aria-selected.
 * - Keyboard navigation is owned by the parent SearchBox (↓/↑/Enter/Esc);
 *   this component only handles hover + click.
 * - Coming-soon questions get a distinct chip + faded styling (×0.4 rank
 *   penalty is applied upstream in useAdvisorSearch).
 */
interface SearchSuggestionsProps {
	id: string;
	results: SearchResult[];
	selectedIndex: number;
	onHover: (index: number) => void;
	onSelect: (question: AdvisorQuestion) => void;
}

export function SearchSuggestions({
	id,
	results,
	selectedIndex,
	onHover,
	onSelect,
}: SearchSuggestionsProps) {
	if (results.length === 0) return null;
	return (
		<ul
			id={id}
			role="listbox"
			className="absolute left-5 right-5 z-30 mt-1 max-h-96 overflow-y-auto rounded-md border border-border bg-card shadow-lg"
		>
			{results.map(({ question }, i) => {
				const isComingSoon = isQuestionComingSoon(question);
				const isActive = i === selectedIndex;
				return (
					<li
						key={question.id}
						role="option"
						aria-selected={isActive}
						onMouseEnter={() => onHover(i)}
						onMouseDown={(e) => {
							// Use mousedown (not click) so the input blur doesn't dismiss
							// the dropdown before the click handler fires.
							e.preventDefault();
							onSelect(question);
						}}
						className={cn(
							'flex cursor-pointer items-center gap-3 px-4 py-2 text-sm',
							isActive && 'bg-muted',
						)}
					>
						<span className="flex-1 truncate">{question.question}</span>
						<span className="shrink-0 rounded-full bg-muted/70 px-2 py-0.5 text-[11px] text-muted-foreground">
							{question.category}
						</span>
						{isComingSoon && (
							<span className="shrink-0 text-[11px] italic text-muted-foreground/70">
								{__('Coming soon', 'statnive')}
							</span>
						)}
					</li>
				);
			})}
		</ul>
	);
}
