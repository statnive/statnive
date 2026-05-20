import { useState, useId, type ReactNode } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronDown, Pin } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AdvisorAnswer, AdvisorQuestion } from '@/types/api';
import { useAdvisorPinMutations } from '@/hooks/use-advisor-preferences';
import { useSingleAdvisorAnswer, useCachedAdvisorAnswer } from '@/hooks/use-advisor-answers';
import { AnswerViz } from './AnswerViz';

/**
 * Single accordion row representing one of the 120 owner questions.
 *
 * States (plan §B):
 *   - Collapsed: 56px row with pin button + question + chip cluster + chevron.
 *   - Loading: viz slot shows skeleton, the row itself stays solid.
 *   - Expanded: viz + 💡 interpretation + source/cache metadata.
 *   - Coming-soon: chip-only, accordion disabled, pin disabled in v1.
 *   - Error: inline error message with retry.
 *
 * Pin button is disabled on coming-soon questions per plan §B.4.
 */
interface QuestionCardProps {
	question: AdvisorQuestion;
	pinned: boolean;
	startExpanded?: boolean;
}

export function QuestionCard({ question, pinned, startExpanded = false }: QuestionCardProps) {
	const isComingSoon = isQuestionComingSoon(question);
	const [expanded, setExpanded] = useState(startExpanded && !isComingSoon);
	const headingId = useId();
	const panelId = useId();

	const { pin, unpin, isPending } = useAdvisorPinMutations();

	const cached = useCachedAdvisorAnswer(question.id);
	const { data: fetched, isLoading } = useSingleAdvisorAnswer(
		question.id,
		expanded && cached === undefined && !isComingSoon,
	);
	const answer: AdvisorAnswer | undefined = cached ?? fetched;

	const togglePin = () => {
		if (isComingSoon || isPending) return;
		if (pinned) unpin(question.id);
		else pin(question.id);
	};

	const toggleExpand = () => {
		if (isComingSoon) return;
		setExpanded((x) => !x);
	};

	return (
		<div className="border-t border-border first:border-t-0">
			{/* Header row */}
			<div className="flex items-center gap-2 px-5 py-3.5">
				<button
					type="button"
					onClick={togglePin}
					aria-pressed={pinned}
					aria-label={
						pinned
							? sprintf(__('Unpin "%s"', 'statnive'), question.question)
							: sprintf(__('Pin "%s"', 'statnive'), question.question)
					}
					disabled={isComingSoon || isPending}
					className={cn(
						'flex h-6 w-6 shrink-0 items-center justify-center rounded transition-transform duration-150 hover:bg-muted',
						'active:scale-90',
						isComingSoon && 'cursor-not-allowed opacity-30',
					)}
				>
					<Pin
						className={cn(
							'h-4 w-4 transition-colors',
							pinned ? 'fill-primary text-primary' : 'text-muted-foreground',
						)}
					/>
				</button>

				<button
					type="button"
					id={headingId}
					onClick={toggleExpand}
					aria-expanded={expanded}
					aria-controls={panelId}
					aria-disabled={isComingSoon}
					className={cn(
						'flex flex-1 items-center gap-2 text-left text-sm font-medium',
						isComingSoon && 'cursor-not-allowed text-muted-foreground',
					)}
				>
					<span className="flex-1 truncate" title={question.question}>
						{question.question}
					</span>
					<ChipCluster question={question} />
					{!isComingSoon && (
						<ChevronDown
							className={cn(
								'h-3 w-3 shrink-0 text-muted-foreground transition-transform duration-200',
								expanded && 'rotate-180',
							)}
						/>
					)}
				</button>
			</div>

			{/* Coming-soon caption (always visible, never expandable) */}
			{isComingSoon && <ComingSoonCaption question={question} />}

			{/* Accordion body — grid-template-rows trick so we don't animate height */}
			{!isComingSoon && (
				<div
					id={panelId}
					role="region"
					aria-labelledby={headingId}
					className={cn(
						'grid transition-[grid-template-rows] duration-200',
						expanded ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
					)}
					style={{ transitionTimingFunction: 'cubic-bezier(0.25, 1, 0.5, 1)' }}
				>
					<div className="overflow-hidden">
						{expanded && (
							<ExpandedBody
								question={question}
								answer={answer}
								isLoading={isLoading && answer === undefined}
							/>
						)}
					</div>
				</div>
			)}
		</div>
	);
}

function isQuestionComingSoon(q: AdvisorQuestion): boolean {
	return q.plan === 'paid' || typeof q.depends_on_schema === 'string';
}

function ChipCluster({ question }: { question: AdvisorQuestion }) {
	const isComingSoon = isQuestionComingSoon(question);
	if (isComingSoon) {
		return (
			<span className="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-[11px] font-medium text-yellow-900 dark:bg-yellow-900/30 dark:text-yellow-300">
				<span aria-hidden="true">🟡</span>
				{__('Coming soon', 'statnive')}
			</span>
		);
	}
	const planLabel = question.plan === 'free' ? __('Free', 'statnive') : __('Paid', 'statnive');
	const confidenceGlyph =
		question.confidence === 'direct'
			? '🟢'
			: question.confidence === 'calculated'
				? '🟡'
				: '🔴';
	return (
		<span className="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground">
			<span>{planLabel}</span>
			<span aria-hidden="true">·</span>
			<span aria-hidden="true">{confidenceGlyph}</span>
		</span>
	);
}

function ComingSoonCaption({ question }: { question: AdvisorQuestion }) {
	const reasonText =
		typeof question.depends_on_schema === 'string'
			? __('Live in v1.1 — auto-enables when ready.', 'statnive')
			: __('Unlocks in Statnive Growth v2.', 'statnive');
	return (
		<div className="px-5 pb-3 pt-0 text-[13px] text-muted-foreground/70">{reasonText}</div>
	);
}

function ExpandedBody({
	question,
	answer,
	isLoading,
}: {
	question: AdvisorQuestion;
	answer: AdvisorAnswer | undefined;
	isLoading: boolean;
}): ReactNode {
	if (isLoading || !answer) {
		return (
			<div className="px-5 pb-6 pt-1">
				<div className="h-16 animate-pulse rounded bg-muted" />
			</div>
		);
	}
	if (answer.status === 'error') {
		return (
			<div className="px-5 pb-5 pt-1 text-sm text-destructive">
				{__("Couldn't load this answer.", 'statnive')}
			</div>
		);
	}
	return (
		<div className="px-5 pb-6 pt-1">
			<AnswerViz question={question} answer={answer} />
			<div className="mt-4 border-t border-border/50 pt-3 text-[12px] text-muted-foreground/75">
				{answer.source && (
					<span>
						{__('Source', 'statnive')}: <code className="text-[11px]">{answer.source}</code>
					</span>
				)}
			</div>
		</div>
	);
}
