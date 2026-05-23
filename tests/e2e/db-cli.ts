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

function mysqlArgs(): string[] {
	const base = ['-uroot', '-proot', 'local', '--batch'];
	if (env.mysqlSocket) {
		return [`--socket=${env.mysqlSocket}`, ...base];
	}
	return base;
}

function runMysql(sql: string): string {
	try {
		return execFileSync('mysql', mysqlArgs(), {
			input: sql,
			encoding: 'utf8',
			stdio: ['pipe', 'pipe', 'pipe'],
		});
	} catch (err) {
		const { stderr, stdout } = err as { stderr?: Buffer | string; stdout?: Buffer | string };
		throw new Error(
			['mysql failed', stderr ? `stderr: ${String(stderr).trim()}` : '', stdout ? `stdout: ${String(stdout).trim()}` : '']
				.filter(Boolean)
				.join('\n')
		);
	}
}

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
	// Strip exactly one trailing newline. trim() also kills the row-separator
	// newline preceding an empty-string value, dropping rows like
	// `option_value\n\n` (one row, value '') to no rows at all.
	const out = runMysql(sql).replace(/\n$/, '');
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

/**
 * Set a single option directly in wp_options.
 *
 * Uses mysql over the Local socket instead of `wp option update` because
 * wp-cli can't reach Local's per-site MySQL socket without PHP ini overrides.
 */
export function wpOptionUpdate(key: string, value: string): void {
	const sql = `INSERT INTO wp_options (option_name, option_value, autoload) VALUES (${sqlQuote(
		key
	)}, ${sqlQuote(value)}, 'yes') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value);`;
	runMysql(sql);
}

/** Flush the object cache (no-op if no persistent cache is installed). */
export function wpCacheFlush(): void {
	try {
		wp(['cache', 'flush']);
	} catch {
		// Non-fatal — persistent object cache may not be installed.
	}
}

function sqlQuote(v: string): string {
	return `'${v.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}
