/**
 * Playwright global teardown.
 *
 * Removes the E2E mu-plugins copied by `global-setup.ts`. Idempotent
 * and tolerant of partial runs. We do NOT touch the existing
 * `ground-truth.php` or anything else in `wp-content/mu-plugins/`.
 */

import { existsSync, unlinkSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { env } from './env';

const SITE_MU_DIR = resolve(env.wpRoot, 'wp-content/mu-plugins');

const E2E_MU_FILES = [
	'statnive-ip-filter.php',
	'statnive-consent-stub.php',
	'statnive-e2e-debug.php',
];

export default async function globalTeardown(): Promise<void> {
	for (const file of E2E_MU_FILES) {
		const path = join(SITE_MU_DIR, file);
		if (existsSync(path)) {
			unlinkSync(path);
		}
	}
}
