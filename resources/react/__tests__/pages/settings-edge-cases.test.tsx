// Edge-case tests for SettingsPage — consent modes, badges, privacy controls,
// dirty-state + save-button contract.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
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
			currentIp: '203.0.113.42',
		},
	});
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SettingsPage edge cases', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
		mockMutate.mockClear();
		mockIsPending = false;
	});

	it('shows only Cookieless + Disabled Until Consent (Full Tracking removed)', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		expect(screen.getByText('Cookieless')).toBeInTheDocument();
		expect(screen.getByText('Disabled Until Consent')).toBeInTheDocument();
		expect(screen.queryByText('Full Tracking')).not.toBeInTheDocument();
		expect(screen.getAllByRole('radio')).toHaveLength(2);
	});

	it('shows "Recommended" badge next to the Cookieless option', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		const recommendedBadge = screen.getByText('Recommended');
		expect(recommendedBadge).toBeInTheDocument();

		const parentLabel = recommendedBadge.closest('label');
		expect(parentLabel).toBeInTheDocument();
		expect(parentLabel?.textContent).toContain('Cookieless');
	});

	it('DNT + GPC checkboxes reflect server state and each has a descriptive hint', () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });

		render(<SettingsPage />);

		const dnt = screen.getByTestId('dnt-respect-toggle') as HTMLInputElement;
		const gpc = screen.getByTestId('gpc-respect-toggle') as HTMLInputElement;

		expect(dnt.checked).toBe(true);
		expect(gpc.checked).toBe(true);
		expect(screen.getByText(/DNT signal/)).toBeInTheDocument();
		expect(screen.getByText(/GPC signal/)).toBeInTheDocument();
	});

	it('navigating away without Save discards local edits (Save button returns to disabled)', async () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });
		const user = userEvent.setup();

		const { unmount } = render(<SettingsPage />);
		await user.click(screen.getByTestId('dnt-respect-toggle'));
		expect(screen.getByTestId('settings-save')).toBeEnabled();
		unmount();

		// Remount with the same (server) state — local edits were never saved.
		render(<SettingsPage />);
		const dnt = screen.getByTestId('dnt-respect-toggle') as HTMLInputElement;
		expect(dnt.checked).toBe(true);
		expect(screen.getByTestId('settings-save')).toBeDisabled();
	});

	it('surfaces an inline error when the save mutation fails and keeps local state', async () => {
		mockUseSettings.mockReturnValue({ data: defaultSettings(), isLoading: false });
		mockMutate.mockImplementation((_body, opts) => opts?.onError?.(new Error('server on fire')));
		const user = userEvent.setup();

		render(<SettingsPage />);

		await user.click(screen.getByTestId('gpc-respect-toggle'));
		await user.click(screen.getByTestId('settings-save'));

		expect(screen.getByTestId('settings-error')).toHaveTextContent('server on fire');
		// Still dirty → still retryable.
		expect(screen.getByTestId('settings-save')).toBeEnabled();
	});
});
