#!/usr/bin/env node
/**
 * extract-react-pot.mjs — merge React/TypeScript i18n strings into the main POT.
 *
 * wp-cli's `wp i18n make-pot` only scans `.js` / `.jsx` and the compiled
 * React bundle is terser-mangled past recognition, so __() calls inside
 * `resources/react/**\/*.{ts,tsx}` never reach languages/statnive.pot.
 * This script closes that gap.
 *
 * It walks the .tsx/.ts tree, parses each file with @babel/parser (TS + JSX),
 * traverses with @babel/traverse, and collects every translation call
 * (__, _n, _x, _nx, esc_html__, esc_attr__, esc_html_e, esc_attr_e) whose
 * text-domain argument is 'statnive' or omitted. Output is merged into the
 * existing POT in-place: pre-existing entries (from PHP) are preserved, and
 * any new React-only msgid is appended at the end of the file.
 *
 * Run after `wp i18n make-pot`. Idempotent — safe to re-run.
 *
 * Usage:
 *   node bin/extract-react-pot.mjs [--check]
 *
 *     --check   Exit 1 if any React string is missing from the POT;
 *               useful as a CI gate. Does not modify the file.
 */

import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';

const traverse = traverseModule.default ?? traverseModule;

const ROOT = dirname(dirname(fileURLToPath(import.meta.url)));
const SCAN_DIR = join(ROOT, 'resources', 'react');
const POT_PATH = join(ROOT, 'languages', 'statnive.pot');
const TEXT_DOMAIN = 'statnive';

const I18N_FUNCS = {
	__: { args: ['msgid'] },
	_e: { args: ['msgid'] },
	esc_html__: { args: ['msgid'] },
	esc_attr__: { args: ['msgid'] },
	esc_html_e: { args: ['msgid'] },
	esc_attr_e: { args: ['msgid'] },
	_x: { args: ['msgid', 'msgctxt'] },
	_ex: { args: ['msgid', 'msgctxt'] },
	esc_html_x: { args: ['msgid', 'msgctxt'] },
	esc_attr_x: { args: ['msgid', 'msgctxt'] },
	_n: { args: ['msgid', 'msgid_plural'] },
	_nx: { args: ['msgid', 'msgid_plural', null, 'msgctxt'] },
};

async function walk(dir, out = []) {
	const entries = await readdir(dir, { withFileTypes: true });
	for (const e of entries) {
		const full = join(dir, e.name);
		if (e.isDirectory()) {
			if (e.name === '__tests__' || e.name === 'node_modules') continue;
			await walk(full, out);
		} else if (e.isFile() && /\.(tsx?|jsx?)$/.test(e.name)) {
			out.push(full);
		}
	}
	return out;
}

function getStringLiteral(node) {
	if (!node) return null;
	if (node.type === 'StringLiteral') return node.value;
	if (
		node.type === 'TemplateLiteral'
		&& node.expressions.length === 0
		&& node.quasis.length === 1
	) {
		return node.quasis[0].value.cooked;
	}
	return null;
}

function entryKey({ msgctxt, msgid, msgid_plural }) {
	return `${msgctxt ?? ''}${msgid}${msgid_plural ?? ''}`;
}

function poEscape(s) {
	return s
		.replace(/\\/g, '\\\\')
		.replace(/"/g, '\\"')
		.replace(/\n/g, '\\n')
		.replace(/\t/g, '\\t')
		.replace(/\r/g, '\\r');
}

function formatEntry(entry) {
	const lines = [];
	for (const ref of [...entry.refs].sort()) lines.push(`#: ${ref}`);
	if (entry.msgctxt != null) lines.push(`msgctxt "${poEscape(entry.msgctxt)}"`);
	lines.push(`msgid "${poEscape(entry.msgid)}"`);
	if (entry.msgid_plural != null) {
		lines.push(`msgid_plural "${poEscape(entry.msgid_plural)}"`);
		lines.push('msgstr[0] ""');
		lines.push('msgstr[1] ""');
	} else {
		lines.push('msgstr ""');
	}
	return lines.join('\n');
}

async function extractFromFile(file, entries) {
	const source = await readFile(file, 'utf8');
	if (!/\b(__|_e|_n|_x|_nx|esc_(html|attr)_[ex]|esc_(html|attr)__)\s*\(/.test(source)) {
		return;
	}
	const ast = parse(source, {
		sourceType: 'module',
		plugins: ['typescript', 'jsx'],
		errorRecovery: true,
		attachComment: false,
	});
	const rel = relative(ROOT, file);
	traverse(ast, {
		CallExpression(path) {
			const callee = path.node.callee;
			let name = null;
			if (callee.type === 'Identifier') name = callee.name;
			else if (callee.type === 'MemberExpression' && callee.property?.type === 'Identifier') name = callee.property.name;
			if (!name || !I18N_FUNCS[name]) return;
			const sig = I18N_FUNCS[name].args;
			const last = path.node.arguments[path.node.arguments.length - 1];
			const domain = last && getStringLiteral(last);
			// Accept calls where the explicit domain is `statnive` OR where no
			// domain literal is present (matches WP-CLI's i18n behaviour for the
			// declared --domain). Reject calls with an unrelated domain.
			if (domain != null && domain !== TEXT_DOMAIN) return;
			const fields = { msgid: null, msgid_plural: null, msgctxt: null };
			for (let i = 0; i < sig.length; i++) {
				const key = sig[i];
				if (!key) continue;
				const value = getStringLiteral(path.node.arguments[i]);
				if (value == null) {
					if (key === 'msgid') return; // dynamic msgid — skip
					continue;
				}
				fields[key] = value;
			}
			if (fields.msgid == null) return;
			const line = path.node.loc?.start.line ?? 0;
			const ref = `${rel}:${line}`;
			const k = entryKey(fields);
			let entry = entries.get(k);
			if (!entry) {
				entry = { ...fields, refs: new Set() };
				entries.set(k, entry);
			}
			entry.refs.add(ref);
		},
	});
}

function parseExistingPotKeys(pot) {
	const lines = pot.split('\n');
	const keys = new Set();
	let i = 0;
	while (i < lines.length) {
		if (lines[i].startsWith('msgid "') && lines[i] !== 'msgid ""') {
			let msgid = lines[i].slice(7, -1);
			let j = i + 1;
			while (j < lines.length && lines[j].startsWith('"')) {
				msgid += lines[j].slice(1, -1);
				j++;
			}
			// Walk backwards to find msgctxt (if present, on the line right above msgid)
			let msgctxt = '';
			for (let k = i - 1; k >= 0; k--) {
				const ln = lines[k].trim();
				if (ln === '' || ln.startsWith('msgstr')) break;
				if (ln.startsWith('msgctxt "')) {
					msgctxt = ln.slice(9, -1);
					break;
				}
				if (ln.startsWith('msgid_plural') || ln.startsWith('msgid ')) break;
			}
			// Walk forward for msgid_plural (if present)
			let plural = '';
			if (j < lines.length && lines[j].startsWith('msgid_plural "')) {
				plural = lines[j].slice(14, -1);
				let k = j + 1;
				while (k < lines.length && lines[k].startsWith('"')) {
					plural += lines[k].slice(1, -1);
					k++;
				}
			}
			keys.add(`${msgctxt}${msgid}${plural}`);
			i = j;
		} else {
			i++;
		}
	}
	return keys;
}

async function main() {
	const check = process.argv.includes('--check');
	const files = await walk(SCAN_DIR);
	const entries = new Map();
	for (const f of files) await extractFromFile(f, entries);
	const pot = await readFile(POT_PATH, 'utf8');
	const existing = parseExistingPotKeys(pot);
	const missing = [];
	for (const [k, entry] of entries) {
		if (!existing.has(k)) missing.push(entry);
	}
	if (missing.length === 0) {
		console.log(`✓ All ${entries.size} React strings already present in POT.`);
		return;
	}
	if (check) {
		console.error(`✗ ${missing.length} React string(s) missing from POT:`);
		for (const e of missing.slice(0, 5)) console.error(`  - ${JSON.stringify(e.msgid)}`);
		if (missing.length > 5) console.error(`  ... +${missing.length - 5} more`);
		process.exit(1);
	}
	const block = ['\n# React/TypeScript-source strings (merged by bin/extract-react-pot.mjs).\n'];
	for (const entry of missing) {
		block.push(formatEntry(entry));
		block.push('');
	}
	const newPot = pot.replace(/\s*$/, '\n') + block.join('\n');
	await writeFile(POT_PATH, newPot, 'utf8');
	console.log(`✓ Merged ${missing.length} React string(s) into ${relative(ROOT, POT_PATH)}.`);
	console.log(`  Total entries now: ${existing.size + missing.length} (was ${existing.size}).`);
}

main().catch((err) => {
	console.error(err);
	process.exit(2);
});
