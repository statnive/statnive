=== Statnive ===
Contributors: statnive
Tags: analytics, statistics, privacy, tracking, dashboard
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first WordPress analytics. Simple stats, clear decisions. No cookies, no third-party scripts, 100% self-hosted.

== Description ==

**The privacy-first analytics plugin for WordPress.**

Statnive gives WordPress site owners fast, smart, and easy-to-understand analytics without complicated setup or confusing dashboards. All data stays on your server — no cookies, no fingerprinting, no third-party transfers.

[See the live demo →](https://statnive.com/demo)

= Why Statnive? =

* **Channel intelligence** — Automatically groups traffic into Organic Search, Social Media, Direct, Referral, and Email so you see which channels drive real results.
* **Privacy by default** — No cookies, no localStorage, no fingerprinting. Designed to support GDPR, CCPA, and APPI compliance. Daily rotating salts make cross-day tracking impossible.
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

Statnive generates a daily visitor hash from the anonymized IP address and User-Agent string, salted with a cryptographically random key that rotates every 24 hours (with 48-hour overlap for session continuity). This means the same visitor gets a different hash each day, making cross-day tracking impossible while still providing accurate daily unique counts.

= Where is my analytics data stored? =

All data is stored in your WordPress database on your own server. Statnive creates its own tables (prefixed `statnive_`) and never sends data to external servers. When you uninstall the plugin, all tables are cleanly removed.

= Which browsers does the tracker support? =

The Statnive tracker uses standard browser APIs (`navigator.sendBeacon`, `fetch` with `keepalive`, `Intl.DateTimeFormat`) that ship in every modern browser. We test against the latest two major versions of Chrome, Firefox, Safari, and Edge, plus iOS WebKit. Older browsers will silently fall back to a no-op — analytics won't work, but your site is unaffected.

= What can cause "no data"? =

Common causes: ad blockers filtering analytics requests, aggressive page caching serving stale HTML, CSP blocking `fetch()`/`sendBeacon()` (ensure `connect-src 'self'`), privacy signals (GPC/DNT) honouring opt-outs by design, or `DISABLE_WP_CRON` preventing background jobs. See [troubleshooting guide](https://statnive.com/docs/troubleshooting) for details.

= Do I need to exclude URLs from page caches? =

Exclude `/wp-json/statnive/v1/hit` and `admin-ajax.php?action=statnive_hit` from page caches. Most caching plugins do this by default.

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

== Privacy Policy ==

All analytics data stays in your WordPress database. No cookies, no fingerprinting, no external transfers. Daily-rotating salted hashes prevent cross-day tracking. Raw IPs are used only for GeoIP lookup and never stored. Integrates with the WordPress Privacy API for data export and erasure.

== Changelog ==

= 0.4.2 - 2026-04-14 =
* Added: Device Distribution + Bot vs Human pie charts on Devices page.
* Added: DualBarCell (visitors/sessions bars) on all report tables.
* Fixed: Resolve 5 PCP warnings for zero-warning Plugin Check compliance.
* Fixed: Stop externalizing react-is (no WordPress global exists).
* Fixed: CI now fails on PCP warnings, not just errors.

= 0.4.1 - 2026-04-14 =
* Fixed: Externalize React/ReactDOM to wp-element instead of bundling (WP.org §8). Bundle size reduced 24%.
* Fixed: Add CSRF nonce to all public tracking endpoints (WP.org §7).
* Fixed: Register weekly cron interval — WordPress has no built-in weekly schedule (WP.org §9).
* Fixed: Set autoload=false for admin-only options to reduce alloptions bloat.

= 0.4.0 - 2026-04-13 =
* WordPress.org submission readiness: 24 audit items resolved.
* Dashboard fully translatable (~130 strings). Chart a11y, empty states, bfcache handler.
* Circuit-breaker, GeoIP backoff, host allow-list, AJAX rate limiting, downgrade detection.
* See CHANGELOG.md for full details.

= 0.3.1 - 2026-04-09 =
* Lowered runtime floor to WordPress 5.6 / PHP 8.0; PHPCompatibilityWP ruleset enforces it.
* Fixed UTM persistence and tuple-based campaign aggregation in referrers.
* Fixed `/hit` and `/event` REST args incorrectly marked required (regression from 0.3.0).
* Fixed dual-bar charts to use a shared scale across visitors and sessions.
* Fixed dashboard CSS leaking into WP admin chrome (now scoped under `#statnive-app`).
* Fixed Pages search input padding and wired search to entry/exit tables.
* Refactored API layer: extracted PayloadValidator, hardened privacy fall-through.

= 0.3.0 - 2026-04-06 =
* WordPress.org compliance pass. Removed license validation (Guideline 6). Added privacy hooks, MaxMind EULA compliance, i18n.

For older releases, see CHANGELOG.md in the plugin source.

== Upgrade Notice ==

= 0.4.0 =
Full WordPress.org submission readiness. Dashboard now translatable. Adds circuit-breaker, GeoIP backoff, bfcache support, chart accessibility, and automated a11y testing.

= 0.3.1 =
Fixes UTM persistence, REST `/hit` regression from 0.3.0, dashboard CSS leak into WP admin, dual-bar scaling, and Pages search wiring. Lowers runtime floor to WP 5.6 / PHP 8.0.

= 0.3.0 =
GeoIP now requires a MaxMind license key (free). Adds full i18n support and WordPress.org compliance fixes.
