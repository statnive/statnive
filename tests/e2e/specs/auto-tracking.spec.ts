// Generated from BDD scenarios — adjust selectors and routes for actual implementation.
// Source: features/08-custom-events-engagement.feature @REQ-5.3, @REQ-5.4, @REQ-5.5, @REQ-5.6

import { test, expect } from '@playwright/test';
import { env } from '../env';

test.describe('Auto-Tracking Events', () => {
	test('clicking external link fires Outbound_Link event', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const eventRequests: Array<{ body: string }> = [];
		await page.route('**/statnive/v1/event', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});
		await page.route('**/statnive/v1/hit', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Inject an external link into the page.
		await page.evaluate(() => {
			const link = document.createElement('a');
			link.href = 'https://external-tool.io/pricing';
			link.textContent = 'External Tool';
			link.id = 'test-external-link';
			document.body.appendChild(link);
		});

		// Click the external link — prevent navigation to stay on page.
		await page.evaluate(() => {
			const link = document.getElementById('test-external-link');
			if (link) {
				link.addEventListener('click', (e) => e.preventDefault(), { once: true });
				link.click();
			}
		});

		// Wait for the event request to be sent after the click.
		await page.waitForResponse(
			(res) =>
				(res.url().includes('statnive/v1/event') || res.url().includes('statnive/v1/hit')) &&
				(res.status() === 204 || res.status() === 200),
			{ timeout: 5000 }
		).catch(() => {
			// Event may not fire if auto-tracking is not enabled.
		});

		// Check that an outbound event was dispatched.
		const outboundEvents = eventRequests.filter((r) => {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(r.body || '{}');
			} catch {
				return false;
			}
			return body.event_name === 'Outbound_Link' || (body.properties && (body.properties as Record<string, unknown>).url && String((body.properties as Record<string, unknown>).url).includes('external'));
		});

		expect(outboundEvents.length).toBeGreaterThanOrEqual(1);

		if (outboundEvents.length > 0) {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(outboundEvents[0].body || '{}');
			} catch {
				// Non-JSON payload; skip detail assertions.
			}
			if (body.properties) {
				expect((body.properties as Record<string, unknown>)).toHaveProperty('url');
				expect(String((body.properties as Record<string, unknown>).url)).toContain('external-tool.io');
			}
		}
	});

	test('clicking .pdf download link fires File_Download event', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const eventRequests: Array<{ body: string }> = [];
		await page.route('**/statnive/v1/event', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});
		await page.route('**/statnive/v1/hit', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Inject a PDF download link.
		await page.evaluate(() => {
			const link = document.createElement('a');
			link.href = '/wp-content/uploads/guide.pdf';
			link.textContent = 'Download Guide';
			link.id = 'test-pdf-link';
			document.body.appendChild(link);
		});

		await page.evaluate(() => {
			const link = document.getElementById('test-pdf-link');
			if (link) {
				link.addEventListener('click', (e) => e.preventDefault(), { once: true });
				link.click();
			}
		});

		// Wait for the event request to be sent after the click.
		await page.waitForResponse(
			(res) =>
				(res.url().includes('statnive/v1/event') || res.url().includes('statnive/v1/hit')) &&
				(res.status() === 204 || res.status() === 200),
			{ timeout: 5000 }
		).catch(() => {
			// Event may not fire if auto-tracking is not enabled.
		});

		const downloadEvents = eventRequests.filter((r) => {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(r.body || '{}');
			} catch {
				return false;
			}
			return body.event_name === 'File_Download';
		});

		expect(downloadEvents.length).toBeGreaterThanOrEqual(1);

		if (downloadEvents.length > 0) {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(downloadEvents[0].body || '{}');
			} catch {
				// Non-JSON payload; skip detail assertions.
			}
			if (body.properties) {
				expect((body.properties as Record<string, unknown>)).toHaveProperty('type', 'pdf');
			}
		}
	});

	test('submitting a form fires Form_Submit event', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const eventRequests: Array<{ body: string }> = [];
		await page.route('**/statnive/v1/event', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});
		await page.route('**/statnive/v1/hit', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Inject a test form.
		await page.evaluate(() => {
			const form = document.createElement('form');
			form.id = 'contact-form';
			form.action = '/wp-admin/admin-post.php';
			form.method = 'post';

			const input = document.createElement('input');
			input.type = 'text';
			input.name = 'name';
			input.value = 'Test';
			form.appendChild(input);

			const submit = document.createElement('button');
			submit.type = 'submit';
			submit.textContent = 'Submit';
			form.appendChild(submit);

			document.body.appendChild(form);
		});

		// Submit form but prevent actual navigation.
		await page.evaluate(() => {
			const form = document.getElementById('contact-form') as HTMLFormElement;
			if (form) {
				form.addEventListener('submit', (e) => e.preventDefault(), { once: true });
				form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
			}
		});

		// Wait for the event request to be sent after the form submit.
		await page.waitForResponse(
			(res) =>
				(res.url().includes('statnive/v1/event') || res.url().includes('statnive/v1/hit')) &&
				(res.status() === 204 || res.status() === 200),
			{ timeout: 5000 }
		).catch(() => {
			// Event may not fire if auto-tracking is not enabled.
		});

		const formEvents = eventRequests.filter((r) => {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(r.body || '{}');
			} catch {
				return false;
			}
			return body.event_name === 'Form_Submit';
		});

		expect(formEvents.length).toBeGreaterThanOrEqual(1);

		if (formEvents.length > 0) {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(formEvents[0].body || '{}');
			} catch {
				// Non-JSON payload; skip detail assertions.
			}
			if (body.properties) {
				expect((body.properties as Record<string, unknown>)).toHaveProperty('id', 'contact-form');
			}
		}
	});

	test('CSS class-based event fires on element click', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const eventRequests: Array<{ body: string }> = [];
		await page.route('**/statnive/v1/event', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});
		await page.route('**/statnive/v1/hit', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Inject an element with Statnive CSS event classes.
		await page.evaluate(() => {
			const button = document.createElement('button');
			button.className = 'btn statnive-event-name=Signup statnive-event-plan--pro';
			button.textContent = 'Sign Up';
			button.id = 'test-css-event-btn';
			document.body.appendChild(button);
		});

		await page.click('#test-css-event-btn');

		// Wait for the event request to be sent after the click.
		await page.waitForResponse(
			(res) =>
				(res.url().includes('statnive/v1/event') || res.url().includes('statnive/v1/hit')) &&
				(res.status() === 204 || res.status() === 200),
			{ timeout: 5000 }
		).catch(() => {
			// Event may not fire if auto-tracking is not enabled.
		});

		const signupEvents = eventRequests.filter((r) => {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(r.body || '{}');
			} catch {
				return false;
			}
			return body.event_name === 'Signup';
		});

		expect(signupEvents.length).toBeGreaterThanOrEqual(1);

		if (signupEvents.length > 0) {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(signupEvents[0].body || '{}');
			} catch {
				// Non-JSON payload; skip detail assertions.
			}
			if (body.properties) {
				expect((body.properties as Record<string, unknown>)).toHaveProperty('plan', 'pro');
			}
		}
	});
});
