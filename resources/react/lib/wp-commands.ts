/**
 * Register Statnive pages in the WordPress Command Palette.
 *
 * Uses wp.data.dispatch('core/commands') when available (WP 6.3+).
 */

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
		{ name: 'statnive/overview', label: 'Statnive: Go to Overview', path: '/' },
		{ name: 'statnive/pages', label: 'Statnive: Go to Pages', path: '/pages' },
		{ name: 'statnive/referrers', label: 'Statnive: Go to Referrers', path: '/referrers' },
		{ name: 'statnive/geography', label: 'Statnive: Go to Geography', path: '/geography' },
		{ name: 'statnive/devices', label: 'Statnive: Go to Devices', path: '/devices' },
		{ name: 'statnive/realtime', label: 'Statnive: Go to Real-time', path: '/realtime' },
		{ name: 'statnive/settings', label: 'Statnive: Go to Settings', path: '/settings' },
	];

	for (const cmd of commands) {
		dispatch.registerCommand({
			name: cmd.name,
			label: cmd.label,
			callback: () => navigateTo(cmd.path),
		});
	}
}
