// Generated from BDD scenarios — Feature: Dashboard Overview — Keyboard shortcuts (REQ-1.10)

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useKeyboardShortcuts', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.10 — Keyboard shortcut navigates to the correct dashboard tab
	it.each([
		{ key: '1', tab: 'Overview' },
		{ key: '2', tab: 'Pages' },
		{ key: '3', tab: 'Referrers' },
		{ key: '4', tab: 'Geography' },
		{ key: '5', tab: 'Devices' },
		{ key: '6', tab: 'Languages' },
		{ key: '7', tab: 'Real-time' },
		{ key: '8', tab: 'Settings' },
	])('pressing "$key" triggers navigation to $tab', ({ key, tab }) => {
		const handlers: Record<string, ReturnType<typeof vi.fn>> = {};
		const tabs = ['Overview', 'Pages', 'Referrers', 'Geography', 'Devices', 'Languages', 'Real-time', 'Settings'];
		tabs.forEach((_, i) => {
			handlers[String(i + 1)] = vi.fn();
		});

		renderHook(() => useKeyboardShortcuts(handlers));

		const event = new KeyboardEvent('keydown', { key, bubbles: true });
		document.dispatchEvent(event);

		const index = tabs.indexOf(tab);
		expect(handlers[String(index + 1)]).toHaveBeenCalled();
	});

	// Shortcuts should not fire when user is typing in an input
	it('does not fire shortcut when focus is on an input element', () => {
		const handler = vi.fn();
		renderHook(() => useKeyboardShortcuts({ '1': handler }));

		const input = document.createElement('input');
		document.body.appendChild(input);
		input.focus();

		const event = new KeyboardEvent('keydown', { key: '1', bubbles: true });
		Object.defineProperty(event, 'target', { value: input });
		input.dispatchEvent(event);

		expect(handler).not.toHaveBeenCalled();

		document.body.removeChild(input);
	});

	// Shortcuts should not fire with modifier keys
	it('does not fire shortcut when ctrl key is held', () => {
		const handler = vi.fn();
		renderHook(() => useKeyboardShortcuts({ '1': handler }));

		const event = new KeyboardEvent('keydown', { key: '1', ctrlKey: true, bubbles: true });
		document.dispatchEvent(event);

		expect(handler).not.toHaveBeenCalled();
	});
});
