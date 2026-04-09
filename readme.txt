=== Statnive ===
Contributors: statnive
Tags: analytics, statistics, privacy, tracking, dashboard
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first WordPress analytics. Simple stats, clear decisions. No cookies, no third-party scripts, 100% self-hosted.

== Description ==

**The privacy-first analytics plugin for WordPress.**

Statnive gives WordPress site owners fast, smart, and easy-to-understand analytics without complicated setup or confusing dashboards. All data stays on your server — no cookies, no fingerprinting, no third-party transfers.

[See the live demo →](https://statnive.com/demo)

= Why Statnive? =

* **Channel intelligence** — Automatically groups traffic into Organic Search, Social Media, Direct, Referral, and Email so you see which channels drive real results.
* **Privacy by default** — No cookies, no localStorage, no fingerprinting. GDPR, CCPA, and APPI compliant out of the box. Daily rotating salts make cross-day tracking impossible.
* **Zero-config setup** — Install, activate, done. No tracking code to paste, no account to create, no external service to connect.

= Key Features =

* **Real-time dashboard** — See who's on your site right now with live visitor count, active pages, and recent activity feed
* **Channel grouping** — Traffic sources automatically grouped into Organic Search, Social Media, Direct, Referral, and Email
* **Geographic data** — Country and city breakdowns using self-hosted GeoIP — no third-party lookups
* **Device detection** — Browser, OS, and device type breakdowns to understand your audience
* **Custom events** — Track button clicks, form submissions, file downloads, and outbound links
* **Bot detection** — Automatic filtering of bots, crawlers, and headless browsers
* **Privacy compliance** — DNT/GPC respect, configurable data retention, WordPress Privacy API (export/erase)
* **Email reports** — Weekly or monthly email summaries delivered to your inbox

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

= Is Statnive GDPR compliant? =

Statnive is **designed to support** GDPR, CCPA, and APPI compliance from the ground up: no cookies, no PII storage, daily rotating hashes, configurable data retention (30 days to 10 years), and full support for the WordPress Privacy API (data export and erasure requests). Compliance ultimately depends on how you configure and operate the plugin on your site.

= Does it work with WooCommerce? =

Statnive tracks pageviews and visitor analytics on any WordPress site, including WooCommerce stores. Dedicated WooCommerce revenue tracking with Revenue per Visitor (RPV) is planned for a future release.

= How much does it slow down my site? =

The tracker script is under 5KB gzipped. Server-side processing adds less than 25ms to page load (p95). Analytics data is pre-aggregated daily for fast dashboard queries that never touch raw event tables.

= Can I import data from other analytics plugins? =

Yes. Statnive supports importing historical data from WP Statistics and CSV files. Google Analytics 4 import is planned for a future release.

= Can I use Statnive alongside Google Analytics? =

Yes. Statnive runs independently and does not conflict with Google Analytics, Matomo, or any other analytics tool. Many users run Statnive as their privacy-compliant primary analytics while keeping GA4 for advertising attribution.

= How does visitor counting work without cookies? =

Statnive generates a daily visitor hash from the anonymized IP address and User-Agent string, salted with a cryptographically random key that rotates every 24 hours (with 48-hour overlap for session continuity). This means the same visitor gets a different hash each day, making cross-day tracking impossible while still providing accurate daily unique counts.

= Where is my analytics data stored? =

All data is stored in your WordPress database on your own server. Statnive creates its own tables (prefixed `statnive_`) and never sends data to external servers. When you uninstall the plugin, all tables are cleanly removed.

= Which browsers does the tracker support? =

The Statnive tracker uses standard browser APIs (`navigator.sendBeacon`, `fetch` with `keepalive`, `Intl.DateTimeFormat`) that ship in every modern browser. We test against the latest two major versions of Chrome, Firefox, Safari, and Edge, plus iOS WebKit. Older browsers will silently fall back to a no-op — analytics won't work, but your site is unaffected.

= What can cause "no data" or partial data loss? =

A few common things can prevent the tracker from reporting:

* **Ad blockers and privacy extensions** filter requests to anything that looks like analytics. There is no way around this — it's intentional on the visitor's part.
* **Aggressive page caching** can serve a stale HTML page that omits the tracker tag. If you use a custom cache, exclude the tracker endpoint (see the next FAQ).
* **CSP (Content Security Policy) misconfiguration** can block `fetch()` / `sendBeacon()` to your own site. Ensure `connect-src 'self'` (or your site origin) is allowed.
* **Strict privacy settings** like `Sec-GPC: 1` (Global Privacy Control) or `DNT: 1` cause Statnive to honour the opt-out and skip tracking — by design.
* **WP-Cron disabled** (e.g., `DISABLE_WP_CRON`) does not stop tracking, but it prevents data retention cleanup and GeoIP updates from running on schedule. Add a system cron or run `wp statnive cron run` manually.

= Do I need to exclude any URL from page caches? =

Yes — exclude the tracking endpoint from page caches. The endpoint is `/wp-json/statnive/v1/hit` (REST) and `wp-admin/admin-ajax.php?action=statnive_hit` (AJAX fallback). Most caching plugins exclude REST endpoints and admin-ajax by default, but if you use a custom cache rule, add these to the exclusion list.

== Screenshots ==

1. All your key metrics in one view — visitors, events, and pageviews with trend comparison
2. Know where your visitors come from — country and city breakdown without third-party services
3. Understand your audience — device types, browsers, and operating systems at a glance
4. See who's on your site right now — live visitor count with active pages and recent activity

== External Services ==

This plugin connects to the following third-party services under specific conditions:

= GeoIP Database Downloads =
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

No visitor data is ever sent to any external service. All analytics data remains in your WordPress database.

== Changelog ==

= 0.3.0 - 2026-04-06 =
* WordPress.org submission compliance pass — see CHANGELOG.md for the full list.
* Removed all bundled license validation per Guideline 6.
* Hardened tracking endpoints: REST schema validation, 8 KB body cap, salted SHA-256 rate limit, GPC-first opt-out.
* Added five privacy filter hooks (`statnive_should_track`, `statnive_require_consent`, `statnive_has_visitor_consent`, `statnive_respect_dnt`, `statnive_respect_gpc`).
* Added MaxMind GeoLite EULA compliance (user-supplied key required, no bundled mmdb).
* Added i18n infrastructure (`load_plugin_textdomain`, `wp_set_script_translations`, regenerated POT).
* Bumped Tested up to WordPress 6.9.

= 0.2.0 - 2026-04-05 =
* Fixed real-time dashboard, tracker URLs, Overview report, Recent Pageviews dedup, GeoIP download URL.
* Added regex-based UA fallback parser, `statnive_client_ip` filter, 38 regression tests.

= 0.1.1 - 2026-04-04 =
* Fixed zero-data Geography/Devices/Real-time pages and dashboard CSS bugs.
* Added `.distignore` for distribution packaging.

= 0.1.0 - 2026-04-02 =
* Initial release: real-time dashboard, cookieless tracking with rotating salts, GeoIP, device detection, custom events, email reports, CSV/WP Statistics import, WordPress Privacy API support.

== Upgrade Notice ==

= 0.3.0 =
GeoIP now requires a MaxMind license key (free). Adds full i18n support and WordPress.org compliance fixes.

= 0.2.0 =
Critical bug fixes for real-time dashboard, tracker URLs, and overview reports. Adds 38 regression tests.

= 0.1.1 =
Fixes zero-data display on Geography, Devices, and Real-time pages. All dashboard screens now show data correctly.

= 0.1.0 =
Initial release of Statnive — privacy-first WordPress analytics.
