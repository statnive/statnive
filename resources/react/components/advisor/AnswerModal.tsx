import { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { X, Pin } from 'lucide-react';
import { cn } from '@/lib/utils';
import { AnswerViz } from './AnswerViz';
import { useSingleAdvisorAnswer } from '@/hooks/use-advisor-answers';
import { useAdvisorPinMutations } from '@/hooks/use-advisor-preferences';
import type { AdvisorQuestion } from '@/types/api';

/**
 * Focused-detour answer modal for the search flow (plan §F.5).
 *
 * Impeccable note: a modal is the right shape here because the search is
 * an *escape-hatch* — owners use it to find a one-off answer without
 * disturbing their pinned-tab ritual. The modal preserves the pinned-tab
 * context behind it, lets the owner glance + close, and returns them
 * exactly where they were.
 *
 * - role="dialog" + aria-modal="true" + aria-labelledby.
 * - Focus trap: Tab cycles Close-X → Pin → close again; initial focus on
 *   close (least-destructive).
 * - Esc closes; outside-click closes; both restore focus to the search
 *   input via the parent's `onClose`.
 * - Coming-soon questions render caption-only (no viz, no pin button).
 */
interface AnswerModalProps {
	question: AdvisorQuestion;
	pinned: boolean;
	onClose: () => void;
}

export function AnswerModal({ question, pinned, onClose }: AnswerModalProps) {
	const isComingSoon =
		question.plan === 'paid' || typeof question.depends_on_schema === 'string';

	const { data: answer, isLoading } = useSingleAdvisorAnswer(
		isComingSoon ? null : question.id,
		!isComingSoon,
	);
	const { pin, unpin, isPending } = useAdvisorPinMutations();

	const closeRef = useRef<HTMLButtonElement | null>(null);
	const dialogRef = useRef<HTMLDivElement | null>(null);

	// Initial focus + body scroll lock.
	useEffect(() => {
		const previouslyFocused = document.activeElement as HTMLElement | null;
		closeRef.current?.focus();
		const previousOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		return () => {
			document.body.style.overflow = previousOverflow;
			previouslyFocused?.focus?.();
		};
	}, []);

	// Esc closes.
	useEffect(() => {
		const onKey = (e: KeyboardEvent) => {
			if (e.key === 'Escape') {
				e.preventDefault();
				onClose();
			}
		};
		window.addEventListener('keydown', onKey);
		return () => window.removeEventListener('keydown', onKey);
	}, [onClose]);

	const togglePin = () => {
		if (isComingSoon || isPending) return;
		if (pinned) unpin(question.id);
		else pin(question.id);
	};

	const titleId = `statnive-answer-modal-title-${question.id}`;
	const bodyId = `statnive-answer-modal-body-${question.id}`;

	const reasonCaption = isComingSoon
		? typeof question.depends_on_schema === 'string'
			? __('Live in v1.1 — auto-enables when ready.', 'statnive')
			: __('Unlocks in Statnive Growth v2.', 'statnive')
		: null;

	return (
		<div
			className="fixed inset-0 z-40 flex items-start justify-center bg-black/60 p-6 pt-20"
			onMouseDown={(e) => {
				// Click on the backdrop (not the dialog) closes.
				if (e.target === e.currentTarget) onClose();
			}}
		>
			<div
				ref={dialogRef}
				role="dialog"
				aria-modal="true"
				aria-labelledby={titleId}
				aria-describedby={bodyId}
				className="relative w-full max-w-2xl rounded-lg border border-border bg-card shadow-xl"
				style={{ animation: 'statnive-modal-in 160ms cubic-bezier(0.25, 1, 0.5, 1)' }}
			>
				{/* Header */}
				<div className="flex items-start gap-3 border-b border-border p-6 pb-4">
					<div className="flex-1">
						<h2 id={titleId} className="text-lg font-semibold">
							{question.question}
						</h2>
						<p className="mt-1 text-[12px] text-muted-foreground">
							<span>{question.category}</span>
							{!isComingSoon && (
								<>
									<span aria-hidden="true"> · </span>
									<span>{question.plan === 'free' ? __('Free', 'statnive') : __('Paid', 'statnive')}</span>
								</>
							)}
						</p>
					</div>
					<button
						ref={closeRef}
						type="button"
						onClick={onClose}
						aria-label={__('Close answer', 'statnive')}
						className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
					>
						<X className="h-4 w-4" />
					</button>
				</div>

				{/* Body */}
				<div id={bodyId} className="p-6">
					{isComingSoon ? (
						<p className="text-sm text-muted-foreground">{reasonCaption}</p>
					) : isLoading || !answer ? (
						<div className="h-20 animate-pulse rounded bg-muted" />
					) : answer.status === 'error' ? (
						<p className="text-sm text-destructive">
							{__("Couldn't load this answer.", 'statnive')}
						</p>
					) : (
						<AnswerViz question={question} answer={answer} />
					)}
				</div>

				{/* Footer */}
				{!isComingSoon && (
					<div className="flex items-center justify-between border-t border-border p-6 pt-4">
						<button
							type="button"
							onClick={togglePin}
							aria-pressed={pinned}
							disabled={isPending}
							className={cn(
								'flex items-center gap-1.5 rounded px-2 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/30',
								pinned
									? 'text-primary'
									: 'text-muted-foreground hover:bg-muted hover:text-foreground',
							)}
						>
							<Pin
								className={cn(
									'h-4 w-4',
									pinned ? 'fill-primary text-primary' : 'text-muted-foreground',
								)}
							/>
							{pinned
								? __('Pinned', 'statnive')
								: __('Pin this question', 'statnive')}
						</button>
					</div>
				)}
			</div>
		</div>
	);
}
