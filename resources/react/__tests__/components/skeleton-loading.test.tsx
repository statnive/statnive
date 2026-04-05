// Generated from BDD scenarios — Feature: Dashboard Overview — Skeleton loading (REQ-1.9)

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SkeletonCard } from '@/components/shared/skeleton-card';
import { DataTable, type Column } from '@/components/shared/data-table';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SkeletonCard', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.9 — Skeleton shimmer displays while data is fetching
	it('renders animated pulse placeholders with the specified number of lines', () => {
		const { container } = render(<SkeletonCard lines={3} />);

		const pulseElements = container.querySelectorAll('.animate-pulse');
		expect(pulseElements.length).toBe(3);
	});

	it('defaults to 3 skeleton lines when lines prop is omitted', () => {
		const { container } = render(<SkeletonCard />);

		const pulseElements = container.querySelectorAll('.animate-pulse');
		expect(pulseElements.length).toBe(3);
	});

	it('applies custom className to the outer container', () => {
		const { container } = render(<SkeletonCard className="h-[300px]" />);

		const wrapper = container.firstElementChild;
		expect(wrapper?.className).toContain('h-[300px]');
	});
});

describe('DataTable loading state', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.9 — Data tables show 5 shimmer rows each
	it('renders 5 shimmer rows when isLoading is true', () => {
		const columns: Column<{ name: string }>[] = [
			{ key: 'name', header: 'Name', render: (row) => <span>{row.name}</span> },
		];

		const { container } = render(
			<DataTable
				data={[]}
				columns={columns}
				isLoading={true}
				getRowKey={(_, i) => String(i)}
				title="Test Table"
			/>,
		);

		const shimmerRows = container.querySelectorAll('.animate-pulse');
		expect(shimmerRows.length).toBe(5);
	});

	// Disappears when data arrives
	it('does not render shimmer rows when isLoading is false', () => {
		const columns: Column<{ name: string }>[] = [
			{ key: 'name', header: 'Name', render: (row) => <span>{row.name}</span> },
		];

		const { container } = render(
			<DataTable
				data={[{ name: 'Test' }]}
				columns={columns}
				isLoading={false}
				getRowKey={(row) => row.name}
				title="Test Table"
			/>,
		);

		const shimmerRows = container.querySelectorAll('.animate-pulse');
		expect(shimmerRows.length).toBe(0);
		expect(screen.getByText('Test')).toBeInTheDocument();
	});
});
