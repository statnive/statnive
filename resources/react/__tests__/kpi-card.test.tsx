import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { KpiCard } from '@/components/shared/kpi-card';

describe('KpiCard', () => {
	it('renders label and value', () => {
		render(<KpiCard label="Visitors" value="1,234" />);

		expect(screen.getByText('Visitors')).toBeInTheDocument();
		expect(screen.getByText('1,234')).toBeInTheDocument();
	});

	it('shows positive change indicator', () => {
		render(<KpiCard label="Visitors" value="1,234" change={12.5} />);

		expect(screen.getByText(/12\.5%/)).toBeInTheDocument();
	});

	it('shows negative change indicator', () => {
		render(<KpiCard label="Visitors" value="1,234" change={-5.3} />);

		expect(screen.getByText(/5\.3%/)).toBeInTheDocument();
	});

	it('renders skeleton when loading', () => {
		const { container } = render(<KpiCard label="Visitors" value="" isLoading />);

		// Skeleton elements have animate-pulse class.
		const skeletons = container.querySelectorAll('.animate-pulse');
		expect(skeletons.length).toBeGreaterThan(0);
	});
});
