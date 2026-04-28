=== Statnive ===
Contributors: statnive
Tags: analytics, statistics, privacy, tracking, dashboard
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first WordPress analytics. No cookies, no third-party scripts, 100% self-hosted.

== Description ==

**The privacy-first analytics plugin for WordPress.**

Fast, smart, easy-to-understand analytics without complicated setup or confusing dashboards. All data stays on your server — no cookies, no fingerprinting, no third-party transfers.

= Why Statnive? =

* **Channel intelligence** — Auto-groups traffic into Organic Search, Social, Direct, Referral, Email.
* **Privacy by default** — No cookies, no fingerprinting. Designed to support GDPR/CCPA/APPI. Daily rotating salts.
* **Zero-config setup** — Install, activate, done. No tracking code, no account, no external service.

= Key Features =

* **Real-time dashboard** — See who's on your site right now with live visitor count, active pages, and recent activity feed
* **Channel grouping** — Traffic sources automatically grouped into Organic Search, Social Media, Direct, Referral, and Email
* **Geographic data** — Country and city breakdowns using self-hosted GeoIP — no third-party lookups
* **Device detection** — Browser, OS, and device type breakdowns to understand your audience
* **Custom events** — Track button clicks, form submissions, file downloads, and outbound links
* **Bot detection** — Automatic filtering of bots, crawlers, and headless browsers
* **Privacy compliance** — DNT/GPC respect, configurable data retention, WordPress Privacy API (export/erase)

[Learn more at statnive.com](https://statnive.com)

The full source code is available at [github.com/statnive/statnive](https://github.com/statnive/statnive).

== Installation ==

1. Upload the `statnive` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Visit the Statnive dashboard from the admin menu

That's it. Analytics tracking begins immediately — no configuration required.

== Frequently Asked Questions ==

= Does Statnive use cookies? =

No. Statnive is 100% cookie-free. It uses a daily rotating salt hash for visitor identification that cannot be used to track individuals across days or sites.

= Is Statnive designed for GDPR compliance? =

Statnive is **designed to support** GDPR, CCPA, and APPI compliance: no cookies, no PII storage, daily rotating hashes, configurable retention, and WordPress Privacy API support. Compliance depends on how you configure the plugin.

= Does it work with WooCommerce? =

Statnive tracks pageviews and visitor analytics on any WordPress site, including WooCommerce stores. Dedicated WooCommerce revenue tracking with Revenue per Visitor (RPV) is planned for a future release.

= How much does it slow down my site? =

The tracker script is under 5KB gzipped. Server-side processing adds less than 25ms to page load (p95). Analytics data is pre-aggregated daily for fast dashboard queries that never touch raw event tables.

= Can I import data from other analytics plugins? =

Yes. Statnive supports importing historical data from WP Statistics and CSV files. Google Analytics 4 import is planned for a future release.

= Can I use Statnive alongside Google Analytics? =

Yes. Statnive runs independently and does not conflict with Google Analytics, Matomo, or any other analytics tool. Many users run Statnive as their privacy-compliant primary analytics while keeping GA4 for advertising attribution.

= How does visitor counting work without cookies? =

A daily-rotating salted hash of the anonymized IP + User-Agent. The same visitor gets a different hash each day, so cross-day tracking is impossible while daily uniques stay accurate.

= Where is my analytics data stored? =

All data is stored in your WordPress database on your own server. Statnive creates its own tables (prefixed `statnive_`) and never sends data to external servers. When you uninstall the plugin, all tables are cleanly removed.

= What can cause "no data"? =

Common causes: ad blockers, aggressive page caching, CSP blocking `fetch()`/`sendBeacon()` (allow `connect-src 'self'`), privacy signals (GPC/DNT), or `DISABLE_WP_CRON`. See the [troubleshooting guide](https://statnive.com/docs/troubleshooting).

= Do I need to exclude URLs from page caches? =

Exclude `/wp-json/statnive/v1/hit` and `admin-ajax.php?action=statnive_hit`. Most caching plugins do this by default.

= How does Geography work? =

Statnive resolves geography in tiers, falling through automatically:

1. **Zero-config — browser timezone.** Each tracker payload carries the visitor's IANA timezone. Statnive maps it to a country via a static IANA tzdb lookup shipped in the plugin. No setup, ~80% accurate at country level.
2. **CDN country headers.** Cloudflare / CloudFront / Vercel set a country header per request. More accurate than timezone when present. Country only.
3. **DB-IP IP-to-City Lite (one click, free).** Click "Enable city-level geography" on the Geography page. Statnive downloads the free DB-IP city database (~80 MB) to your uploads directory; cities populate from the next hit. No account, no key.
4. **MaxMind GeoLite2 (paid-grade).** Configure a free MaxMind license key in Settings → GeoIP for the highest-accuracy IP-to-city resolution.

Tiers 1 and 2 require no network call. Tiers 3 and 4 are opt-in via a discrete user action.

== Screenshots ==

1. Know your traffic at a glance — visitors, sessions, pageviews and trends that matter
2. Find what's actually driving results — top sources and top pages side by side
3. Every page, ranked by what matters — search, sort, find your best content
4. See where visitors arrive and leave — entry and exit pages side by side
5. Understand where your traffic comes from — referral, direct, organic, social
6. Desktop, mobile, bots — device, browser and OS breakdowns in one view
7. Reach across languages and regions — see which languages your visitors speak
8. Watch your site breathe in real time — active visitors and live pageviews

== External Services ==

This plugin connects to the following third-party services under specific conditions:

= GeoIP Database Downloads — MaxMind =
This plugin can download MaxMind GeoLite2 GeoIP databases to enable visitor geolocation.
Requires a free MaxMind account and license key (user must accept the GeoLite2 EULA).

* Source: MaxMind (https://www.maxmind.com), downloaded from https://download.maxmind.com/
* When: Weekly via WordPress Cron, only when GeoIP feature is enabled in Settings and a license key is configured
* Data sent: License key and standard HTTP request headers (no visitor data is transmitted)
* Data received: GeoIP database file, stored locally in your uploads directory
* Purpose: Determine approximate geographic location of visitors from anonymized IP addresses
* MaxMind Privacy Policy: https://www.maxmind.com/en/privacy-policy
* MaxMind Terms of Use: https://www.maxmind.com/en/terms-of-use
* MaxMind GeoLite2 EULA: https://www.maxmind.com/en/geolite2/eula

This product includes GeoLite Data created by MaxMind, available from https://www.maxmind.com.

= GeoIP Database Downloads — DB-IP IP-to-City Lite =
This plugin can download the DB-IP IP-to-City Lite database to enable city-level geolocation.
No account, no license key, no EULA — anonymously downloadable.

* Source: DB-IP (https://db-ip.com), downloaded from https://download.db-ip.com/free/
* When: One-shot user click "Enable city-level geography" on the Geography page, then weekly via WordPress Cron for refresh
* Data sent: Standard HTTP request headers only (no visitor data, no account, no key)
* Data received: dbip-city-lite-YYYY-MM.mmdb.gz file, decompressed and stored in your uploads directory
* Purpose: Resolve approximate city/region from anonymized visitor IPs
* DB-IP Terms: https://db-ip.com/db/about/
* License: CC-BY 4.0

GeoIP data © DB-IP under CC-BY 4.0.

No visitor data is ever sent to any external service. All analytics data remains in your WordPress database.

== Privacy Policy ==

All analytics data stays in your WordPress database. No cookies, no fingerprinting, no external transfers. Daily-rotating salted hashes prevent cross-day tracking. Raw IPs are used only for GeoIP lookup and never stored. Integrates with the WordPress Privacy API for data export and erasure.

== Changelog ==

= 0.4.5 - 2026-04-28 =
* Added: One-click DB-IP IP-to-City Lite — free, account-less city-level geography. Click "Enable city-level geography" on the Geography page; ~80 MB downloads to your uploads directory. CC-BY-4.0.
* Added: New POST /wp-json/statnive/v1/diagnostics/enable-dbip-city endpoint (manage_options).
* Changed: GeoIPService::get_database_path() returns the first existing .mmdb (MaxMind wins ties, DB-IP is the free fallback).
* Changed: Weekly GeoIP cron now refreshes both providers when active; @set_time_limit(300) defends against PHP-FPM kills on managed hosts.

= 0.4.4 - 2026-04-27 =
* Added: Stale-aware cron health notice — fires only when a job is actually behind its grace window. Managed hosts that set DISABLE_WP_CRON while running system cron stay silent.
* Added: "Run cleanup now" button + per-user dismissal; notice suppressed on local/development environments.
* Added: cron.jobs[hook].next_run_iso / last_run_iso / is_stale + top-level any_stale in diagnostics.
* Fixed: statnive_last_purge timestamp format — now ISO 8601.

For older releases (0.4.3 and earlier), see CHANGELOG.md in the plugin source.

== Upgrade Notice ==

= 0.4.5 =
City-level geography is now one click away — free, no account, no key. Click "Enable city-level geography" on the Geography page.

= 0.4.4 =
Cron-disabled notice no longer false-positives on managed WP hosts. Adds "Run cleanup now" button + per-user dismissal.

= 0.4.3 =
Zero-config visitor geography on fresh installs. Drops the WP-nonce check that 403'd cached-page hits. Excluded IPs now actually block tracking.
