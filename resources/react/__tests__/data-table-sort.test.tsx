import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent, within } from '@testing-library/react';
import { DataTable, type Column } from '@/components/shared/data-table';

interface Row {
	name: string;
	visitors: unknown;
}

const columns: Column<Row>[] = [
	{ key: 'name', header: 'Name', render: (r) => r.name, sortable: true },
	{
		key: 'visitors',
		header: 'Visitors',
		render: (r) => String(r.visitors),
		sortable: true,
		align: 'right',
	},
];

function rowOrder(): string[] {
	const rows = screen.getAllByRole('row');
	// first row is the header
	return rows.slice(1).map((row) => within(row).getAllByRole('cell')[0].textContent ?? '');
}

describe('DataTable sort comparator', () => {
	it('sorts numeric-string values descending by magnitude, not lexicographically', () => {
		// Regression: WP $wpdb->get_results returns numeric DB columns as strings.
		// DESC string sort would place "82" before "108" because "8" > "1".
		const data: Row[] = [
			{ name: 'Google', visitors: '82' },
			{ name: 'Direct', visitors: '108' },
			{ name: 'Bing', visitors: '7' },
		];

		render(
			<DataTable
				data={data}
				columns={columns}
				defaultSortKey="visitors"
				getRowKey={(r) => r.name}
			/>,
		);

		expect(rowOrder()).toEqual(['Direct', 'Google', 'Bing']);
	});

	it('sorts ascending when toggled', () => {
		const data: Row[] = [
			{ name: 'A', visitors: '42' },
			{ name: 'B', visitors: '100' },
			{ name: 'C', visitors: '7' },
		];

		render(
			<DataTable
				data={data}
				columns={columns}
				defaultSortKey="visitors"
				defaultSortDir="asc"
				getRowKey={(r) => r.name}
			/>,
		);

		expect(rowOrder()).toEqual(['C', 'A', 'B']);
	});

	it('handles mixed number and numeric-string values', () => {
		const data: Row[] = [
			{ name: 'A', visitors: 42 },
			{ name: 'B', visitors: '100' },
			{ name: 'C', visitors: '7' },
		];

		render(
			<DataTable
				data={data}
				columns={columns}
				defaultSortKey="visitors"
				getRowKey={(r) => r.name}
			/>,
		);

		expect(rowOrder()).toEqual(['B', 'A', 'C']);
	});

	it('falls back to localeCompare for non-numeric string columns', () => {
		const data: Row[] = [
			{ name: 'Charlie', visitors: '1' },
			{ name: 'Alice', visitors: '1' },
			{ name: 'Bob', visitors: '1' },
		];

		render(
			<DataTable
				data={data}
				columns={columns}
				defaultSortKey="name"
				defaultSortDir="asc"
				getRowKey={(r) => r.name}
			/>,
		);

		expect(rowOrder()).toEqual(['Alice', 'Bob', 'Charlie']);
	});

	it('toggles direction when the active column header is clicked', () => {
		const data: Row[] = [
			{ name: 'A', visitors: '10' },
			{ name: 'B', visitors: '200' },
			{ name: 'C', visitors: '30' },
		];

		render(
			<DataTable
				data={data}
				columns={columns}
				defaultSortKey="visitors"
				getRowKey={(r) => r.name}
			/>,
		);

		expect(rowOrder()).toEqual(['B', 'C', 'A']);

		fireEvent.click(screen.getByText('Visitors'));
		expect(rowOrder()).toEqual(['A', 'C', 'B']);
	});
});
