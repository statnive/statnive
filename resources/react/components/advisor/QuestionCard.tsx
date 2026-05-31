import { useState, useId, type ReactNode } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronDown, Pin } from 'lucide-react';
import { cn } from '@/lib/utils';
import { isQuestionComingSoon } from '@/lib/advisor';
import { formatDateRangeLabel, formatPriorRangeLabel } from '@/lib/date-range-label';
import type { AdvisorAnswer, AdvisorQuestion } from '@/types/api';
import { useAdvisorPinMutations } from '@/hooks/use-advisor-preferences';
import { useSingleAdvisorAnswer, useCachedAdvisorAnswer } from '@/hooks/use-advisor-answers';
import { useDateRange } from '@/hooks/use-date-range';
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

	const { range, params } = useDateRange();
	const dynamicLabel = question.dynamic_window
		? question.dynamic_window === 'prior'
			? formatPriorRangeLabel(range, params)
			: formatDateRangeLabel(range, params)
		: null;
	const resolvedQuestion = dynamicLabel
		? question.question.replace('%s', dynamicLabel)
		: question.question;

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
		<div
			className={cn(
				'group border-t border-border transition-colors duration-150 first:border-t-0',
				!isComingSoon && 'hover:bg-muted/30',
				expanded && 'bg-muted/20',
			)}
		>
			{/* Header row — the whole row is the expand target so clicks
			    anywhere outside the pin button toggle the accordion. Keep
			    the inner expand button for keyboard a11y (Tab → Enter). */}
			<div
				className={cn(
					'flex items-center gap-3 px-6 py-4',
					!isComingSoon && 'cursor-pointer',
				)}
				onClick={(e) => {
					// Pin button (and anything inside it) owns its own click;
					// bail so the expand toggle doesn't fire twice.
					const target = e.target as HTMLElement;
					if (target.closest('[data-statnive-pin]')) return;
					toggleExpand();
				}}
			>
				<button
					type="button"
					data-statnive-pin
					onClick={(e) => {
						e.stopPropagation();
						togglePin();
					}}
					aria-pressed={pinned}
					aria-label={
						pinned
							? sprintf(
									/* translators: %s: question text */
									__('Unpin "%s"', 'statnive'),
									question.question,
								)
							: sprintf(
									/* translators: %s: question text */
									__('Pin "%s"', 'statnive'),
									question.question,
								)
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
							pinned
								? 'fill-[color:var(--color-accent)] text-[color:var(--color-accent)]'
								: 'text-muted-foreground/60 group-hover:text-muted-foreground',
						)}
					/>
				</button>

				<button
					type="button"
					id={headingId}
					onClick={(e) => {
						// Stop propagation so the outer row's click handler
						// doesn't fire a second time and cancel the toggle.
						e.stopPropagation();
						toggleExpand();
					}}
					aria-expanded={expanded}
					aria-controls={panelId}
					aria-disabled={isComingSoon}
					aria-label={question.question}
					className={cn(
						'flex flex-1 items-center gap-2 text-left',
						isComingSoon && 'cursor-not-allowed',
					)}
				>
					<span
						className={cn(
							'flex-1 truncate text-[15px] font-semibold tracking-tight',
							isComingSoon
								? 'text-muted-foreground'
								: 'text-[color:var(--color-primary)]',
						)}
						style={{ letterSpacing: '-0.01em' }}
						title={resolvedQuestion}
					>
						{dynamicLabel ? renderDynamicQuestion(question.question, dynamicLabel) : resolvedQuestion}
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

			{/* Helper caption — dynamic-window questions follow the date picker
			    above. Surfaced inline so owners learn the affordance without
			    expanding the card. */}
			{dynamicLabel && !isComingSoon && (
				<p className="px-15 pb-2 pt-0 text-[11px] italic text-muted-foreground/70">
					{__('Following the date picker above. Change the range to see a different period.', 'statnive')}
				</p>
			)}

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

/**
 * Split a sprintf-style question template on `%s` and wrap the dynamic
 * window phrase in a tinted-accent highlight. Returns a ReactNode so it
 * can slot directly into the question-text span.
 */
function renderDynamicQuestion(template: string, label: string): ReactNode {
	const idx = template.indexOf('%s');
	if (idx < 0) return template;
	const prefix = template.slice(0, idx);
	const suffix = template.slice(idx + 2);
	return (
		<>
			{prefix}
			<span className="rounded bg-[color:var(--color-accent)]/10 px-1 font-bold text-foreground">
				{label}
			</span>
			{suffix}
		</>
	);
}

function ChipCluster({ question }: { question: AdvisorQuestion }) {
	const isComingSoon = isQuestionComingSoon(question);
	if (isComingSoon) {
		// Impeccable quieter pass: drop the yellow background + emoji that
		// shouted "warning"; reduce to italic muted text so the chip reads
		// as a quiet status note next to the question, not a callout.
		return (
			<span className="inline-flex items-center text-[11px] italic text-muted-foreground/70">
				{__('Coming soon', 'statnive')}
			</span>
		);
	}
	// Confidence dot: monochrome, varies by solidity not hue so it doesn't
	// borrow traffic-light semantics (red ≠ bad answer, just indirect).
	// Hover reveals the full tier description.
	const conf = question.confidence;
	const tierLabel =
		conf === 'direct'
			? __('Direct', 'statnive')
			: conf === 'calculated'
				? __('Calculated', 'statnive')
				: __('Proxy', 'statnive');
	const tierDescription =
		conf === 'direct'
			? __('Read straight from stored data.', 'statnive')
			: conf === 'calculated'
				? __('Derived one step from stored data.', 'statnive')
				: __('Closest available stand-in, indicative not authoritative.', 'statnive');
	const dotClass =
		conf === 'direct'
			? 'bg-foreground/65'
			: conf === 'calculated'
				? 'bg-foreground/35'
				: 'border border-foreground/45 bg-transparent';
	return (
		<span
			role="img"
			aria-label={`${tierLabel}: ${tierDescription}`}
			className="group/conf relative inline-flex h-4 w-4 shrink-0 items-center justify-center"
		>
			<span className={cn('h-1.5 w-1.5 rounded-full transition-opacity', dotClass)} />
			<span
				role="tooltip"
				className="pointer-events-none absolute end-0 top-full z-10 mt-2 hidden w-max max-w-[220px] rounded-md border border-border bg-card px-2.5 py-1.5 text-start text-[11px] leading-snug text-muted-foreground shadow-sm group-hover/conf:block"
			>
				<span className="font-semibold text-foreground">{tierLabel}.</span> {tierDescription}
			</span>
		</span>
	);
}

function ComingSoonCaption({ question }: { question: AdvisorQuestion }) {
	const reasonText =
		typeof question.depends_on_schema === 'string'
			? __('Live in v1.1 — auto-enables when ready.', 'statnive')
			: __('Unlocks in Statnive Growth v2.', 'statnive');
	return (
		<div className="px-15 pb-3 pt-0 text-[13px] text-muted-foreground/70">{reasonText}</div>
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
			<div className="px-15 pb-6 pt-1">
				<div className="h-16 animate-pulse rounded bg-muted" />
			</div>
		);
	}
	if (answer.status === 'error') {
		return (
			<div className="px-15 pb-5 pt-1 text-sm text-destructive">
				{__("Couldn't load this answer.", 'statnive')}
			</div>
		);
	}
	return (
		<div className="px-15 pb-6 pt-1">
			<AnswerViz question={question} answer={answer} />
		</div>
	);
}
