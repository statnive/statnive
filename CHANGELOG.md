# Changelog

All notable changes to Statnive are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-04-06

### Fixed

- Fix engagement-to-view correlation using pageview ID (pvid) token for exact matching
- Fix engagement updates matching by URI path instead of resource_id (fixes homepage/archive/404 pages)
- Fix Avg Duration data pipeline — aggregate from views.duration instead of sessions.duration
- Fix dashboard importmap output — revert to direct output (wp_get_inline_script_tag adds CDATA)
- Fix readme.txt WP.org compliance — remove false Pro feature claims, fix URLs, remove woocommerce tag
- Add ABSPATH guards to all 92 src/ PHP files for WP.org compliance
- Fix unescaped output in AdminMenuManager, AdminBarWidget, ReactHandler
- Make admin notices dismissible with `is-dismissible` class
- Gate GeoIP download to opt-in (no auto-download on activation)
- Gate license API to explicit user action only (no api.statnive.com contact on free installs)
- Remove P3TERX GeoIP mirror — MaxMind license key is now required for GeoIP (EULA compliance)
- Fix ReportBuilder email numbers to use `number_format_i18n()` for locale-aware formatting

### Added

- Date range persistence across all dashboard tabs via URL search params
- Two-stage tracker loading for optimal Web Vitals (1.1KB inline core + deferred modules)
- GPL v2 LICENSE file
- THIRD-PARTY-LICENSES.md with all bundled dependency licenses
- External Services section in readme.txt (GeoIP + license API documentation with MaxMind EULA link)
- Ship unminified tracker source alongside minified builds
- WP.org pre-submission CI workflow with 6 enforcement gates
- i18n: `load_plugin_textdomain()` boot, `languages/statnive.pot` with 96 strings
- GeoIPNotice admin notices for missing MaxMind license key and `DISABLE_WP_CRON` advisory
- Translatable strings throughout email reports (Visitors, Sessions, Pageviews, Top Pages, etc.)
- MaxMind license key + GeoIP enable/disable settings in Settings REST API with validation

## [0.2.0] - 2026-04-05

### Fixed

- Fix real-time dashboard showing 0 active visitors due to stale 30s transient cache
- Fix tracker not sending actual page URL, causing synthetic URIs like /page/0 in reports
- Fix Overview report dropping today's data when visitors count is zero
- Fix Pages report showing stale aggregated data instead of fresh real-time numbers
- Fix duplicate entries in Recent Pageviews feed caused by resources table join
- Fix race condition creating duplicate resource rows on concurrent requests
- Fix GeoIP database never downloaded (broken CDN URL, missing activation trigger)
- Fix MaxMind tar.gz archive not extracted during direct download
- Fix version mismatch between plugin header, constant, and package.json

### Added

- Regex-based UA fallback parser when matomo/device-detector is unavailable
- `statnive_client_ip` filter for local development/testing IP override
- GeoIP database auto-download on plugin activation
- 38 regression tests covering all fixed bugs

## [0.1.1] - 2026-04-04

### Fixed

- Resolve zero-data Geography, Devices, and Real-time dashboard pages
- Fix dashboard CSS, crash, and zero-data rendering bugs
- Fix BotDetector regex and ExclusionMatcher regex patterns
- Resolve 4 critical + 4 medium production integrity issues
- Green Playwright E2E suite (42/49 pass, 7 skipped)
- Green integration test suite

### Added

- Plugin distribution packaging (.distignore)

## [0.1.0] - 2026-04-02

### Added

- Privacy-first analytics dashboard with 8 screens (Overview, Pages, Referrers, Geography, Devices, Languages, Real-time, Settings)
- Cookieless tracking with daily rotating CSPRNG salts (two-salt system, 48h overlap)
- IP anonymization (last-octet zeroing) with ephemeral lifecycle
- GeoIP resolution via MaxMind GeoLite2-City
- Device detection via matomo/device-detector (server-side UA parsing)
- Referrer classification into 7 channels (Organic Search, Social Media, Direct, Referral, Email, Paid Search, Paid Social)
- UTM parameter extraction and storage
- Custom event tracking with auto-tracking (outbound links, form submissions, file downloads)
- Engagement tracking (scroll depth, time-on-page via Visibility API)
- Bot detection (UA patterns, webdriver, Math.random entropy)
- Pre-aggregated summary tables with daily cron job
- Real-time visitor counter with 5-second polling
- Email reports (weekly/monthly) with HTML templates
- Data import from WP Statistics, CSV
- WordPress Privacy API compliance (data exporter, eraser, policy generator)
- Privacy audit dashboard with 10 compliance checks and score
- WordPress Site Health integration (3 checks)
- WP Consent API integration (Real Cookie Banner, Complianz, CookieYes)
- 3 consent modes (full, cookieless, disabled-until-consent)
- DNT + GPC header support (enabled by default)
- Configurable data retention (forever, auto-delete, archive with encryption)
- 4-tier licensing system (Free, Starter, Professional, Agency)
- Feature gating with ConditionTagEvaluator and FeatureGate service
- License management REST API with encrypted key storage
- 5 add-on feature modules (Data Plus, Advanced Reporting, Real-Time Stats, Marketing, REST API)
- API key authentication for external integrations
- Campaign manager with UTM URL generator
- React 18 SPA dashboard (TanStack Router/Query, Tailwind CSS, shadcn/ui, Recharts)
- Keyboard shortcuts and WP Command Palette integration
- CSV export from all data tables
- Comparison mode (current vs previous period)
- WCAG 2.1 AA accessibility (skip-to-content, aria-live, focus management, prefers-reduced-motion)
- WordPress.org ready (readme.txt, screenshots, release workflow)

### Security

- All SQL via `$wpdb->prepare()` — zero string concatenation
- HMAC-SHA256 signatures on tracker payloads
- WordPress nonces + capability checks on all admin endpoints
- License keys encrypted with sodium_crypto_secretbox
- API keys stored as SHA-256 hashes (never plaintext)
- SHA-pinned GitHub Actions (not tag-pinned)
