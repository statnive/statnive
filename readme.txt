=== Statnive ===
Contributors: statnive
Tags: analytics, statistics, privacy, tracking, dashboard
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
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

= Pro Features (planned) =

* WooCommerce revenue tracking with Revenue per Visitor (RPV) — coming soon
* Unlimited custom events (free tier: 5 events) — coming soon
* All form integrations (Contact Form 7, Gravity Forms, Elementor) — coming soon
* Advanced reporting with PDF export — coming soon
* Campaign manager with UTM URL builder — coming soon
* External REST API with API key authentication — coming soon
* Priority support with SLA — coming soon

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

Yes. Statnive is designed for GDPR compliance from the ground up: no cookies, no PII storage, daily rotating hashes, configurable data retention (30 days to 2 years), and full support for WordPress Privacy API (data export and erasure requests).

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

* Source: MaxMind (https://www.maxmind.com)
* When: Weekly via WordPress Cron, only when GeoIP feature is enabled in Settings and a license key is configured
* Data sent: License key and standard HTTP request headers (no visitor data is transmitted)
* Data received: GeoIP database file, stored locally in your uploads directory
* Purpose: Determine approximate geographic location of visitors from anonymized IP addresses
* MaxMind Privacy Policy: https://www.maxmind.com/en/privacy-policy
* MaxMind Terms of Use: https://www.maxmind.com/en/terms-of-use
* MaxMind GeoLite2 EULA: https://www.maxmind.com/en/geolite2/eula

= StatNive License Validation (Optional) =
If you enter a premium license key, the plugin connects to the StatNive API.

* Service URL: https://api.statnive.com/v1/licenses
* When: Only when you manually activate a license key, and weekly thereafter to verify status
* Data sent: License key, site URL, plugin version
* Purpose: Validate premium license subscriptions
* StatNive Privacy Policy: https://statnive.com/privacy
* StatNive Terms of Service: https://statnive.com/terms

No visitor data is ever sent to any external service. All analytics data remains in your WordPress database.

== Changelog ==

= 0.3.0 - 2026-04-06 =
* Fix engagement-to-view correlation using pageview ID (pvid) token
* Fix engagement updates matching by URI path instead of resource_id
* Fix Avg Duration data pipeline — aggregate from views.duration
* Fix dashboard importmap output for WP compatibility
* Fix readme.txt WP.org compliance — remove false claims, fix URLs
* Fix unescaped output in admin UI components
* Fix email report numbers to use number_format_i18n() for locale-aware output
* Remove P3TERX GeoIP mirror — MaxMind license key now required (EULA compliance)
* Gate GeoIP download to opt-in (no auto-download)
* Gate license API to explicit user action only
* Make admin notices dismissible
* Add ABSPATH guards to all src/ PHP files
* Add date range persistence across all dashboard tabs via URL params
* Add two-stage tracker loading for optimal Web Vitals
* Add GPL v2 LICENSE file and THIRD-PARTY-LICENSES.md
* Add External Services section in readme.txt (with MaxMind EULA link)
* Add WP.org pre-submission CI workflow with 6 enforcement gates
* Add i18n infrastructure: load_plugin_textdomain() and languages/statnive.pot
* Add GeoIPNotice admin notices for missing MaxMind key and DISABLE_WP_CRON
* Add translatable strings throughout email reports
* Add MaxMind license key + GeoIP enable/disable settings in REST API
* Harden tracking endpoints: strict payload schema, 8 KB size cap, Content-Type enforcement
* Exclude unused premium stub modules from distribution ZIP
* Bump Tested up to WordPress 6.9

= 0.2.0 - 2026-04-05 =
* Fix real-time dashboard showing 0 active visitors due to stale cache
* Fix tracker not sending actual page URL (synthetic URIs like /page/0)
* Fix Overview report dropping today's data when visitors is zero
* Fix Pages report showing stale aggregated data over fresh numbers
* Fix duplicate entries in Recent Pageviews feed
* Fix GeoIP database never downloaded (broken CDN URL)
* Add regex-based UA fallback parser for device detection
* Add statnive_client_ip filter for local dev IP override
* Add GeoIP database auto-download on plugin activation
* Add 38 regression tests covering all fixed bugs

= 0.1.1 - 2026-04-04 =
* Fix zero-data Geography, Devices, and Real-time dashboard pages
* Fix dashboard CSS, crash, and zero-data rendering bugs
* Fix BotDetector regex and ExclusionMatcher regex patterns
* Resolve 4 critical + 4 medium production integrity issues
* Green Playwright E2E suite and integration tests
* Add plugin distribution packaging (.distignore)

= 0.1.0 - 2026-04-02 =
* Initial release
* Real-time analytics dashboard with 8 screens
* Privacy-first tracking (no cookies, rotating salts)
* GeoIP resolution, device detection, referrer classification
* Custom events, engagement tracking, bot detection
* Email reports, data import (WP Statistics, CSV)
* Full GDPR compliance (export, erase, policy generator)
* 4-tier licensing system (Free, Starter, Professional, Agency)

== Upgrade Notice ==

= 0.3.0 =
GeoIP now requires a MaxMind license key (free). Adds full i18n support and WordPress.org compliance fixes.

= 0.2.0 =
Critical bug fixes for real-time dashboard, tracker URLs, and overview reports. Adds 38 regression tests.

= 0.1.1 =
Fixes zero-data display on Geography, Devices, and Real-time pages. All dashboard screens now show data correctly.

= 0.1.0 =
Initial release of Statnive — privacy-first WordPress analytics.
