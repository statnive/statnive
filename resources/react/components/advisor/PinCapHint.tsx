import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Toast that fires when the user tries to pin a question while already
 * at the Free-tier cap. Listens for the `statnive:advisor-pin-cap`
 * CustomEvent that `useAdvisorPinMutations.pin()` dispatches on cap.
 *
 * Mount once at the page root so the toast stays in the same screen
 * position regardless of which tab (or modal) the user clicked from.
 */
const GROWTH_TIER_PINS = 50;
const DISMISS_AFTER_MS = 5000;

interface CapDetail {
	current: number;
	max: number;
}

export function PinCapHint() {
	const [detail, setDetail] = useState<CapDetail | null>(null);

	useEffect(() => {
		const handler = (e: Event) => {
			const ce = e as CustomEvent<CapDetail>;
			if (!ce.detail) return;
			setDetail(ce.detail);
		};
		window.addEventListener('statnive:advisor-pin-cap', handler);
		return () => window.removeEventListener('statnive:advisor-pin-cap', handler);
	}, []);

	useEffect(() => {
		if (!detail) return;
		const t = window.setTimeout(() => setDetail(null), DISMISS_AFTER_MS);
		return () => window.clearTimeout(t);
	}, [detail]);

	if (!detail) return null;

	return (
		<div
			role="status"
			aria-live="polite"
			className="fixed inset-x-0 top-12 z-50 mx-auto flex w-full max-w-md justify-center px-4"
		>
			<div className="pointer-events-auto w-full rounded-md border border-border bg-card px-5 py-3 shadow-md">
				<p className="text-sm font-semibold text-foreground">
					{sprintf(
						/* translators: %1$d: current pinned count, %2$d: max */
						__('%1$d / %2$d pinned.', 'statnive'),
						detail.current,
						detail.max,
					)}
				</p>
				<p className="mt-1 text-[13px] text-muted-foreground">
					{sprintf(
						/* translators: %d: pin ceiling on the Growth tier */
						__('Unlocks %d pins in Statnive Growth v2.', 'statnive'),
						GROWTH_TIER_PINS,
					)}
				</p>
			</div>
		</div>
	);
}
