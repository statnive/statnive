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

/**
 * Sentinel returned by `GET /settings` when a sensitive value (currently the
 * MaxMind license key) is set. `PUT /settings` skips updating any field whose
 * value is exactly this string, so masked round-trips are safe. Mirrored on
 * the server in `src/Api/SettingsController.php`.
 */
export const MASKED_PLACEHOLDER = '********';

export interface SettingsState {
	consent_mode: 'cookieless' | 'disabled-until-consent';
	respect_dnt: boolean;
	respect_gpc: boolean;
	retention_days: number;
	retention_mode: 'forever' | 'delete' | 'archive';
	excluded_ips: string;
	excluded_roles: string[];
	tracking_enabled: boolean;
	geoip_enabled: boolean;
	maxmind_license_key: string;
}

export type DateRange = 'today' | '7d' | '30d' | 'this-month' | 'last-month' | 'custom';

// =================================================================
// Advisor (Ask me!) types — match src/Advisor/Questions.php + AdvisorController.
// =================================================================

export type AdvisorPlan = 'free' | 'paid';
export type AdvisorConfidence = 'direct' | 'calculated' | 'proxy';

/**
 * Viz template hints emitted by the server. Mirrors the `Questions::VIZ_*`
 * constants on the PHP side; both must stay in sync. The AnswerViz component
 * dispatches on these values.
 */
export type AdvisorVizHint =
	| 'kpi_tile'
	| 'table'
	| 'donut'
	| 'bar'
	| 'map'
	| 'delta'
	| 'line'
	| 'live'
	| 'live_table'
	| 'status'
	| 'funnel'
	| 'anomaly'
	| 'coming_soon'
	| 'error';

export interface AdvisorCategory {
	id: string;
	label: string;
	label_en: string;
}

export interface AdvisorQuestion {
	id: string;
	category_id: string;
	category: string;
	category_en: string;
	question: string;
	question_en: string;
	keywords: string[];
	plan: AdvisorPlan;
	surface: string;
	viz_hint: AdvisorVizHint;
	confidence: AdvisorConfidence;
	depends_on_schema?: string;
	searchable: string[];
	/**
	 * Dynamic-window flag. When set, the `question` field is a sprintf
	 * template with a single `%s` placeholder; React substitutes a
	 * localised date-range phrase from the current date picker.
	 * - 'current' = phrase for the active window (e.g. "today", "this month")
	 * - 'prior'   = phrase for the comparison window (e.g. "with yesterday")
	 */
	dynamic_window?: 'current' | 'prior';
}

export interface AdvisorQuestionsResponse {
	categories: AdvisorCategory[];
	questions: AdvisorQuestion[];
}

export type AdvisorAnswerStatus = 'ok' | 'coming_soon' | 'error';

export type AdvisorAnswerReason =
	| 'schema_gap_v1_1'
	| 'paid_growth_v2'
	| 'handler_pending';

export interface AdvisorAnswer {
	id: string;
	status: AdvisorAnswerStatus;
	value: unknown;
	viz: AdvisorVizHint;
	source?: string | null;
	plan?: AdvisorPlan;
	confidence?: AdvisorConfidence;
	reason?: AdvisorAnswerReason;
	code?: string;
	message?: string;
}

export interface AdvisorAnswersResponse {
	answers: AdvisorAnswer[];
	from: string;
	to: string;
}

export interface AdvisorPreferencesResponse {
	pinned_questions: string[];
	max_pins: number;
	defaults?: string[];
}
