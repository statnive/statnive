import { describe, it, expect, vi, beforeEach } from 'vitest';
import { apiGet, apiPut } from '@/lib/api-client';

describe('apiGet', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('sends GET with nonce header', async () => {
		const mockResponse = { visitors: 100 };
		global.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve(mockResponse),
		});

		const result = await apiGet<{ visitors: number }>('summary', { from: '2026-01-01', to: '2026-01-07' });

		expect(result).toEqual(mockResponse);
		expect(global.fetch).toHaveBeenCalledTimes(1);

		const [url, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0]!;
		expect(url).toContain('summary');
		expect(url).toContain('from=2026-01-01');
		expect(options.headers['X-WP-Nonce']).toBe('test-nonce-123');
	});

	it('throws on non-ok response', async () => {
		global.fetch = vi.fn().mockResolvedValue({
			ok: false,
			status: 403,
			statusText: 'Forbidden',
		});

		await expect(apiGet('summary')).rejects.toThrow('API error: 403 Forbidden');
	});
});

describe('apiPut', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('sends PUT with JSON body and nonce', async () => {
		const mockResponse = { success: true };
		global.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve(mockResponse),
		});

		const body = { consent_mode: 'cookieless' };
		const result = await apiPut('settings', body);

		expect(result).toEqual(mockResponse);
		const [, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0]!;
		expect(options.method).toBe('PUT');
		expect(options.headers['Content-Type']).toBe('application/json');
		expect(options.headers['X-WP-Nonce']).toBe('test-nonce-123');
		expect(JSON.parse(options.body)).toEqual(body);
	});
});
