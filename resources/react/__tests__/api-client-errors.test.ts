import { describe, it, expect, vi, beforeEach } from 'vitest';
import { apiGet } from '@/lib/api-client';

describe('apiGet error handling', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('throws with status in error when fetch returns 500', async () => {
		global.fetch = vi.fn().mockResolvedValue({
			ok: false,
			status: 500,
			statusText: 'Internal Server Error',
		});

		await expect(apiGet('summary')).rejects.toThrow('API error: 500 Internal Server Error');
	});

	it('throws with 403 when nonce is expired', async () => {
		global.fetch = vi.fn().mockResolvedValue({
			ok: false,
			status: 403,
			statusText: 'Forbidden',
		});

		await expect(apiGet('summary')).rejects.toThrow('API error: 403 Forbidden');
	});

	it('propagates TypeError on network failure', async () => {
		global.fetch = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));

		await expect(apiGet('summary')).rejects.toThrow('Failed to fetch');
	});

	it('throws when fetch returns non-JSON response', async () => {
		global.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.reject(new SyntaxError('Unexpected token')),
		});

		await expect(apiGet('summary')).rejects.toThrow('Unexpected token');
	});

	it('throws with 429 when rate limited', async () => {
		global.fetch = vi.fn().mockResolvedValue({
			ok: false,
			status: 429,
			statusText: 'Too Many Requests',
		});

		await expect(apiGet('summary')).rejects.toThrow('API error: 429 Too Many Requests');
	});
});
