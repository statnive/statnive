/**
 * Node-side DB oracle helpers.
 *
 * Uses `wp db query` under the hood, so it picks up the DB credentials
 * from `wp-config.php` without the test harness needing to know Local's
 * per-site MySQL socket. Complements `./db.ts` which goes through a
 * debug REST endpoint from inside the browser context.
 *
 * All helpers are synchronous because they run in Playwright fixtures
 * (before/after hooks) where blocking the worker is fine and easier to
 * reason about than juggling `await execFileAsync`.
 */

import { execFileSync } from 'node:child_process';
import { env } from './env';

const WP_CWD = env.wpRoot;

function wp(args: string[], opts: { input?: string } = {}): string {
	try {
		return execFileSync('wp', args, {
			cwd: WP_CWD,
			encoding: 'utf8',
			input: opts.input,
			stdio: opts.input ? ['pipe', 'pipe', 'pipe'] : ['ignore', 'pipe', 'pipe'],
		});
	} catch (err) {
		const { stderr, stdout } = err as { stderr?: Buffer | string; stdout?: Buffer | string };
		const msg = [
			`wp ${args.join(' ')} failed`,
			stderr ? `stderr: ${String(stderr).trim()}` : '',
			stdout ? `stdout: ${String(stdout).trim()}` : '',
		]
			.filter(Boolean)
			.join('\n');
		throw new Error(msg);
	}
}

/** Run an arbitrary SQL query and return tab-separated rows as record objects. */
export function dbQuery<T = Record<string, string>>(sql: string): T[] {
	const out = wp(['db', 'query', sql, '--skip-column-names=false', '--batch'], {}).trim();
	if (!out) return [];
	const [header, ...lines] = out.split('\n');
	const cols = header.split('\t');
	return lines.map((line) => {
		const values = line.split('\t');
		const row: Record<string, string> = {};
		cols.forEach((c, i) => {
			row[c] = values[i] ?? '';
		});
		return row as T;
	});
}

/** Count rows matching an optional WHERE clause. */
export function dbCount(table: string, where = ''): number {
	const full = `${env.tablePrefix}${table}`;
	const sql = where
		? `SELECT COUNT(*) AS c FROM \`${full}\` WHERE ${where}`
		: `SELECT COUNT(*) AS c FROM \`${full}\``;
	const rows = dbQuery<{ c: string }>(sql);
	return rows.length ? Number(rows[0].c) : 0;
}

/** Set/clear a single option via WP-CLI. */
export function wpOptionUpdate(key: string, value: string): void {
	wp(['option', 'update', key, value]);
}

/** Flush the object cache (in case settings are cached). */
export function wpCacheFlush(): void {
	try {
		wp(['cache', 'flush']);
	} catch {
		// Non-fatal — persistent object cache may not be installed.
	}
}
