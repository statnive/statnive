import { useEffect } from 'react';

interface ShortcutMap {
	[key: string]: () => void;
}

/**
 * Global keyboard shortcuts for the dashboard.
 *
 * Only fires when no input/textarea/select is focused.
 */
export function useKeyboardShortcuts(shortcuts: ShortcutMap): void {
	useEffect(() => {
		function handler(event: KeyboardEvent) {
			// Skip if user is typing in an input.
			const tag = (event.target as HTMLElement)?.tagName;
			if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
				return;
			}

			// Skip if modifier keys are held (avoid conflicting with WP admin shortcuts).
			if (event.ctrlKey || event.metaKey || event.altKey) {
				return;
			}

			const fn = shortcuts[event.key];
			if (fn) {
				event.preventDefault();
				fn();
			}
		}

		document.addEventListener('keydown', handler);
		return () => document.removeEventListener('keydown', handler);
	}, [shortcuts]);
}
