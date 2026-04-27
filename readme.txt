=== Statnive ===
Contributors: statnive
Tags: analytics, statistics, privacy, tracking, dashboard
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.4.3
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

Statnive resolves approximate country in three tiers, falling through automatically:

1. **Zero-config — browser timezone.** Each tracker payload already carries the visitor's IANA timezone (`America/New_York`, etc.). Statnive maps it to a country via a static IANA tzdb lookup shipped in the plugin. No setup, no external service, ~80% accurate.
2. **CDN country headers.** If your site sits behind Cloudflare, AWS CloudFront, or Vercel (free tiers set a country header), Statnive reads `CF-IPCountry` / `CloudFront-Viewer-Country` / `X-Vercel-IP-Country` per request — more accurate than timezone when present.
3. **MaxMind GeoLite2.** Configure a free MaxMind license key in Settings → GeoIP to unlock city + region precision from the raw IP before it is discarded.

No outbound request is made for tiers 1 or 2; both arrive with the page view.

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

= 0.4.3 - 2026-04-27 =
* Added: Zero-config visitor geography. New IANA-timezone fallback resolves country from each browser's timezone — works on fresh installs without Cloudflare or MaxMind. Pure in-process, no external service contact.
* Added: Settings Save button with dirty tracking; "Your IP" hint with one-click exclude; per-control descriptions on every Settings control.
* Added: Real-production Playwright E2E suite for consent modes, DNT/GPC, retention, IP exclusions.
* Changed: Default Data Retention is now "Forever" (was 90 days).
* Fixed: Excluded IPs / CIDR ranges now actually block tracking — ExclusionMatcher was previously cosmetic.
* Removed: WP-nonce check on public tracker endpoints. WP nonces tick every 12-24h and 403'd every cached-page hit. HMAC remains the CSRF boundary.
* Removed: "Full Tracking" consent mode (was behaviorally identical to Cookieless; legacy installs coerced).
* Removed: Email Reports subsystem (deferred — will return with delivery diagnostics).

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

For older releases (0.3.x and earlier), see CHANGELOG.md in the plugin source.

== Upgrade Notice ==

= 0.4.3 =
Zero-config visitor geography on fresh installs (no CDN or MaxMind needed). Drops the WP-nonce check that 403'd cached-page hits. Excluded IPs now actually block tracking.

= 0.4.0 =
Full WordPress.org submission readiness. Dashboard now translatable. Adds circuit-breaker, GeoIP backoff, bfcache support, chart accessibility.
