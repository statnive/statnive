// Edge-case tests for SettingsPage — consent modes, badges, and privacy controls

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

const mockUpdate = vi.fn();
const mockUseSettings = vi.fn();

vi.mock('@/hooks/use-settings', () => ({
	useSettings: (...args: unknown[]) => mockUseSettings(...args),
	useUpdateSettings: () => ({ mutate: mockUpdate }),
}));

import { SettingsPage } from '@/pages/settings';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function defaultSettings() {
	return {
		consent_mode: 'cookieless' as const,
		respect_dnt: true,
		respect_gpc: true,
		retention_days: 90,
		excluded_ips: '',
		excluded_roles: [],
		email_reports: false,
		email_frequency: 'weekly' as const,
		tracking_enabled: true,
	};
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SettingsPage edge cases', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
		mockUpdate.mockClear();
	});

	it('renders all three consent mode radio options', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.getByText('Cookieless')).toBeInTheDocument();
		expect(screen.getByText('Full Tracking')).toBeInTheDocument();
		expect(screen.getByText('Disabled Until Consent')).toBeInTheDocument();
	});

	it('shows "Recommended" badge next to the Cookieless option', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		const recommendedBadge = screen.getByText('Recommended');
		expect(recommendedBadge).toBeInTheDocument();

		// The badge should be near the Cookieless label (within the same parent label)
		const parentLabel = recommendedBadge.closest('label');
		expect(parentLabel).toBeInTheDocument();
		expect(parentLabel?.textContent).toContain('Cookieless');
	});

	it('renders DNT and GPC checkboxes with correct labels', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.getByText('Respect Do Not Track')).toBeInTheDocument();
		expect(screen.getByText('Respect Global Privacy Control')).toBeInTheDocument();

		// Both checkboxes should be checked based on default settings
		const checkboxes = screen.getAllByRole('checkbox');
		const dntCheckbox = checkboxes.find(
			(cb) => cb.closest('label')?.textContent?.includes('Do Not Track'),
		);
		const gpcCheckbox = checkboxes.find(
			(cb) => cb.closest('label')?.textContent?.includes('Global Privacy Control'),
		);

		expect(dntCheckbox).toBeTruthy();
		expect(gpcCheckbox).toBeTruthy();
		expect((dntCheckbox as HTMLInputElement).checked).toBe(true);
		expect((gpcCheckbox as HTMLInputElement).checked).toBe(true);
	});
});
