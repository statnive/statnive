/**
 * CSV export utility.
 *
 * Generates CSV from table data and triggers browser download.
 */

interface CsvColumn<T> {
	key: keyof T;
	label: string;
}

export function exportCsv<T extends Record<string, unknown>>(
	data: T[],
	columns: CsvColumn<T>[],
	filename: string,
): void {
	if (data.length === 0) return;

	const header = columns.map((col) => escapeCsvField(col.label)).join(',');

	const rows = data.map((row) =>
		columns
			.map((col) => {
				const value = row[col.key];
				return escapeCsvField(String(value ?? ''));
			})
			.join(','),
	);

	const csv = [header, ...rows].join('\n');
	const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
	const url = URL.createObjectURL(blob);

	const link = document.createElement('a');
	link.href = url;
	link.download = filename;
	link.style.display = 'none';
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
	URL.revokeObjectURL(url);
}

function escapeCsvField(field: string): string {
	if (field.includes(',') || field.includes('"') || field.includes('\n')) {
		return `"${field.replace(/"/g, '""')}"`;
	}
	return field;
}

/**
 * Build a filename for CSV exports.
 *
 * @example buildCsvFilename('pages', '2026-03-01', '2026-03-07') → 'statnive-pages-2026-03-01-2026-03-07.csv'
 */
export function buildCsvFilename(screen: string, from: string, to: string): string {
	return `statnive-${screen}-${from}-${to}.csv`;
}
