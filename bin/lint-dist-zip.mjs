#!/usr/bin/env node
/**
 * lint-dist-zip.mjs — gate the rebuilt plugin ZIP against WP.org's
 * "unexpected files" rejection criteria.
 *
 * Purpose
 * -------
 * WordPress.org's automated submission gate rejects ZIPs that contain files
 * with extensions or names it doesn't expect inside a plugin. v0.4.12 was
 * rejected on first upload because `vendor/maxmind/web-service-common/dev-bin/release.sh`
 * was bundled by composer and not excluded by .distignore. This script
 * catches every file matching the same family of patterns BEFORE the ZIP
 * goes to WordPress.org, so the rejection cycle does not repeat.
 *
 * What it checks (in the ZIP, against the actual shipping bytes)
 * --------------------------------------------------------------
 *  1. Forbidden script extensions: .sh, .bash, .zsh, .fish, .bat, .cmd, .ps1
 *  2. Native binaries: .exe, .dll, .so, .dylib
 *  3. Legacy PHP / archive extensions: .php3, .php4, .php5, .php6, .php7, .phtml, .phar
 *  4. Compressed archives: .zip, .tar, .tar.gz, .tgz, .rar, .7z, .bz2, .xz, .gz
 *  5. Raw SQL / database dumps: .sql, .dump, .sqlite, .db
 *  6. Compiled bytecode: .pyc, .class, .jar, .war
 *  7. Editor / OS junk: .swp, .swo, .bak, .orig, .rej, .DS_Store, Thumbs.db, desktop.ini
 *  8. CI / build configs in vendor: .travis.yml, .gitlab-ci.yml, .appveyor.yml,
 *     .circleci/, .github/, .drone.yml, bitbucket-pipelines, Vagrantfile, Dockerfile
 *  9. PECL C-ext build files: .c, .h, .m4, .w32, package.xml inside vendor/
 * 10. Lock files: composer.lock, package-lock.json, yarn.lock, bun.lock, pnpm-lock.yaml
 * 11. Hidden dot-files at any depth (e.g. .env, .gitignore, .editorconfig)
 *     except a documented whitelist (.htaccess, .well-known/ if present)
 *
 * Each finding is reported with the file path and the rule it matched.
 * Exit 0 if clean; exit 1 if any forbidden file is found.
 *
 * Usage
 * -----
 *   node bin/lint-dist-zip.mjs path/to/statnive-X.Y.Z.zip
 *
 * Or via npm:
 *   npm run lint:dist
 *
 * Wired into the /statnive-release-zip skill's C-14 check.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, statSync } from 'node:fs';
import { basename } from 'node:path';

const FORBIDDEN_EXTENSIONS = [
	// Scripts
	'sh', 'bash', 'zsh', 'fish', 'bat', 'cmd', 'ps1',
	// Native binaries
	'exe', 'dll', 'so', 'dylib',
	// Legacy PHP + PHP archives
	'php3', 'php4', 'php5', 'php6', 'php7', 'phtml', 'phar',
	// Compressed archives
	'zip', 'tar', 'tgz', 'rar', '7z', 'bz2', 'xz', 'gz',
	// Raw SQL / DB
	'sql', 'dump', 'sqlite', 'db',
	// Compiled bytecode
	'pyc', 'class', 'jar', 'war',
	// Editor / OS junk
	'swp', 'swo', 'bak', 'orig', 'rej',
	// PECL C-extension build artefacts
	'c', 'h', 'm4', 'w32',
];

// Patterns matched against the basename of each entry.
const FORBIDDEN_BASENAMES = [
	/^\.DS_Store$/i,
	/^Thumbs\.db$/i,
	/^desktop\.ini$/i,
	/^Vagrantfile$/i,
	/^Dockerfile$/i,
	/^\.dockerignore$/i,
	/^Makefile$/i,
	/^Rakefile$/i,
	/^Gemfile(\.lock)?$/i,
	/^bitbucket-pipelines\.yml$/i,
	/^bun\.lock$/i,
	/^composer\.lock$/i,
	/^package-lock\.json$/i,
	/^yarn\.lock$/i,
	/^pnpm-lock\.yaml$/i,
	/^\.travis\.yml$/i,
	/^\.gitlab-ci\.yml$/i,
	/^\.appveyor\.yml$/i,
	/^\.drone\.yml$/i,
	/^phpstan-baseline\.neon$/i,
	/^phpunit\.xml(\.dist)?$/i,
	/^phpcs\.xml(\.dist)?$/i,
	/^\.phpunit\.result\.cache$/i,
	/^package\.xml$/i, // PECL package manifest
];

// Path-fragment patterns (directory anywhere in the tree).
const FORBIDDEN_PATH_FRAGMENTS = [
	/\/dev-bin\//i,
	/\/\.github\//i,
	/\/\.gitlab\//i,
	/\/\.circleci\//i,
	/\/\.idea\//i,
	/\/\.vscode\//i,
	/\/php4\//i,
	/\/ext\//i, // PECL C-ext source dirs
	/\/__tests__\//i,
	/\/__snapshots__\//i,
	/\/playwright-report\//i,
	/\/test-results\//i,
	/\/coverage\//i,
];

// Whitelist: dotfile paths that may legitimately ship.
// Currently empty — Statnive ships none. Add here if a future need emerges.
const DOTFILE_WHITELIST = [
	// e.g. /^statnive\/\.well-known\//,
	// e.g. /^statnive\/\.htaccess$/,
];

function isDotfile(path) {
	const parts = path.split('/');
	// Strip the leading top-level dir (`statnive/`) — we only care about
	// the in-plugin path, not the wrapping folder.
	if (parts.length > 1) parts.shift();
	for (const segment of parts) {
		if (segment.startsWith('.') && segment !== '' && segment !== '.' && segment !== '..') return true;
	}
	return false;
}

function isWhitelisted(path) {
	return DOTFILE_WHITELIST.some((rx) => rx.test(path));
}

function classify(path) {
	const findings = [];
	const name = basename(path);
	const ext = (name.match(/\.([a-zA-Z0-9]+)$/) || [])[1]?.toLowerCase();

	if (ext && FORBIDDEN_EXTENSIONS.includes(ext)) {
		findings.push(`extension .${ext}`);
	}
	for (const rx of FORBIDDEN_BASENAMES) {
		if (rx.test(name)) {
			findings.push(`basename ${rx}`);
			break;
		}
	}
	for (const rx of FORBIDDEN_PATH_FRAGMENTS) {
		if (rx.test(path)) {
			findings.push(`path fragment ${rx}`);
			break;
		}
	}
	if (isDotfile(path) && !isWhitelisted(path)) {
		findings.push('hidden dotfile (not whitelisted)');
	}
	return findings;
}

function listEntries(zipPath) {
	const out = execFileSync('unzip', ['-Z1', zipPath], { encoding: 'utf8' });
	return out.split('\n').filter((line) => line && !line.endsWith('/'));
}

function main() {
	const zipPath = process.argv[2];
	if (!zipPath) {
		console.error('Usage: node bin/lint-dist-zip.mjs <path-to-zip>');
		process.exit(2);
	}
	if (!existsSync(zipPath)) {
		console.error(`✗ ZIP not found: ${zipPath}`);
		process.exit(2);
	}
	const size = statSync(zipPath).size;
	const entries = listEntries(zipPath);
	console.log(`Scanning ${basename(zipPath)} — ${entries.length} entries, ${size.toLocaleString()} bytes`);

	const offenders = [];
	for (const path of entries) {
		const findings = classify(path);
		if (findings.length) offenders.push({ path, findings });
	}

	if (offenders.length === 0) {
		console.log('✓ No forbidden files. ZIP is WP.org-safe.');
		process.exit(0);
	}

	console.error(`\n✗ ${offenders.length} forbidden file(s) detected:\n`);
	for (const o of offenders.slice(0, 50)) {
		console.error(`  ${o.path}`);
		for (const f of o.findings) console.error(`    └─ ${f}`);
	}
	if (offenders.length > 50) console.error(`  ... +${offenders.length - 50} more`);
	console.error('\nFix: extend statnive/.distignore to exclude the matched patterns, then rebuild the ZIP.');
	process.exit(1);
}

main();
