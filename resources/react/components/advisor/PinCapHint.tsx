import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Pin } from 'lucide-react';

/**
 * Toast that fires when the user tries to pin a question while already
 * at the Free-tier cap. Listens for the `statnive:advisor-pin-cap`
 * CustomEvent that `useAdvisorPinMutations.pin()` dispatches on cap.
 *
 * Mount once at the page root so the toast stays in the same screen
 * position regardless of which tab (or modal) the user clicked from.
 */
const GROWTH_TIER_PINS = 50;
const VISIBLE_MS = 4200;
const EXIT_MS = 180;

interface CapDetail {
	current: number;
	max: number;
}

type Phase = 'in' | 'out';

export function PinCapHint() {
	const [detail, setDetail] = useState<CapDetail | null>(null);
	const [phase, setPhase] = useState<Phase>('in');

	useEffect(() => {
		const handler = (e: Event) => {
			const ce = e as CustomEvent<CapDetail>;
			if (!ce.detail) return;
			setDetail(ce.detail);
			setPhase('in');
		};
		window.addEventListener('statnive:advisor-pin-cap', handler);
		return () => window.removeEventListener('statnive:advisor-pin-cap', handler);
	}, []);

	useEffect(() => {
		if (!detail) return;
		const startExit = window.setTimeout(() => setPhase('out'), VISIBLE_MS);
		const unmount = window.setTimeout(() => setDetail(null), VISIBLE_MS + EXIT_MS);
		return () => {
			window.clearTimeout(startExit);
			window.clearTimeout(unmount);
		};
	}, [detail]);

	if (!detail) return null;

	const isEntering = phase === 'in';
	const animation = isEntering
		? `statnive-toast-in 220ms cubic-bezier(0.25, 1, 0.5, 1) both`
		: `statnive-toast-out ${EXIT_MS}ms cubic-bezier(0.55, 0, 0.7, 0.2) both`;

	return (
		<div
			role="status"
			aria-live="polite"
			className="pointer-events-none fixed inset-x-0 top-14 z-50 mx-auto flex justify-center px-4"
		>
			<div
				className="pointer-events-auto inline-flex max-w-md items-start gap-3 rounded-md border border-border bg-card px-4 py-3 shadow-md"
				style={{ animation, willChange: 'opacity, transform' }}
			>
				<Pin
					aria-hidden="true"
					className="mt-0.5 h-4 w-4 shrink-0 fill-[color:var(--color-accent)] text-[color:var(--color-accent)]"
				/>
				<div className="min-w-0">
					<p className="text-sm font-semibold leading-tight text-foreground">
						{sprintf(
							/* translators: %1$d: current pinned count, %2$d: cap */
							__('You’re at %1$d of %2$d pins.', 'statnive'),
							detail.current,
							detail.max,
						)}
					</p>
					<p className="mt-1 text-[13px] leading-snug text-muted-foreground">
						{sprintf(
							/* translators: %d: pin ceiling on the Growth tier */
							__('Unpin one to add another, or get %d pins with Statnive Growth v2.', 'statnive'),
							GROWTH_TIER_PINS,
						)}
					</p>
				</div>
			</div>
		</div>
	);
}
