/**
 * Test mock for @wordpress/i18n.
 *
 * Returns strings as-is so assertions match the source text.
 */

export function __(text: string, _domain?: string): string {
	return text;
}

export function _x(text: string, _context: string, _domain?: string): string {
	return text;
}

export function _n(single: string, plural: string, number: number, _domain?: string): string {
	return number === 1 ? single : plural;
}

export function _nx(
	single: string,
	plural: string,
	number: number,
	_context: string,
	_domain?: string,
): string {
	return number === 1 ? single : plural;
}

export function sprintf(format: string, ...args: unknown[]): string {
	let i = 0;
	// Handle positional (%1$s, %2$s) and simple (%s, %d, %%) placeholders
	// in a single pass so positional markers are not partially consumed.
	return format.replace(/%(?:(\d+)\$[sd]|[sd%])/g, (match, pos?: string) => {
		if (match === '%%') return '%';
		if (pos !== undefined) {
			const idx = Number(pos) - 1;
			return args[idx] !== undefined ? String(args[idx]) : match;
		}
		const arg = args[i++];
		return arg !== undefined ? String(arg) : match;
	});
}
