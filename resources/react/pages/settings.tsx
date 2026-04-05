import { useSettings, useUpdateSettings } from '@/hooks/use-settings';

export function SettingsPage() {
	const { data: settings, isLoading } = useSettings();
	const { mutate: update } = useUpdateSettings();

	if (isLoading || !settings) {
		return (
			<div className="space-y-4">
				<h2 className="text-lg font-semibold">Settings</h2>
				{Array.from({ length: 4 }).map((_, i) => (
					<div key={i} className="h-24 animate-pulse rounded-lg border border-border bg-card" />
				))}
			</div>
		);
	}

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">Settings</h2>

			{/* Privacy */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className="mb-4 text-sm font-medium">Privacy</h3>
				<div className="space-y-3">
					<label className="flex cursor-pointer items-center gap-3">
						<input
							type="radio"
							name="consent"
							checked={settings.consent_mode === 'cookieless'}
							onChange={() => update({ consent_mode: 'cookieless' })}
							className="accent-primary"
						/>
						<div>
							<span className="text-sm font-medium">Cookieless</span>
							<span className="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Recommended</span>
							<p className="text-xs text-muted-foreground">No cookies, privacy-first. GDPR compliant by default.</p>
						</div>
					</label>
					<label className="flex cursor-pointer items-center gap-3">
						<input type="radio" name="consent" checked={settings.consent_mode === 'full'} onChange={() => update({ consent_mode: 'full' })} className="accent-primary" />
						<div>
							<span className="text-sm font-medium">Full Tracking</span>
							<p className="text-xs text-muted-foreground">Extended analytics with consent banner.</p>
						</div>
					</label>
					<label className="flex cursor-pointer items-center gap-3">
						<input type="radio" name="consent" checked={settings.consent_mode === 'disabled-until-consent'} onChange={() => update({ consent_mode: 'disabled-until-consent' })} className="accent-primary" />
						<div>
							<span className="text-sm font-medium">Disabled Until Consent</span>
							<p className="text-xs text-muted-foreground">No tracking until explicit consent given.</p>
						</div>
					</label>
				</div>

				<div className="mt-4 flex gap-6">
					<label className="flex cursor-pointer items-center gap-2">
						<input type="checkbox" checked={settings.respect_dnt} onChange={(e) => update({ respect_dnt: e.target.checked })} className="accent-primary" />
						<span className="text-sm">Respect Do Not Track</span>
					</label>
					<label className="flex cursor-pointer items-center gap-2">
						<input type="checkbox" checked={settings.respect_gpc} onChange={(e) => update({ respect_gpc: e.target.checked })} className="accent-primary" />
						<span className="text-sm">Respect Global Privacy Control</span>
					</label>
				</div>
			</div>

			{/* Data Retention */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className="mb-4 text-sm font-medium">Data Retention</h3>
				<select
					value={settings.retention_days}
					onChange={(e) => update({ retention_days: Number(e.target.value) })}
					className="rounded-md border border-border bg-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
				>
					<option value={30}>30 days</option>
					<option value={90}>90 days</option>
					<option value={365}>1 year</option>
					<option value={3650}>Forever</option>
				</select>
			</div>

			{/* Exclusions */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className="mb-4 text-sm font-medium">Exclusions</h3>
				<label className="block text-sm">
					<span className="text-muted-foreground">Excluded IP Addresses (one per line)</span>
					<textarea
						value={settings.excluded_ips}
						onChange={(e) => update({ excluded_ips: e.target.value })}
						rows={3}
						className="mt-1 w-full rounded-md border border-border bg-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
						placeholder="192.168.1.1&#10;10.0.0.0/8"
					/>
				</label>
			</div>

			{/* Email Reports */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className="mb-4 text-sm font-medium">Email Reports</h3>
				<label className="flex cursor-pointer items-center gap-2">
					<input type="checkbox" checked={settings.email_reports} onChange={(e) => update({ email_reports: e.target.checked })} className="accent-primary" />
					<span className="text-sm">Send email reports</span>
				</label>
				{settings.email_reports && (
					<select
						value={settings.email_frequency}
						onChange={(e) => update({ email_frequency: e.target.value as 'weekly' | 'monthly' })}
						className="mt-2 rounded-md border border-border bg-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
					>
						<option value="weekly">Weekly</option>
						<option value="monthly">Monthly</option>
					</select>
				)}
			</div>
		</div>
	);
}
