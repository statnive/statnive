/**
 * Register Statnive pages in the WordPress Command Palette.
 *
 * Uses wp.data.dispatch('core/commands') when available (WP 6.3+).
 */

import { __ } from '@wordpress/i18n';

interface WpCommand {
	name: string;
	label: string;
	icon?: string;
	callback: () => void;
}

export function registerWpCommands(navigateTo: (path: string) => void): void {
	// wp.data may not be available in all WP versions.
	const wp = (window as unknown as Record<string, unknown>).wp as
		| { data?: { dispatch?: (store: string) => { registerCommand?: (cmd: WpCommand) => void } } }
		| undefined;

	const dispatch = wp?.data?.dispatch?.('core/commands');
	if (!dispatch?.registerCommand) return;

	const commands: { name: string; label: string; path: string }[] = [
		{ name: 'statnive/overview', label: __('Statnive: Go to Overview', 'statnive'), path: '/' },
		{ name: 'statnive/pages', label: __('Statnive: Go to Pages', 'statnive'), path: '/pages' },
		{ name: 'statnive/referrers', label: __('Statnive: Go to Referrers', 'statnive'), path: '/referrers' },
		{ name: 'statnive/geography', label: __('Statnive: Go to Geography', 'statnive'), path: '/geography' },
		{ name: 'statnive/devices', label: __('Statnive: Go to Devices', 'statnive'), path: '/devices' },
		{ name: 'statnive/realtime', label: __('Statnive: Go to Real-time', 'statnive'), path: '/realtime' },
		{ name: 'statnive/settings', label: __('Statnive: Go to Settings', 'statnive'), path: '/settings' },
	];

	for (const cmd of commands) {
		dispatch.registerCommand({
			name: cmd.name,
			label: cmd.label,
			callback: () => navigateTo(cmd.path),
		});
	}
}
