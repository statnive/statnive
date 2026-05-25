=== Statnive – Privacy-first Analytics + Revenue Reports for WooCommerce ===
Contributors: parhumm
Tags: analytics, privacy, statistics, gdpr, cookieless
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookieless WordPress analytics with WooCommerce revenue and an Ask me! tab that answers your top questions in one click. Privacy-first, no AI.

== Description ==

**The privacy-first analytics plugin for WordPress, with an Ask me! tab that turns your top questions into one-click answers.**

See exactly who visits your site, where they come from, what they do, and on WooCommerce stores how that converts into revenue. No cookies, no third-party trackers, no visitor data sent anywhere. All analytics and revenue data live in your own WordPress database.

= Just ask. No reports to dig through. =

Most analytics dashboards make you learn the tool before you can answer a question. Statnive's **Ask me!** tab flips that around. Pin your 5 favourite questions and the answers fill in the moment the page loads. Browse 10 category tabs (Traffic, Pages, Referrers, Geography, Devices, Real-time, Engagement, Revenue, Events, Campaigns) and click any question to expand the live answer with the same date range you set everywhere else.

Owners typically open Statnive to ask:

* **How much traffic this week?**
* **Where is my traffic coming from?**
* **Which pages get the most views?**
* **What countries are my visitors from?**
* **Is my traffic mobile or desktop?**
* **How many people are on my site right now?**
* **What's my best landing page?**
* **How much organic search traffic?**
* **Did my campaign work?**
* **Is my tracking working?**

Every answer is computed locally from your WordPress database. No AI, no LLM, no third-party API, no question ever leaves your server. The same SQL that powers the dedicated reports answers the Ask me! cards, so the numbers always match what the reports show.

Open source under GPLv2. Self-hosted in your own database — nothing ever leaves your server.

Install, activate, open Statnive — your dashboard fills up within minutes. No tracking code to paste, no account to create. If WooCommerce is active, existing orders are auto-imported in the background so the Revenue Report fills in within minutes.

= Why Statnive? =

* **No cookies. No fingerprinting. No third-party transfers.** Designed to support GDPR, CCPA, and APPI compliance.
* **Honors GPC and DNT** server-side, integrates with the WordPress Consent API.
* **Daily-rotating salted hashes** — cross-day and cross-site tracking are mathematically impossible.
* **One plugin for analytics AND WooCommerce revenue** — no separate add-on, no separate license, no clutter, no upsells.

= Key Features =

* **Ask me! tab** — 120 owner questions across 10 categories, answered straight from your database. Pin the 5 you ask most so they sit at the top of every visit. Search by keyword, jump to the question, see the answer. No AI, no third-party API, no data leaves your server.
* **WooCommerce Revenue Report** — Net revenue, orders, AOV, conversion rate, refund rate (with period-over-period deltas) · revenue by channel (8 channels including AI Assistants) · top products · cart→checkout→purchase funnel with named drop-off rates. Built on WooCommerce 8.5+ Order Attribution; HPOS + Block Checkout compatible; read-only against WooCommerce.
* **Zero-touch backfill for existing stores** — On a WooCommerce store with existing orders, Statnive auto-imports your history in the background via Action Scheduler the first time you open the Revenue Report. No CLI required (but `wp statnive wc-backfill` is also available).
* **Real-time** — Active visitor count, active pages, live pageview feed.
* **Smart channel grouping** — Direct, Organic Search, Social Media, Email, Referral, Paid Search, Paid Social, and a dedicated **AI Assistants** channel for ChatGPT, Claude, Gemini, Perplexity, Copilot, NotebookLM, Meta AI, Le Chat, Deepseek, You, iAsk, Jasper, and Writesonic.
* **Custom events + engagement** — Link clicks (and tagged button clicks via `statnive-event-*` classes), form submissions, downloads, outbound links, time on page, scroll depth.
* **Bot vs human separation** — Real visitors and automated traffic in distinct buckets.
* **Geography in tiers** — Zero-config timezone country mapping; optional CDN headers; optional one-click DB-IP city download (free); optional MaxMind GeoLite2.
* **Configurable retention** — 30 / 90 / 180 / 365 days, or Forever. Daily WP-Cron purge.
* **WordPress Privacy API** — Personal-data export and erase registered automatically.
* **WP-CLI** — `wp statnive cron run` for sites with `DISABLE_WP_CRON`; `wp statnive wc-backfill` for manual WooCommerce imports.

Source code at [github.com/statnive/statnive](https://github.com/statnive/statnive). Learn more at [statnive.com](https://statnive.com).

== Installation ==

1. Upload the `statnive` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Open **Statnive** in the admin sidebar.

That's it. Tracking begins immediately — no configuration, no account, no tracking code to paste.

== Frequently Asked Questions ==

= What is the Ask me! tab? =

Ask me! is a question-first dashboard. Instead of learning which report answers which question, you pick a question in plain language and Statnive shows the answer inline with the same date range you set everywhere else. It ships with 120 questions across 10 categories (Traffic, Real-time, Pages, Referrers, Campaigns, Geography, Devices, Engagement, Revenue, Events) and lets you pin your favourite 10 to the home tab so the answers load the moment you open Statnive.

Common starter questions include *How much traffic this week?*, *Where is my traffic coming from?*, *Which pages get the most views?*, *What countries are my visitors from?*, *Is my traffic mobile or desktop?*, *How many people are on my site right now?*, *Is my tracking working?*

= Does Ask me! send my questions to an AI or any external service? =

No. The 120 questions are pre-built and live entirely inside the plugin. Each question maps to a SQL query that runs locally against your WordPress database, exactly the same SQL the dedicated reports use. There is no AI, no LLM, no chat API, and nothing about your traffic ever leaves your server.

= Does Statnive use cookies? =

No. Statnive is 100% cookie-free. Visitor identification is a daily-rotating salted hash that cannot be used to track individuals across days or sites.

= Is Statnive GDPR compliant? =

Statnive is **designed to support** GDPR, CCPA, APPI, and PIPL compliance: no cookies, no PII storage, daily rotating hashes, configurable retention, WordPress Privacy API export/erase, and server-side respect for GPC and DNT signals. Final compliance always depends on your configuration and your privacy policy.

= How does visitor counting work without cookies? =

A salted SHA-256 hash of the visitor's anonymized IP plus User-Agent. The salt rotates daily, so the same visitor gets a different hash tomorrow — cross-day stitching is impossible while daily uniques stay accurate.

= Where is my data stored? =

In your WordPress database, in tables prefixed `statnive_`. Nothing leaves your server unless you explicitly enable the optional MaxMind or DB-IP GeoIP downloads (one-time database files, never visitor data). When you uninstall the plugin, all tables and uploaded GeoIP files are removed.

= Does it work with WooCommerce? =

Yes — Statnive ships a full WooCommerce Revenue Report. Net revenue, orders, AOV, refund rate, revenue by channel (UTM + referrer + AI assistants), top products, and the cart → checkout → purchase funnel — all inside WordPress, on the **Statnive → Revenue Report** page. The Revenue Report is read-only against WooCommerce (the Recorder only ever calls `$order->get_*()` getters; it never writes to a WooCommerce table or order meta) and is compatible with HPOS and Block Checkout.

If you install Statnive on a store that already has orders, the historical data is imported automatically in the background via Action Scheduler. No CLI required, but `wp statnive wc-backfill` is available if you prefer to drive it manually.

= How much does it slow down my site? =

The tracker script is small (~2 KB gzipped) and loads asynchronously, so it does not block your page render. The hit endpoint writes a single row per pageview. Dashboard queries run against pre-aggregated daily summaries rather than raw events.

= Can I run Statnive alongside Google Analytics or Matomo? =

Yes. Statnive is fully independent. Many users run Statnive as their primary privacy-friendly analytics and keep GA4 for advertising attribution.

= What can cause "no data"? =

Common causes: ad blockers, aggressive page caching, CSP rules blocking `fetch`/`sendBeacon` (allow `connect-src 'self'`), GPC or DNT enabled, or `DISABLE_WP_CRON` without a system cron. Exclude `/wp-json/statnive/v1/hit` and `admin-ajax.php?action=statnive_hit` from page caches.

= How does Geography work? =

Four tiers, falling through automatically: (1) browser timezone → country, ~80% accurate, no external call; (2) CDN country headers (Cloudflare, CloudFront, Vercel) when present; (3) optional one-click DB-IP IP-to-City Lite (free, CC-BY 4.0); (4) optional MaxMind GeoLite2 (free with an account). Tiers 3 and 4 are opt-in via a discrete user click.

= Does Statnive count bots as real visitors? =

No. ~200 server-side bot UA patterns and tracker-side fingerprints (webdriver, automation flags) bucket bots separately, so "Visitors" and "Pageviews" reflect humans only.

== Screenshots ==

1. WooCommerce Revenue Report — net revenue, AOV, orders, refund rate, and revenue by channel (8 channels including AI Assistants), all inside WordPress. HPOS + Block Checkout compatible.
2. Ask me! tab — pin your top 5 questions and read the answers the moment you open Statnive. 120 pre-built questions, computed locally, no AI, no third-party API, no data leaves your server.
3. Privacy-first analytics overview — visitors, sessions, pageviews and trends in one fast dashboard. Cookieless, no fingerprinting, no third-party trackers.
4. Real-time visitors and live pageviews — verify a campaign, watch a launch, confirm your tracking is alive, all without leaving WordPress.
5. Visitor map and country breakdown — see which countries drive your WordPress traffic, with privacy-safe coarse geolocation (no IP storage, optional GeoIP).
6. Traffic sources summary — referral, direct, organic search, social, and AI assistants ranked side by side. Know where every visit really came from.
7. Top pages report — every page on your site ranked by views, with instant search and sort. Find your best content in seconds.
8. Entry and exit pages, side by side — find what brings visitors in and where they leave. The foundation of every WordPress conversion-rate improvement.
9. Smart channel grouping — Direct, Organic Search, Social Media, Email, Referral, Paid, and a dedicated AI Assistants channel (ChatGPT, Claude, Gemini, Perplexity, Copilot, and more).
10. Desktop, mobile, tablet, bots — device, browser, and OS breakdowns in one privacy-first view. Real humans and bots stay in separate buckets.
11. Reach across languages and regions — see which languages your WordPress visitors actually speak, and prioritise translations with confidence.
12. Ask me! — Traffic answers. "How much traffic this week?" "Is traffic up or down?" Pre-built questions answered straight from your database, no AI.
13. Ask me! — Referrer answers. "Which source drove the most visits?" "How much AI assistant traffic?" Same numbers as the dedicated report.
14. Ask me! — Pages answers. "What are my top pages this week?" "Best landing page?" One click, no dashboard fishing.
15. Ask me! — Real-time answers. "How many people are on my site right now?" "Is my tracking working?" Pinned, live, zero clicks.
16. Ask me! — Campaign answers. "Did my last campaign work?" UTM-aware, WooCommerce-aware, answered straight from your database.
17. Ask me! — Device answers. "Is my traffic mobile or desktop?" "Which browsers do my visitors use?" Bots stay separate from humans.

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
* DB-IP Terms: https://db-ip.com/tos.php
* DB-IP Privacy Policy: https://db-ip.com/privacy.php
* License: CC-BY 4.0

GeoIP data © DB-IP under CC-BY 4.0.

No visitor data is ever sent to any external service. All analytics data remains in your WordPress database.

== Privacy Policy ==

All analytics data stays in your WordPress database. Raw IPs are used only for the optional GeoIP lookup and are never persisted. Statnive registers with the WordPress Privacy API for personal-data export and erasure.

== Changelog ==

= 1.0.0 - 2026-05-20 =
* New: WooCommerce Revenue Report (v1.0.0).

= 0.4.13 - 2026-05-13 =
* Changed: PHP floor raised from 8.0 to 8.1 (PHP 8.0 EOL was Nov 2023).
* Changed: `geoip2/geoip2` bumped ^2.13 → ^3.0; `maxmind/web-service-common` bumped ~0.9.0 → ^0.11.
* Fix: DB-IP privacy-policy URL in External Services disclosure (404 → canonical). See CHANGELOG.md.

= 0.4.12 - 2026-05-11 =
* Fix: replace competitor name in Tags line with `cookieless` for WP.org policy compliance. See CHANGELOG.md.

= 0.4.11 - 2026-05-09 =
* Removed: importers (WP Statistics + CSV) — orphan feature whose cron handler was never registered. See CHANGELOG.md.

= 0.4.10 - 2026-05-05 =
* New: MaxMind GeoIP card on the admin Settings page (license-key input + Enable toggle, drives the existing server-side options).
* Internal: centralized the masked-license-key sentinel as a shared PHP/TS constant; new SettingsController integration test and ConsentApiIntegration unit test close prior coverage gaps. See CHANGELOG.md.

= 0.4.9 - 2026-05-04 =
* Fix: tracker skips excluded_roles + dist ZIP excludes hidden files. See CHANGELOG.md.

= 0.4.7 =
First public release on WordPress.org. Real-time dashboard, eight-channel grouping with a dedicated AI Assistants channel, four-tier geography, custom events + engagement, cookieless privacy modes, WordPress Privacy API, configurable retention, and the `wp statnive cron run` WP-CLI command. Source code and full history: https://github.com/statnive/statnive.

== Upgrade Notice ==

= 0.4.7 =
First public release on WordPress.org.
