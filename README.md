# Statnive — Privacy-first Analytics + Revenue Reports for WooCommerce

**Simple stats, clear decisions.**

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://statnive.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-8892BF.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759B.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588A.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Privacy-first analytics for WordPress, with a question-first **Ask me!** tab and a full **WooCommerce Revenue Report** built in. No cookies, no third-party transfers, no AI, no complicated dashboards. Just the answers you actually ask for.

- **Ask me! tab** — Pin your top questions ("How much traffic this week?", "Where is my traffic coming from?", "Which pages get the most views?") and read the answers the moment you open Statnive. 120 questions across 10 categories, all answered locally from your database. No AI, no LLM, no third-party API.
- **Cookieless by design** — No cookies, localStorage, or fingerprinting. Ever.
- **Real-time dashboard** — Active visitors, active pages, and a live pageview feed.
- **WooCommerce Revenue Report** — Net revenue, AOV, refund rate, channel attribution, top products, and the cart→checkout→purchase funnel, all inside WordPress, read-only against WooCommerce.
- **AI source tracking** — Dedicated channel for ChatGPT, Claude, Gemini, Perplexity and 9 more.
- **Self-hosted** — All data stays in your own WordPress database. Designed to support GDPR, CCPA and APPI compliance.

## Features

### Ask me! — your top questions, one click away
- **11 in-page tabs** — *Ask me!* (pinned home) plus 10 categories: Traffic, Real-time, Pages, Referrers, Campaigns, Geography, Devices, Engagement, Revenue, Events
- **Pin up to 10 questions** to the home tab so the answers fill in the moment you open Statnive
- **120 owner questions** built from the most-asked phrasings on Reddit, WP.org forums, Indie Hackers and the Shopify Community: *How much traffic this week?*, *Where is my traffic coming from?*, *Which pages get the most views?*, *What countries are my visitors from?*, *Is my traffic mobile or desktop?*, *How many people are on my site right now?*, *What's my best landing page?*, *How much organic search traffic?*, *Did my campaign work?*, *Is my tracking working?*, and 110 more
- **Search-by-keyword + answer modal** — type "mobile", "bounce", "checkout" and get a ranked list, open the answer in a focused popover without losing the tab you were on
- **No AI, no LLM, no third-party API** — every answer runs the same SQL as the dedicated report, locally against your database. Your questions never leave the server, your traffic never leaves the server, and the numbers always match what the reports show
- **Schema-gap and Paid-tier questions stay visible** with a quiet "Coming soon" caption so you can see what's on the way without paywalls or upsells in the v1 surface

### Analytics Dashboard
- **Top-level admin pages**: Overview (with Pages, Referrers, Geography, Devices, Languages, Real-time tabs), Ask me!, Revenue Report, Settings
- Real-time visitor counter and live pageview feed
- Comparison mode (current vs previous period)
- CSV export for all data views

### WooCommerce Revenue Report
- **Headline KPIs**: net revenue, orders, AOV, conversion rate, refund rate — with period-over-period deltas
- **Revenue by channel** — Organic Search, Paid Search, Social, Paid Social, Email, Direct, Referral, AI Assistants
- **Top products** ranked by revenue, with units and per-product trend
- **Cart → checkout → purchase funnel** with named drop-off rates
- **Zero-touch backfill** — historical WooCommerce orders are imported automatically the first time you open Statnive on a store with existing orders, via Action Scheduler in the background. No CLI required (but `wp statnive wc-backfill` is available if you prefer).
- **HPOS + Block Checkout compatible** — read-only against WooCommerce; the Recorder only ever calls `$order->get_*()` getters and never writes to a WooCommerce table or post meta.
- **Attribution from WooCommerce 8.5+ Order Attribution** — UTM, referrer, device, session — snapshotted at order record time, never live-joined.
- **No setup, no separate license, no tracking code to add** — install on a WooCommerce store and the Revenue tab works.

### Privacy & Compliance
- Cookieless tracking with daily-rotating SHA-256 salt
- Raw IPs never persisted — used only for the optional GeoIP lookup, then discarded
- WordPress Privacy API integration (personal-data exporter, eraser, policy generator)
- WP Consent API integration (Real Cookie Banner, Complianz, CookieYes)
- Three consent modes: full, cookieless, disabled-until-consent
- DNT and GPC respected server-side, on by default

### Tracking
- Eight-channel referrer grouping including a dedicated **AI Assistants** channel (ChatGPT, Claude, Gemini, Perplexity, Copilot, NotebookLM, Meta AI, Le Chat, Deepseek, You, iAsk, Jasper, Writesonic)
- Geography in four tiers — browser timezone, CDN country headers, optional one-click DB-IP IP-to-City Lite (free, CC-BY 4.0), optional MaxMind GeoLite2 (your own free key)
- Device detection via matomo/device-detector
- UTM parameter extraction and campaign tracking
- Custom events with auto-tracking (outbound links, form submissions, downloads)
- Engagement tracking (scroll depth, time-on-page via Visibility API — no heartbeats)
- Bot detection (UA patterns, webdriver, Math.random entropy)

### Integrations & Tools
- WordPress Site Health integration
- Keyboard shortcuts and WP Command Palette (`Ctrl/Cmd + K`)
- WP-CLI command `wp statnive cron run` for sites with `DISABLE_WP_CRON`
- Configurable retention (30 / 90 / 180 / 365 days, or Forever) with daily purge cron

## Requirements

- PHP 8.1 or higher
- WordPress 6.2 or higher

## Installation

1. Download the latest release from [Releases](../../releases)
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Click **Activate**

Your dashboard is ready at **WP Admin → Statnive**.

## Try It with WP Playground

Spin up a disposable test instance with no installation required:

```bash
npx @wp-playground/cli server --blueprint=blueprint.json
```

## Development

### Prerequisites

- PHP 8.1+ (dev/CI requires PHP 8.2+ because PHPUnit 11.5 needs 8.2+)
- Node.js 18+
- Composer

### Setup

```bash
cd statnive
composer install
npm install
```

### Build

```bash
npm run build            # Build React SPA + tracker
npm run build:react      # Build React SPA only
npm run build:tracker    # Build tracker only (<5KB gzipped)
```

### Dev Server

```bash
npm run dev              # Vite dev server with HMR
npm run dev:tracker      # Watch mode for tracker
```

### Testing

```bash
composer test             # PHP unit + integration tests
npm run test              # Vitest component tests
npx playwright test       # E2E tests
composer phpstan          # Static analysis
composer phpcs            # WordPress Coding Standards
```

## Architecture

| Layer | Stack |
|-------|-------|
| **Backend** | PHP 8.1+, WordPress Plugin API, PSR-4 autoloading, service container |
| **Frontend** | React 18, TypeScript, TanStack Router/Query, Tailwind CSS, shadcn/ui, Recharts |
| **Tracker** | Vanilla JS, IIFE bundle (<5KB gzipped), compile-time feature flags |
| **Database** | 26 normalized tables (21 analytics + 5 WooCommerce: orders, attribution, items, refunds, coupons), star schema, binary visitor hashes, pre-aggregated summaries, DECIMAL(19,4) money columns |
| **Privacy** | SHA-256 hashing, daily-rotating CSPRNG salt, zero persistent PII |

## License model

Statnive ships fully open-source under GPLv2 or later. There is no licensing system in this codebase and no paywalled features — every screen, integration and tracking capability described above is available to every user who installs the plugin.

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test && npm run test`)
5. Submit a pull request

### Pre-commit hook

Running `npm install` automatically sets `core.hooksPath=.githooks`, activating the pre-commit gate at `.githooks/pre-commit`. It runs:

- `phpcs` on staged PHP files (scoped, fast)
- `phpunit --testsuite unit` when PHP files are staged
- `vitest run` when TS/JS files are staged

`tsc --noEmit` is not run in the hook because of a pre-existing TS baseline that needs to be fixed separately. CI still runs the full gate (integration, Playwright, k6, typecheck).

Expected runtime: 5–15 seconds. Emergency bypass: `git commit --no-verify`.

Run the gate manually at any time:

```bash
composer gate && npm run gate
```

## License

Statnive is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
