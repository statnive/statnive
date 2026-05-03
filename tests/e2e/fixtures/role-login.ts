/**
 * Logged-in non-admin browser session for role-exclusion specs.
 *
 * The shared `fixtures/auth.ts` storage state only covers admin. This fixture
 * provisions a user via `/debug/ensure-user` (idempotent — the password is
 * reset on every call so the credentials are deterministic) and form-logs
 * them in via wp-login.php in a fresh context.
 */

import type { Browser, Page } from '@playwright/test';
import { env } from '../env';
import { newAnonContext, type AnonContext } from './anon';
import { getDashboardNonce } from './settings';

export type ExcludableRole = 'subscriber' | 'editor' | 'author' | 'contributor';

export type RoleSession = AnonContext;

interface EnsureUserResponse {
	ok: boolean;
	user_id: number;
	user_login: string;
	user_pass: string;
}

async function ensureUser(adminPage: Page, role: ExcludableRole): Promise<EnsureUserResponse> {
	const response = await adminPage.request.post(
		`${env.restUrl}/statnive/v1/debug/ensure-user`,
		{
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': await getDashboardNonce(adminPage),
			},
			data: { role },
		}
	);
	if (!response.ok()) {
		throw new Error(`POST /debug/ensure-user failed: ${response.status()} ${await response.text()}`);
	}
	return (await response.json()) as EnsureUserResponse;
}

export async function loginAsRole(
	browser: Browser,
	adminPage: Page,
	role: ExcludableRole
): Promise<RoleSession> {
	const creds = await ensureUser(adminPage, role);

	const session = await newAnonContext(browser);
	await session.page.goto(`${env.baseUrl}/wp-login.php`);
	await session.page.fill('#user_login', creds.user_login);
	await session.page.fill('#user_pass', creds.user_pass);
	await session.page.click('#wp-submit');
	await session.page.waitForURL(/wp-admin/);

	return session;
}
