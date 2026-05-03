=== Statnive ===
Contributors: statnive
Tags: analytics, statistics, privacy, tracking, dashboard
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.4.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first WordPress analytics. No cookies, no third-party scripts, 100% self-hosted. Real-time, GeoIP, AI-source tracking.

== Description ==

**The privacy-first analytics plugin for WordPress.**

See exactly who visits your site, where they come from, and what they do — without cookies, third-party trackers, or sending visitor data to anyone. All analytics live in your own WordPress database.

= Why Statnive? =

* **No cookies. No fingerprinting. No third-party transfers.** Designed to support GDPR, CCPA, and APPI compliance.
* **Honors GPC and DNT** server-side, integrates with the WordPress Consent API.
* **Daily-rotating salted hashes** — cross-day and cross-site tracking are mathematically impossible.
* **Eight focused dashboard pages.** No clutter, no upsells.
* **Free forever.** No license validation, no Pro tier, no trial limits. GPL-2.0-or-later.

= Key Features =

* **Real-time** — Active visitor count, active pages, live pageview feed.
* **Smart channel grouping** — Direct, Organic Search, Social Media, Email, Referral, Paid Search, Paid Social, and a dedicated **AI Assistants** channel for ChatGPT, Claude, Gemini, Perplexity, Copilot, NotebookLM, Meta AI, Le Chat, Deepseek, You, iAsk, Jasper, and Writesonic.
* **Custom events + engagement** — Link clicks (and tagged button clicks via `statnive-event-*` classes), form submissions, downloads, outbound links, time on page, scroll depth.
* **Bot vs human separation** — Real visitors and automated traffic in distinct buckets.
* **Geography in tiers** — Zero-config timezone country mapping; optional CDN headers; optional one-click DB-IP city download (free); optional MaxMind GeoLite2.
* **Configurable retention** — 30 / 90 / 180 / 365 days, or Forever. Daily WP-Cron purge.
* **WordPress Privacy API** — Personal-data export and erase registered automatically.
* **WP-CLI** — `wp statnive cron run` for sites with `DISABLE_WP_CRON`.
* **Importers** — WP Statistics and CSV.

Source code at [github.com/statnive/statnive](https://github.com/statnive/statnive). Learn more at [statnive.com](https://statnive.com).

== Installation ==

1. Upload the `statnive` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Open **Statnive** in the admin sidebar.

That's it. Tracking begins immediately — no configuration, no account, no tracking code to paste.

== Frequently Asked Questions ==

= Does Statnive use cookies? =

No. Statnive is 100% cookie-free. Visitor identification is a daily-rotating salted hash that cannot be used to track individuals across days or sites.

= Is Statnive GDPR compliant? =

Statnive is **designed to support** GDPR, CCPA, APPI, and PIPL compliance: no cookies, no PII storage, daily rotating hashes, configurable retention, WordPress Privacy API export/erase, and server-side respect for GPC and DNT signals. Final compliance always depends on your configuration and your privacy policy.

= How does visitor counting work without cookies? =

A salted SHA-256 hash of the visitor's anonymized IP plus User-Agent. The salt rotates daily, so the same visitor gets a different hash tomorrow — cross-day stitching is impossible while daily uniques stay accurate.

= Where is my data stored? =

In your WordPress database, in tables prefixed `statnive_`. Nothing leaves your server unless you explicitly enable the optional MaxMind or DB-IP GeoIP downloads (one-time database files, never visitor data). When you uninstall the plugin, all tables and uploaded GeoIP files are removed.

= Does it work with WooCommerce? =

Statnive tracks pageviews, events, sessions, and referrers on WooCommerce stores like any other WordPress site. Dedicated WooCommerce revenue tracking with Revenue per Visitor (RPV) is on the roadmap.

= How much does it slow down my site? =

The tracker script is small (~2 KB gzipped) and loads asynchronously, so it does not block your page render. The hit endpoint writes a single row per pageview. Dashboard queries run against pre-aggregated daily summaries rather than raw events.

= Can I import data from another analytics plugin? =

Yes. Importers ship for WP Statistics and CSV. A Google Analytics 4 importer is planned for a future release.

= Can I run Statnive alongside Google Analytics or Matomo? =

Yes. Statnive is fully independent. Many users run Statnive as their primary privacy-friendly analytics and keep GA4 for advertising attribution.

= What can cause "no data"? =

Common causes: ad blockers, aggressive page caching, CSP rules blocking `fetch`/`sendBeacon` (allow `connect-src 'self'`), GPC or DNT enabled, or `DISABLE_WP_CRON` without a system cron. Exclude `/wp-json/statnive/v1/hit` and `admin-ajax.php?action=statnive_hit` from page caches.

= How does Geography work? =

Four tiers, falling through automatically: (1) browser timezone → country, ~80% accurate, no external call; (2) CDN country headers (Cloudflare, CloudFront, Vercel) when present; (3) optional one-click DB-IP IP-to-City Lite (free, CC-BY 4.0); (4) optional MaxMind GeoLite2 (free with an account). Tiers 3 and 4 are opt-in via a discrete user click.

== Screenshots ==

1. Know your traffic at a glance — visitors, sessions, pageviews and trends that matter
2. Find what's actually driving results — top sources and top pages side by side
3. Every page, ranked by what matters — search, sort, find your best content
4. See where visitors arrive and leave — entry and exit pages side by side
5. Understand where your traffic comes from — referral, direct, organic, social, AI
6. Desktop, mobile, bots — device, browser and OS breakdowns in one view
7. Reach across languages and regions — see which languages your visitors speak
8. Watch your site breathe in real time — active visitors and live pageviews

== External Services ==

This plugin connects to two third-party services, both **opt-in via explicit user action**. No visitor data is ever sent to either service.

= MaxMind GeoLite2 (optional) =

Used to download the MaxMind GeoLite2-City database for high-accuracy visitor geolocation. Requires a free MaxMind account and a license key the user pastes into Settings → GeoIP. Accuracy: approximate city/region only — not for identifying individuals or households.

* Source: MaxMind (https://www.maxmind.com), downloaded from https://download.maxmind.com/app/geoip_download
* When: Weekly via WP-Cron, only after the user enables GeoIP and configures a license key
* Data sent: License key + standard HTTP request headers. No visitor data is transmitted.
* Data received: GeoLite2-City.mmdb file, stored in your `wp-content/uploads/statnive/` directory
* Purpose: Approximate visitor geolocation (country / region / coarse city)
* Sign up for a MaxMind account: https://www.maxmind.com/en/geolite2/signup
* Get your license key: https://www.maxmind.com/en/accounts/current/license-key
* MaxMind Privacy Policy: https://www.maxmind.com/en/privacy-policy
* MaxMind Terms of Use: https://www.maxmind.com/en/terms-of-use
* MaxMind GeoLite2 EULA: https://www.maxmind.com/en/geolite2/eula

This product includes GeoLite Data created by MaxMind, available from https://www.maxmind.com.

= DB-IP IP-to-City Lite (optional) =

Used to download the free DB-IP IP-to-City Lite database. No account, no license key, no EULA. Accuracy: approximate city/region only — not for identifying individuals or households.

* Source: DB-IP (https://db-ip.com), downloaded from https://download.db-ip.com/free/
* When: One-shot user click "Enable city-level geography" on the Geography page, then monthly via WP-Cron for refresh
* Data sent: Standard HTTP request headers only (no visitor data, no account, no key)
* Data received: dbip-city-lite-YYYY-MM.mmdb.gz file, decompressed to your `wp-content/uploads/statnive/` directory
* Purpose: Approximate visitor geolocation (city / region / country)
* DB-IP Terms: https://db-ip.com/db/about/
* DB-IP Privacy Policy: https://db-ip.com/our_privacy_policy.php
* License: CC-BY 4.0

GeoIP data © DB-IP under CC-BY 4.0.

No visitor data is ever sent to any external service. All analytics data remains in your WordPress database.

== Privacy Policy ==

All analytics data stays in your WordPress database. No cookies, no fingerprinting, no external transfers. Daily-rotating salted hashes prevent cross-day tracking. Raw IP addresses are used only for the optional GeoIP lookup and are never persisted. Statnive registers with the WordPress Privacy API for personal-data export and erasure.

== Changelog ==

= 0.4.7 =
First public release on WordPress.org. Privacy-first WordPress analytics with:

* Real-time dashboard with active visitors, active pages and a live pageview feed
* Eight channel grouping: Direct, Organic Search, Social Media, **AI Assistants** (ChatGPT, Claude, Gemini, Perplexity, Copilot, NotebookLM, Meta AI, Le Chat, Deepseek and more), Email, Referral, Paid Search, Paid Social
* Anchored host-suffix referrer matching (no false positives on lookalike domains)
* Geography in four tiers — browser timezone, CDN country headers, optional DB-IP IP-to-City Lite (one-click free), optional MaxMind GeoLite2 (your own free key)
* Custom events, engagement (time-on-page + scroll depth), bot vs human separation
* Cookieless and Disabled-Until-Consent privacy modes; server-side GPC and DNT
* WordPress Privacy API integration (personal-data export and erase)
* Configurable retention (30 / 90 / 180 / 365 days, or Forever) with daily purge cron
* Importers for WP Statistics and CSV
* `wp statnive cron run` WP-CLI command for sites with `DISABLE_WP_CRON`

Source code, issue tracker and full development history: https://github.com/statnive/statnive

== Upgrade Notice ==

= 0.4.7 =
First public release on WordPress.org.
