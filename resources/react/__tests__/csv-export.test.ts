import { describe, it, expect, vi } from 'vitest';
import { exportCsv, buildCsvFilename } from '@/lib/csv-export';

describe('buildCsvFilename', () => {
	it('builds correct filename', () => {
		expect(buildCsvFilename('pages', '2026-03-01', '2026-03-07')).toBe(
			'statnive-pages-2026-03-01-2026-03-07.csv',
		);
	});
});

describe('exportCsv', () => {
	it('does not trigger download for empty data', () => {
		const createSpy = vi.spyOn(document, 'createElement');
		exportCsv([], [{ key: 'name', label: 'Name' }], 'test.csv');
		expect(createSpy).not.toHaveBeenCalledWith('a');
		createSpy.mockRestore();
	});

	it('creates correct CSV content', () => {
		const originalCreateObjectURL = URL.createObjectURL;
		URL.createObjectURL = vi.fn(() => 'blob:test');
		URL.revokeObjectURL = vi.fn();

		// Mock link click.
		const mockLink = { href: '', download: '', style: { display: '' }, click: vi.fn() };
		vi.spyOn(document, 'createElement').mockReturnValue(mockLink as unknown as HTMLElement);
		vi.spyOn(document.body, 'appendChild').mockImplementation((node) => node);
		vi.spyOn(document.body, 'removeChild').mockImplementation((node) => node);

		const data = [
			{ name: 'Page A', visitors: 100 },
			{ name: 'Page B', visitors: 50 },
		];

		exportCsv(
			data,
			[
				{ key: 'name', label: 'Page' },
				{ key: 'visitors', label: 'Visitors' },
			],
			'test.csv',
		);

		expect(mockLink.click).toHaveBeenCalled();
		expect(mockLink.download).toBe('test.csv');

		vi.restoreAllMocks();
		URL.createObjectURL = originalCreateObjectURL;
	});
});
