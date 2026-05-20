# Changelog

All notable changes to Statnive are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-20

### Added

- WooCommerce Revenue Report (v1.0.0).

## [0.4.13] - 2026-05-13

### Changed

- **PHP floor raised from 8.0 to 8.1.** PHP 8.0 reached end-of-life in November 2023. Bumped in `statnive.php` (header `Requires PHP` + `STATNIVE_MIN_PHP` define), `readme.txt`, `composer.json`, `package.json`, `phpstan.neon` (`phpVersion: 80100`), `.phpcs.xml.dist` (`testVersion: 8.1-`), `blueprint.json`, both test bootstraps, and all three GitHub Actions workflows (matrix entries + cache keys + explanatory comments). Required for the MaxMind library bumps below; also closes the gap with WordPress core's own PHP support timeline.
- **`geoip2/geoip2` bumped from `^2.13` to `^3.0`** (installed v3.3.0). v3 introduces readonly typed properties on the City/Country/Continent/Subdivision record classes; Statnive's narrow API surface (`\GeoIp2\Database\Reader::city()`, `->close()`, six property reads on the result) is fully v3-compatible — no production code changes needed.
- **`maxmind/web-service-common` bumped from `~0.9.0` to `^0.11`** (installed v0.11.1). Transitive dependency of `geoip2/geoip2`; v0.10+ raised the PHP floor to 8.1, which is why the bumps had to land together.

### Fixed

- **DB-IP privacy-policy URL.** `readme.txt:135` updated from `https://db-ip.com/our_privacy_policy.php` (now returns HTTP 404) to the current canonical URL `https://db-ip.com/privacy.php` (HTTP 200, linked from db-ip.com homepage footer). External Services disclosure now resolves.

## [0.4.12] - 2026-05-11

### Fixed

- **readme.txt Tags line.** Replaced `google-analytics` with `cookieless`. WordPress.org plugin guidelines and the project's own forbidden-tag list (`.claude/skills/statnive-release-zip/checks/c05-readme.md`) disallow competitor product names in the `Tags:` header — submission would have been flagged on automated screening. New tags line: `analytics, privacy, statistics, gdpr, cookieless` (5 generic tags, all within policy). The description body's "Google Analytics alternative" framing is unaffected — that's allowed in body copy, only forbidden as a tag.

## [0.4.11] - 2026-05-09

### Removed

- **Importers** — WP Statistics and CSV historical-data import. The cron handler for `statnive_import_batch` was never registered, so scheduled batches never ran; the REST endpoints returned `200 "started"` but no data was ever ingested. Feature removed entirely; the `wp_clear_scheduled_hook( 'statnive_import_batch' )` call in `uninstall.php` is retained as legacy cleanup for sites upgrading from versions that scheduled the orphan event.

## [0.4.10] - 2026-05-05

### Added

- **MaxMind GeoIP card on the admin Settings page.** New card with a license-key text input and an Enable-MaxMind-GeoIP toggle. Drives the existing server-side `maxmind_license_key` and `statnive_geoip_enabled` options that previously had no UI surface (settable only via REST or WP-CLI). Helper copy links to the maxmind.com signup flow and points at the DB-IP IP-to-City Lite fallback on the Geography page. Conditional hint surfaces when the stored key is masked.

### Changed

- **Centralized the masked-license-key sentinel.** The `********` value used to mask `maxmind_license_key` on GET — and recognised as "no change" on PUT — is now the canonical `Statnive\Api\SettingsController::MASKED_PLACEHOLDER` PHP constant, mirrored as the `MASKED_PLACEHOLDER` TypeScript export from `resources/react/types/api.ts`. Tests and the GeoIP e2e spec import the constant rather than duplicating the literal, so any future masking change is a single-point edit.

### Internal

- **Closed test coverage gaps for the admin Settings tab.** New `SettingsControllerTest` integration test (12 cases for the manage_options auth gate, masked-license-key roundtrip, `missing_license_key` 400 path, GeoIP enable/disable cron transitions, REST-framework rejection of out-of-range `retention_days` and invalid `consent_mode`). New `ConsentApiIntegrationTest` unit test (4 cases covering all `has_consent()` branches, with `wp_has_consent` stubbed in the global namespace so `function_exists()` resolves it). Extended `SettingsSanitizationTest` with `archive` enum, multiline IP textarea, non-array role coercion, and bool coercion. Extended `DataRetentionTest` with one case that pins the current `archive`-mode contract (delete-equivalent) so future archival work fails this test instead of regressing silently.

## [0.4.9] - 2026-05-04

### Fixed

- **Packaging — WordPress.org Plugin Check `hidden_files` rejection.** The v0.4.8 dist ZIP shipped three dotfiles that WP.org PCP rejects: `.env.local` (developer's local config + admin credentials), `.githooks/pre-commit` (host-side dev tooling), and `public/react/.vite/manifest.json` (Vite default manifest path). `.env.local` and `.githooks/` are now excluded via `.distignore`. The Vite manifest moves to `public/react/manifest.json` (`manifest: 'manifest.json'`) so it's no longer in a hidden directory; both readers (`ReactHandler::read_manifest()` and `AdminAssetScopeTest`) updated to match.

### Security

- The v0.4.8 ZIP, available on GitHub Releases for ~3 hours before this fix, contained `.env.local` with development credentials (`admin`/`admin` for the dev WordPress site) and the developer's local filesystem path. Anyone who downloaded v0.4.8 from GitHub Releases between publish and this fix has those values. They are not used outside the dev environment, but rotate them if you have any concern.

## [0.4.8] - 2026-05-04

### Fixed

- Tracker no longer fires for users in `excluded_roles`. Previously the role check ran only at the `/hit` REST endpoint, where REST cookie auth treats nonce-less tracker beacons as guest, so excluded admins/editors still recorded views. The gate now runs at `wp_enqueue_scripts` time where `wp_get_current_user()` is reliable.

### Added

- E2E coverage for `tracking_enabled`, `excluded_roles`, `maxmind_license_key` (masking + 400 path), GeoIP cron scheduling on enable, and `retention_mode=archive`. New mu-plugin debug endpoint `/debug/ensure-user` and fixture `role-login.ts` for non-admin role test sessions.

### Internal

- Fixed `dbQuery` E2E parser silently dropping rows with empty-string values (mysql `--batch` row-separator newline was being stripped by `.trim()`).

## [0.4.7] - 2026-05-03

First public release on WordPress.org. Privacy-first WordPress analytics — no cookies, no third-party scripts, 100% self-hosted.

### Features

- **Real-time dashboard** — Active visitor count, active pages, and a live recent-pageview feed.
- **Eight channel grouping** — Direct, Organic Search, Social Media, **AI Assistants** (ChatGPT, Claude, Gemini, Perplexity, Copilot, NotebookLM, Meta AI, Le Chat, Deepseek, You, iAsk, Jasper, Writesonic), Email, Referral, Paid Search, Paid Social.
- **Anchored host-suffix referrer matching** — same algorithm Snowplow `referer-parser`, Plausible, and Matomo use; no false positives on lookalike domains.
- **Eight focused dashboard pages** — Overview, Pages, Referrers, Geography, Devices, Languages, Real-time, Settings.
- **Geography in four tiers** — browser-timezone country mapping (zero-config), CDN country headers (Cloudflare/CloudFront/Vercel), optional one-click DB-IP IP-to-City Lite (free, CC-BY-4.0), optional MaxMind GeoLite2 with a user-provided license key.
- **Custom events + engagement** — button clicks, form submissions, file downloads, outbound links, time-on-page, scroll depth.
- **Bot vs human separation** — automated traffic shown in distinct buckets.
- **Privacy by default** — cookieless mode (default) and disabled-until-consent mode; server-side GPC and DNT honoring; daily-rotating salted hashes.
- **WordPress Privacy API** — personal-data exporter and eraser registered automatically.
- **Configurable retention** — 30 / 90 / 180 / 365 days, or Forever. Daily WP-Cron purge.
- **Importers** — WP Statistics and CSV historical-data import.
- **WP-CLI** — `wp statnive cron run` for sites with `DISABLE_WP_CRON`.
- **Free forever** — no license validation, no Pro tier, no trial limits, no paywall. GPL-2.0-or-later.
