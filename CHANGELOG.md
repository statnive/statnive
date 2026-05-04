# Changelog

All notable changes to Statnive are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
