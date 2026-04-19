/**
 * Authenticated Playwright fixture.
 *
 * Re-exports `test` with `use.storageState` preset to the admin session
 * captured by `global-setup.ts`. Specs import from here instead of
 * `@playwright/test` when they need to exercise admin-scoped routes.
 */

import { test as base, expect } from '@playwright/test';

export const test = base.extend({
	storageState: 'tests/e2e/.auth/admin.json',
});

export { expect };
