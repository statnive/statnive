/**
 * Settings helpers for E2E specs.
 *
 * `setSetting` / `setSettings` write via the real `PUT /statnive/v1/settings`
 * endpoint so the sanitize + enum + mode coercion paths in
 * `SettingsController::update_settings()` are exercised end-to-end. We then
 * flush caches so the tracker script picks up the change on the next page
 * load. `snapshotSettings` / `restoreSettings` wrap the debug endpoints
 * mounted by `statnive-e2e-debug.php`.
 */

import type { APIRequestContext, Page } from '@playwright/test';
import { env } from '../env';
import { wpCacheFlush } from '../db-cli';

type SettingKey =
	| 'tracking_enabled'
	| 'respect_dnt'
	| 'respect_gpc'
	| 'consent_mode'
	| 'retention_days'
	| 'retention_mode'
	| 'excluded_ips'
	| 'excluded_roles';

type SettingValue = string | number | boolean | string[];

export async function getDashboardNonce(page: Page): Promise<string> {
	return page.evaluate(
		() => (window as unknown as { StatniveDashboard?: { nonce: string } }).StatniveDashboard?.nonce ?? ''
	);
}

export async function setSettings(
	page: Page,
	patch: Partial<Record<SettingKey, SettingValue>>
): Promise<void> {
	const response = await page.request.put(`${env.restUrl}/statnive/v1/settings`, {
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': await getDashboardNonce(page),
		},
		data: patch,
	});
	if (!response.ok()) {
		throw new Error(`PUT /settings failed: ${response.status()} ${await response.text()}`);
	}
	wpCacheFlush();
}

export async function setSetting(
	page: Page,
	key: SettingKey,
	value: SettingValue
): Promise<void> {
	await setSettings(page, { [key]: value } as Partial<Record<SettingKey, SettingValue>>);
}

async function debugPost(request: APIRequestContext, path: string, body: unknown = {}): Promise<unknown> {
	const response = await request.post(`${env.restUrl}/statnive/v1${path}`, {
		headers: { 'Content-Type': 'application/json' },
		data: body,
	});
	if (!response.ok()) {
		throw new Error(`POST ${path} failed: ${response.status()} ${await response.text()}`);
	}
	return response.json();
}

export async function snapshotSettings(page: Page): Promise<void> {
	await debugPost(page.request, '/debug/settings-snapshot');
}

export async function restoreSettings(page: Page): Promise<void> {
	await debugPost(page.request, '/debug/settings-restore');
	wpCacheFlush();
}

export async function truncateStatnive(page: Page): Promise<void> {
	await debugPost(page.request, '/debug/truncate');
}

export async function backdate(
	page: Page,
	table: string,
	column: string,
	daysAgo: number,
	where: Record<string, string | number> = {}
): Promise<void> {
	await debugPost(page.request, '/debug/backdate', { table, column, days_ago: daysAgo, where });
}

export async function runPurge(page: Page): Promise<void> {
	await debugPost(page.request, '/debug/run-purge');
}

export async function nextScheduled(page: Page, hook: string): Promise<number> {
	const response = await page.request.get(
		`${env.restUrl}/statnive/v1/debug/next-scheduled?hook=${encodeURIComponent(hook)}`
	);
	if (!response.ok()) {
		throw new Error(`GET /next-scheduled failed: ${response.status()}`);
	}
	const json = (await response.json()) as { next: number };
	return json.next;
}

export async function setStubbedConsent(
	page: Page,
	category: string,
	granted: boolean
): Promise<void> {
	await debugPost(page.request, '/debug/consent-stub', { category, granted });
}
