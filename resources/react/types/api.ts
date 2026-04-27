// API response types aligned with actual REST endpoints.
// Revenue fields are intentionally omitted until WooCommerce integration (Phase 4).

export interface SummaryTotals {
	visitors: number;
	sessions: number;
	views: number;
	total_duration: number;
	bounces: number;
}

export interface DailyMetric {
	date: string;
	visitors: number;
	sessions: number;
	views: number;
	total_duration: number;
	bounces: number;
}

export interface SummaryResponse {
	totals: SummaryTotals;
	daily: DailyMetric[];
}

export interface SourceRow {
	channel: string | null;
	name: string | null;
	domain: string | null;
	visitors: number;
	sessions: number;
	views: number;
}

export type ChannelSourceRow = Omit<SourceRow, 'channel' | 'name' | 'domain'> & {
	name: string;
	domain: string;
};

export interface ChannelGroup {
	channel: string;
	visitors: number;
	sessions: number;
	views: number;
	sources: ChannelSourceRow[];
}

export interface PageRow {
	uri: string;
	title: string | null;
	visitors: number;
	views: number;
	total_duration: number;
	bounces: number;
}

export interface DimensionRow {
	code?: string;
	name?: string;
	city_name?: string;
	country?: string;
	continent_code?: string;
	version?: string;
	percentage?: number;
	visitors: number;
	sessions: number;
}

export interface UtmRow {
	campaign: string;
	source: string;
	medium: string;
	visitors: number;
	sessions: number;
}

export interface EntryExitPage {
	uri: string;
	title: string | null;
	count: number;
	visitors: number;
}

export interface RealtimeResponse {
	active_visitors: number;
	active_pages: { uri: string; visitors: number }[];
	recent_feed: { uri: string; country: string; browser: string; time: string }[];
}

export interface SettingsState {
	consent_mode: 'cookieless' | 'disabled-until-consent';
	respect_dnt: boolean;
	respect_gpc: boolean;
	retention_days: number;
	retention_mode: 'forever' | 'delete' | 'archive';
	excluded_ips: string;
	excluded_roles: string[];
	tracking_enabled: boolean;
}

export type DateRange = 'today' | '7d' | '30d' | 'this-month' | 'last-month' | 'custom';
