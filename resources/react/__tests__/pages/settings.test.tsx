// Generated from BDD scenarios — Feature: Dashboard Detail Pages — Settings screen

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

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

describe('SettingsPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
		mockUpdate.mockClear();
	});

	// REQ-1.23 — Consent mode toggle switches between 3 privacy modes
	it('renders 3 consent mode radio options with Cookieless marked as Recommended', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.getByText('Cookieless')).toBeInTheDocument();
		expect(screen.getByText('Full Tracking')).toBeInTheDocument();
		expect(screen.getByText('Disabled Until Consent')).toBeInTheDocument();
		expect(screen.getByText('Recommended')).toBeInTheDocument();
	});

	// REQ-1.23 — Selecting Disabled Until Consent persists the setting
	it('calls update with consent_mode "disabled-until-consent" when that radio is selected', async () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });
		const user = userEvent.setup();

		render(<SettingsPage />);

		// The radio inputs are wrapped in <label> elements. Find via the label text
		// and click the associated radio input.
		const label = screen.getByText('Disabled Until Consent').closest('label');
		const radio = label?.querySelector('input[type="radio"]');
		expect(radio).toBeTruthy();
		await user.click(radio!);

		expect(mockUpdate).toHaveBeenCalledWith({ consent_mode: 'disabled-until-consent' });
	});

	// REQ-1.23 — DNT and GPC checkboxes are present
	it('renders Respect Do Not Track and Respect Global Privacy Control checkboxes', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.getByText('Respect Do Not Track')).toBeInTheDocument();
		expect(screen.getByText('Respect Global Privacy Control')).toBeInTheDocument();
	});

	// REQ-1.25 — Data retention dropdown persists selection
	it('renders data retention dropdown with 4 options and calls update when changed', async () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });
		const user = userEvent.setup();

		render(<SettingsPage />);

		const select = screen.getByDisplayValue('90 days');
		expect(select).toBeInTheDocument();

		await user.selectOptions(select, '365');

		expect(mockUpdate).toHaveBeenCalledWith({ retention_days: 365 });
	});

	// REQ-1.25 — All retention options available
	it('displays all 4 retention period options: 30 days, 90 days, 1 year, Forever', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		const options = screen.getAllByRole('option');
		const retentionOptions = options.map((o) => o.textContent);
		expect(retentionOptions).toContain('30 days');
		expect(retentionOptions).toContain('90 days');
		expect(retentionOptions).toContain('1 year');
		expect(retentionOptions).toContain('Forever');
	});

	// Settings loading state
	it('shows skeleton loading state while settings are fetching', () => {
		mockUseSettings.mockReturnValue({ data: null, isLoading: true });

		const { container } = render(<SettingsPage />);

		const skeletons = container.querySelectorAll('.animate-pulse');
		expect(skeletons.length).toBeGreaterThan(0);
	});
});
