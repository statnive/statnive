# Changelog

All notable changes to Statnive are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Settings page Save button with dirty tracking — replaces auto-save-on-every-keystroke. Inline "Saved ✓" flash on success, inline error + retry on failure.
- "Your IP right now" hint in Exclusions with a one-click "Add to exclusions" button.
- Short per-control descriptions on every Settings control (DNT, GPC, Data Retention, Exclusions) so users can decide without docs.
- Mention that Statnive integrates with Real Cookie Banner, Complianz, CookieYes, and any WordPress Consent API plugin (copy only — integrations already existed).
- Real-production Playwright E2E suite that proves every Settings-page description is accurate: consent modes, DNT/GPC signal handling, retention purge behavior, IP/CIDR exclusion matching, Save contract, and removal guarantees.

### Changed

- Default Data Retention is now "Forever" (`retention_mode=forever`, `retention_days=3650`). Previous default was 90 days with `retention_mode=delete`.
- `PrivacyManager::check_request_privacy()` now accepts the extracted client IP and short-circuits on `ExclusionMatcher::is_excluded_ip()` / `is_excluded_role()` before expensive checks.

### Fixed

- Excluded IPs / CIDR ranges actually block tracking. `ExclusionMatcher` was defined but had no caller in the hit pipeline, so the "Excluded IP Addresses" setting was cosmetic. Now wired into `PrivacyManager` and exercised by `HitController`, `EventController`, and `AjaxFallback`.

### Removed

- "Full Tracking" consent mode. It was behaviorally identical to Cookieless (same `allows_tracking`, `requires_consent_signal`, `allows_geo`, `allows_device` flags in `ConsentMode::behaviors()`) — shipping the label without the intended cookie-based cross-day recognition was misleading. Deferred to a future release that actually implements the differentiation. Legacy installs with `statnive_consent_mode='full'` are silently coerced to `'cookieless'` on read.
- Email Reports subsystem: `EmailReportJob`, `ReportBuilder`, `wp statnive cron run --job=email-report`, the REST `email_reports`/`email_frequency` fields, and the Settings UI section. Deferred — will come back with email recipient management, template customization, and delivery diagnostics.

## [0.4.2] - 2026-04-14

### Added

- Device Distribution and Bot vs Human pie charts on Devices page, replacing progress-bar cards.
- DualBarCell (visitors/sessions side-by-side bars) on all report tables: Top Pages, Top Content, Entry/Exit Pages.

### Fixed

- Resolve 5 Plugin Check (PCP) warnings: ship composer.json in ZIP, rename THIRD-PARTY-LICENSES to .txt, remove deprecated load_plugin_textdomain(), bake SQL direction literals, refactor DimensionService to pass fully prepared queries.
- Stop externalizing react-is — WordPress has no ReactIs global; was causing runtime errors.
- Use unfiltered data for DualBarCell max calculation + DRY entry/exit column definitions.
- CI workflow now fails on PCP warnings (not just errors) via runner annotations check.

## [0.4.1] - 2026-04-14

### Fixed

- Externalize React/ReactDOM to WordPress's `wp-element` instead of bundling (WP.org Guideline §8, Appendix A #12). Dashboard bundle reduced from 743 KB to 562 KB.
- Add CSRF nonce to all public tracking endpoints — hit, event, engagement, AJAX fallback (WP.org Checklist §7). Centralized in `PayloadValidator::validate_nonce()`.
- Register `weekly` cron interval in `CronRegistrar` — WordPress has no built-in weekly schedule; GeoIP download and email reports depend on it (Checklist §9).
- Set `autoload=false` for admin-only options (`statnive_version`, `statnive_geoip_enabled`, `statnive_db_version`) to reduce `alloptions` bloat.

## [0.4.0] - 2026-04-13

### Added

- **React i18n**: wrapped ~130 user-visible strings with `@wordpress/i18n` `__()` across 14 component/page files. Dashboard is now translatable.
- **Host allow-list on `page_url`**: tracking endpoints validate URL host against site origin. Multi-domain setups can extend via `statnive_allowed_hosts` filter.
- **Rate limiting on AJAX fallback**: 60 req/min per IP (SHA-256 hashed), matching REST endpoint behavior.
- **`validate_callback` on all REST args**: HitController, EventController, EngagementController (new args schema), plus all admin dashboard controllers.
- **Downgrade detection**: Migrator warns admins when the stored schema version exceeds the running plugin version.
- **Circuit-breaker**: stops tracking writes after 50 failures in a 5-minute window, returns 503. Resets automatically.
- **GeoIP exponential backoff**: tracks download failures, waits 2^n hours between retries (capped at 1 week).
- **Disk-full detection**: DataPurgeJob checks total Statnive table size before each purge run, warns at 500 MB.
- **Automated a11y**: vitest-axe + axe-core integration for WCAG 2.2 AA regression testing in CI.
- **Action Scheduler detection**: `CronRegistrar::has_action_scheduler()` for WooCommerce environments.
- **Chart a11y table fallback**: visually-hidden `<table>` alongside Recharts time-series chart for screen readers.
- **Empty-state copy**: cause/fix/next-step messages on Pages, Referrers, Geography, Devices, Languages pages.
- **Privacy Policy section** in readme.txt.
- **`monthly` WP-Cron interval** registered via `cron_schedules` filter for email reports.
- **`DISABLE_WP_CRON` admin notice** broadened to cover all 5 Statnive cron jobs (not just GeoIP).
- **`pageshow` bfcache handler** in tracker JS — re-sends pageview when page is restored from back/forward cache.
- **GPL license banner** in React SPA bundle (`esbuild.banner`) and tracker JS builds (`terser.output.preamble`).

### Changed

- GDPR compliance language harmonized across readme.txt, README.md, and FAQ — all now say "designed to support" per Guideline 9.
- `tsc --noEmit` re-enabled in Frontend Quality CI job after fixing TanStack Router type-narrowing errors.
- `$wpdb->insert()` in DimensionService now includes explicit format arrays.
- Table-name validation added to DiagnosticsController SQL queries.
- `WPStatisticsImporter.php` uses `$wpdb->prepare()` with `$wpdb->esc_like()` for `SHOW TABLES`.
- `.distignore` expanded: `vendor/phpcompatibility/`, `vendor/*/examples/`, `vendor/*/*/examples/`.

### Fixed

- React bundle (`public/react/assets/main-*.js`) now includes GPL-2.0-or-later license header.
- FAQ heading changed from "Is Statnive GDPR compliant?" to "Is Statnive designed for GDPR compliance?" (Guideline 9).
- readme.txt trimmed to 9,881 bytes (safely under 10 KB cap).

## [0.3.1] - 2026-04-09

### Changed

- Lowered runtime floor to WordPress 5.6 / PHP 8.0. Wired the
  `PHPCompatibilityWP` ruleset into PHPCS so the floor is enforced in CI (#15).
  `Tested up to` remains the major-only `6.9` per WP.org Plugin Check policy.
- Refactored the tracking REST layer: extracted `PayloadValidator` from the
  `/hit` and `/event` controllers and hardened the privacy fall-through path
  so consent / DNT / GPC checks run before any payload work.
- Wired a fast pre-commit hook at `.githooks/pre-commit` that runs the
  `composer gate` + `npm run gate` quick suites on staged files.

### Fixed

- Fixed UTM parameter persistence and tuple-based campaign aggregation in
  the referrers report — campaigns with the same `utm_source` but different
  `utm_medium`/`utm_campaign` are now grouped correctly (#13).
- Fixed `/hit` and `/event` REST args being incorrectly marked
  `required: true`, which caused legitimate payloads with optional fields
  omitted to 400 (regression from 0.3.0, #11). Added a regression guard
  test (#12).
- Fixed dual-bar visualization on the referrers and channels reports to
  use a shared scale across visitors and sessions so the bars are visually
  comparable (#3).
- Fixed dashboard CSS leaking into WordPress admin chrome by scoping every
  rule in `globals.css` under `#statnive-app` and removing the unscoped
  Tailwind preflight import (#6).
- Fixed Pages report search input padding (`!important` to beat WP admin
  CSS) and wired the search box to the entry-page and exit-page tables (#4).

### Removed

- Removed unused `src/Addon` premium scaffolding and stale container
  configuration that survived the Guideline 6 license cleanup.
- **WordPress.org Guideline 6 compliance:** removed all license validation
  code from the WordPress.org build (`src/Licensing/`, `src/Feature/`,
  `src/Cron/LicenseCheckJob.php`, `src/Container/LicensingServiceProvider.php`,
  `src/Api/LicenseController.php`, `src/Api/CapabilitiesController.php` and
  the corresponding test suites). Premium features now ship via a separate
  Pro add-on distributed from statnive.com — never from wordpress.org.
- Removed the `statnive_weekly_license_check` cron schedule registration
  (the hook is still cleared in `uninstall.php` for sites upgrading from
  earlier versions).
- Removed the `statnive_dashboard_config` filter that injected plan
  capabilities into the React admin (no longer needed without tiers).

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
- Harden public tracking endpoints: strict payload schema (reject unknown keys → 400), 8 KB body size cap (413), Content-Type enforcement for REST (text/plain or application/json → 415)

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

### Changed

- Exclude dead `/src/Addon` premium stub modules from .org ZIP via `.distignore` (prevents Guideline 5 trialware confusion during review)
- Bump "Tested up to" to WordPress 6.9 (current stable, April 2026)

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
