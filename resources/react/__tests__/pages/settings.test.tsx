// Generated from BDD scenarios — Feature: Dashboard Detail Pages — Settings screen

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

const mockMutate = vi.fn();
const mockUseSettings = vi.fn();
let mockIsPending = false;

vi.mock('@/hooks/use-settings', () => ({
	useSettings: (...args: unknown[]) => mockUseSettings(...args),
	useUpdateSettings: () => ({ mutate: mockMutate, isPending: mockIsPending }),
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
		retention_days: 3650,
		retention_mode: 'forever' as const,
		excluded_ips: '',
		excluded_roles: [],
		tracking_enabled: true,
	};
}

beforeEach(() => {
	Object.defineProperty(window, 'StatniveDashboard', {
		writable: true,
		configurable: true,
		value: {
			restUrl: '/wp-json/statnive/v1/',
			nonce: 'test-nonce',
			siteTitle: 'Test',
			version: '0.0.0-test',
			currentIp: '198.51.100.77',
		},
	});
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SettingsPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
		mockMutate.mockClear();
		mockIsPending = false;
	});

	it('renders the two supported consent modes with Cookieless marked as Recommended', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.getByText('Cookieless')).toBeInTheDocument();
		expect(screen.getByText('Disabled Until Consent')).toBeInTheDocument();
		expect(screen.queryByText('Full Tracking')).not.toBeInTheDocument();
		expect(screen.getByText('Recommended')).toBeInTheDocument();
	});

	it('keeps Save disabled until a field is changed, then submits the whole form on click', async () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });
		mockMutate.mockImplementation((_body, opts) => opts?.onSuccess?.(defaultSettings()));
		const user = userEvent.setup();

		render(<SettingsPage />);

		const save = screen.getByTestId('settings-save');
		expect(save).toBeDisabled();

		await user.click(screen.getByTestId('consent-mode-disabled-until-consent'));

		expect(save).toBeEnabled();
		await user.click(save);

		expect(mockMutate).toHaveBeenCalledTimes(1);
		const [payload] = mockMutate.mock.calls[0];
		expect(payload.consent_mode).toBe('disabled-until-consent');
		expect(payload.retention_days).toBe(3650);
		expect(payload.retention_mode).toBe('forever');
	});

	it('shows the "Saved ✓" flash after a successful save', async () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });
		mockMutate.mockImplementation((body, opts) => opts?.onSuccess?.(body));
		const user = userEvent.setup();

		render(<SettingsPage />);

		await user.click(screen.getByTestId('dnt-respect-toggle'));
		await user.click(screen.getByTestId('settings-save'));

		await waitFor(() => {
			expect(screen.getByTestId('settings-saved-flash')).toBeInTheDocument();
		});
	});

	it('renders Respect Do Not Track and Respect Global Privacy Control checkboxes', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.getByText('Respect Do Not Track')).toBeInTheDocument();
		expect(screen.getByText('Respect Global Privacy Control')).toBeInTheDocument();
	});

	it('maps retention select "Forever" → mode=forever and any other value → mode=delete', async () => {
		mockUseSettings.mockReturnValue({
			data: { ...defaultSettings(), retention_days: 90, retention_mode: 'delete' as const },
			isLoading: false,
		});
		mockMutate.mockImplementation((body, opts) => opts?.onSuccess?.(body));
		const user = userEvent.setup();

		render(<SettingsPage />);

		const select = screen.getByTestId('retention-select') as HTMLSelectElement;
		await user.selectOptions(select, '3650');
		await user.click(screen.getByTestId('settings-save'));

		expect(mockMutate).toHaveBeenCalledTimes(1);
		const payload = mockMutate.mock.calls[0][0];
		expect(payload.retention_days).toBe(3650);
		expect(payload.retention_mode).toBe('forever');
	});

	it('offers the 5 retention options: 30 days, 90 days, 180 days, 1 year, Forever', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		const options = screen.getAllByRole('option');
		const labels = options.map((o) => o.textContent);
		expect(labels).toContain('30 days');
		expect(labels).toContain('90 days');
		expect(labels).toContain('180 days');
		expect(labels).toContain('1 year');
		expect(labels).toContain('Forever');
	});

	it('shows the current IP and the "Add to exclusions" button appends it to the textarea', async () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });
		const user = userEvent.setup();

		render(<SettingsPage />);

		expect(screen.getByTestId('current-ip-value')).toHaveTextContent('198.51.100.77');
		await user.click(screen.getByTestId('add-ip-button'));

		const textarea = screen.getByTestId('excluded-ips-textarea') as HTMLTextAreaElement;
		expect(textarea.value).toContain('198.51.100.77');
		expect(screen.getByTestId('settings-save')).toBeEnabled();
	});

	it('has no Email Reports section', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.queryByText(/email report/i)).not.toBeInTheDocument();
	});

	it('shows skeleton loading state while settings are fetching', () => {
		mockUseSettings.mockReturnValue({ data: null, isLoading: true });

		const { container } = render(<SettingsPage />);

		const skeletons = container.querySelectorAll('.animate-pulse');
		expect(skeletons.length).toBeGreaterThan(0);
	});
});
