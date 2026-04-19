import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { getCurrentIp } from '@/lib/api-client';
import { useSettings, useUpdateSettings } from '@/hooks/use-settings';
import { HEADING_H2, HEADING_H3 } from '@/lib/typography';
import type { SettingsState } from '@/types/api';

const FOREVER_DAYS = 3650;
const SAVED_FLASH_MS = 2000;

const RETENTION_OPTIONS: { value: number; label: () => string }[] = [
	{ value: 30, label: () => __('30 days', 'statnive') },
	{ value: 90, label: () => __('90 days', 'statnive') },
	{ value: 180, label: () => __('180 days', 'statnive') },
	{ value: 365, label: () => __('1 year', 'statnive') },
	{ value: FOREVER_DAYS, label: () => __('Forever', 'statnive') },
];

function appendIp(existing: string, ip: string): string {
	const lines = existing.split('\n').map((l) => l.trim()).filter(Boolean);
	if (lines.includes(ip)) {
		return existing;
	}
	const trimmed = existing.replace(/\s+$/, '');
	return trimmed === '' ? ip : `${trimmed}\n${ip}`;
}

function shallowEqualValue(a: unknown, b: unknown): boolean {
	if (Array.isArray(a) && Array.isArray(b)) {
		return a.length === b.length && a.every((v, i) => v === b[i]);
	}
	return a === b;
}

export function SettingsPage() {
	const { data: settings, isLoading } = useSettings();
	const { mutate, isPending } = useUpdateSettings();

	const [form, setForm] = useState<SettingsState | null>(null);
	const [showSaved, setShowSaved] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const savedTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

	useEffect(() => {
		if (settings && !form) {
			setForm(settings);
		}
	}, [settings, form]);

	useEffect(() => {
		return () => {
			if (savedTimer.current) {
				clearTimeout(savedTimer.current);
			}
		};
	}, []);

	const isDirty =
		form !== null &&
		settings !== undefined &&
		(Object.keys(settings) as (keyof SettingsState)[]).some(
			(k) => !shallowEqualValue(form[k], settings[k])
		);

	const currentIp = getCurrentIp();

	if (isLoading || !form) {
		return (
			<div className="space-y-4">
				<h2 className={HEADING_H2}>{__('Settings', 'statnive')}</h2>
				{Array.from({ length: 4 }).map((_, i) => (
					<div key={i} className="h-24 animate-pulse rounded-lg border border-border bg-card" />
				))}
			</div>
		);
	}

	const patch = (changes: Partial<SettingsState>) => {
		setForm((prev) => (prev ? { ...prev, ...changes } : prev));
		if (error) setError(null);
		if (showSaved) setShowSaved(false);
	};

	const handleSave = () => {
		if (!form || !isDirty || isPending) return;
		setError(null);
		mutate(form, {
			onSuccess: (saved) => {
				setForm(saved);
				setShowSaved(true);
				if (savedTimer.current) clearTimeout(savedTimer.current);
				savedTimer.current = setTimeout(() => setShowSaved(false), SAVED_FLASH_MS);
			},
			onError: (err: unknown) => {
				setError(err instanceof Error ? err.message : __('Save failed.', 'statnive'));
			},
		});
	};

	const handleRetentionChange = (days: number) => {
		patch({
			retention_days: days,
			retention_mode: days === FOREVER_DAYS ? 'forever' : 'delete',
		});
	};

	const handleAddCurrentIp = () => {
		if (!currentIp) return;
		patch({ excluded_ips: appendIp(form.excluded_ips, currentIp) });
	};

	return (
		<div className="space-y-6">
			<div className="flex items-center justify-between gap-4">
				<h2 className={HEADING_H2}>{__('Settings', 'statnive')}</h2>
				<div className="flex items-center gap-3">
					{showSaved && (
						<span
							data-testid="settings-saved-flash"
							className="text-sm text-revenue-dark"
							role="status"
						>
							{__('Saved ✓', 'statnive')}
						</span>
					)}
					{error && (
						<span data-testid="settings-error" className="text-sm text-destructive" role="alert">
							{error}
						</span>
					)}
					<button
						data-testid="settings-save"
						type="button"
						onClick={handleSave}
						disabled={!isDirty || isPending}
						className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow disabled:cursor-not-allowed disabled:opacity-50"
					>
						{isPending ? __('Saving…', 'statnive') : __('Save', 'statnive')}
					</button>
				</div>
			</div>

			{/* Privacy */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className={`mb-4 ${HEADING_H3}`}>{__('Privacy', 'statnive')}</h3>
				<div className="space-y-3">
					<label className="flex cursor-pointer items-start gap-3">
						<input
							data-testid="consent-mode-cookieless"
							type="radio"
							name="consent"
							checked={form.consent_mode === 'cookieless'}
							onChange={() => patch({ consent_mode: 'cookieless' })}
							className="mt-1 accent-primary"
						/>
						<div>
							<span className="text-sm font-medium">{__('Cookieless', 'statnive')}</span>
							<span className="ml-2 rounded-full bg-revenue/10 px-2 py-0.5 text-xs text-revenue-dark">
								{__('Recommended', 'statnive')}
							</span>
							<p className="text-xs text-muted-foreground">
								{__('No cookies, privacy-first. Supports GDPR/CCPA/APPI compliance.', 'statnive')}
							</p>
						</div>
					</label>
					<label className="flex cursor-pointer items-start gap-3">
						<input
							data-testid="consent-mode-disabled-until-consent"
							type="radio"
							name="consent"
							checked={form.consent_mode === 'disabled-until-consent'}
							onChange={() => patch({ consent_mode: 'disabled-until-consent' })}
							className="mt-1 accent-primary"
						/>
						<div>
							<span className="text-sm font-medium">{__('Disabled Until Consent', 'statnive')}</span>
							<p className="text-xs text-muted-foreground">
								{__(
									'Tracking stays off until a consent-banner plugin signals approval. Also honors plugins that implement the WordPress Consent API.',
									'statnive'
								)}
							</p>
						</div>
					</label>
				</div>

				<p className="mt-3 text-xs text-muted-foreground">
					{__(
						'Works with Real Cookie Banner, Complianz, CookieYes, or any WordPress Consent API plugin.',
						'statnive'
					)}
				</p>

				<div className="mt-4 space-y-3">
					<label className="flex cursor-pointer items-start gap-2">
						<input
							data-testid="dnt-respect-toggle"
							type="checkbox"
							checked={form.respect_dnt}
							onChange={(e) => patch({ respect_dnt: e.target.checked })}
							className="mt-1 accent-primary"
						/>
						<div>
							<span className="text-sm">{__('Respect Do Not Track', 'statnive')}</span>
							<p className="text-xs text-muted-foreground">
								{__('Skip visitors whose browser sends the DNT signal.', 'statnive')}
							</p>
						</div>
					</label>
					<label className="flex cursor-pointer items-start gap-2">
						<input
							data-testid="gpc-respect-toggle"
							type="checkbox"
							checked={form.respect_gpc}
							onChange={(e) => patch({ respect_gpc: e.target.checked })}
							className="mt-1 accent-primary"
						/>
						<div>
							<span className="text-sm">{__('Respect Global Privacy Control', 'statnive')}</span>
							<p className="text-xs text-muted-foreground">
								{__(
									'Skip visitors whose browser sends the GPC signal. Legally recognized in California and other regions.',
									'statnive'
								)}
							</p>
						</div>
					</label>
				</div>
			</div>

			{/* Data Retention */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className={`mb-4 ${HEADING_H3}`}>{__('Data Retention', 'statnive')}</h3>
				<select
					data-testid="retention-select"
					value={form.retention_days}
					onChange={(e) => handleRetentionChange(Number(e.target.value))}
					className="rounded-md border border-border bg-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
				>
					{RETENTION_OPTIONS.map((opt) => (
						<option key={opt.value} value={opt.value}>
							{opt.label()}
						</option>
					))}
				</select>
				<p className="mt-2 text-xs text-muted-foreground">
					{__(
						'How long stats are kept before deletion. Shorter = more privacy-friendly and a smaller database. Longer = year-over-year comparisons.',
						'statnive'
					)}
				</p>
			</div>

			{/* Exclusions */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className={`mb-4 ${HEADING_H3}`}>{__('Exclusions', 'statnive')}</h3>
				<label className="block text-sm">
					<span className="text-muted-foreground">{__('Excluded IP Addresses', 'statnive')}</span>
					<textarea
						data-testid="excluded-ips-textarea"
						value={form.excluded_ips}
						onChange={(e) => patch({ excluded_ips: e.target.value })}
						rows={4}
						className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary"
						placeholder="192.168.1.1&#10;10.0.0.0/8"
					/>
				</label>
				<p className="mt-2 text-xs text-muted-foreground">
					{__(
						'Tracking requests from these IPs or ranges are ignored — handy for hiding your own team. One per line. Supports CIDR (e.g., 10.0.0.0/8) and IPv6.',
						'statnive'
					)}
				</p>
				{currentIp && (
					<div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
						<span className="text-muted-foreground">{__('Your IP right now:', 'statnive')}</span>
						<code data-testid="current-ip-value" className="rounded bg-muted px-2 py-0.5 font-mono">
							{currentIp}
						</code>
						<button
							data-testid="add-ip-button"
							type="button"
							onClick={handleAddCurrentIp}
							className="rounded-md border border-border bg-card px-2 py-0.5 text-xs hover:bg-muted"
						>
							{__('Add to exclusions', 'statnive')}
						</button>
					</div>
				)}
			</div>
		</div>
	);
}
