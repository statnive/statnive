import { __, sprintf } from '@wordpress/i18n';

interface RealtimeCounterProps {
	count: number;
}

export function RealtimeCounter({ count }: RealtimeCounterProps) {
	return (
		<div
			className="flex items-center gap-2 rounded-full bg-green-50 px-3 py-1"
			aria-live="polite"
			aria-label={sprintf(
				/* translators: %d: number of active visitors */
				__('%d active visitors', 'statnive'),
				count,
			)}
		>
			<span className="relative flex h-2 w-2">
				<span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75 motion-reduce:animate-none" />
				<span className="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
			</span>
			<span className="text-sm font-medium tabular-nums text-green-800">
				{count}
			</span>
			<span className="hidden text-xs text-green-600 sm:inline">{__('online', 'statnive')}</span>
		</div>
	);
}
