# Statnive

**Simple stats, clear decisions.**

[![Version](https://img.shields.io/badge/version-0.1.0-blue.svg)](https://statnive.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-8892BF.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759B.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Privacy-first analytics for WordPress. No cookies, no third-party transfers, no complicated dashboards — just the metrics that matter.

- **Cookieless by design** — No cookies, localStorage, or fingerprinting. Ever.
- **Real-time dashboard** — See what's happening on your site right now.
- **Revenue attribution** — Track WooCommerce revenue per traffic source.
- **Self-hosted** — All data stays on your server. GDPR/APPI compliant by default.

![Statnive Dashboard](screenshot.png)

## Features

### Analytics Dashboard
- **8 dashboard screens**: Overview, Pages, Referrers, Geography, Devices, Languages, Real-time, Settings
- Real-time visitor counter with 5-second polling
- Comparison mode (current vs previous period)
- CSV export for all data views

### Privacy & Compliance
- Cookieless tracking with daily rotating salts (two-salt system, 48h overlap)
- IP anonymization with ephemeral lifecycle — raw IPs are never stored
- WordPress Privacy API compliance (data exporter, eraser, policy generator)
- Privacy audit dashboard with 10 compliance checks and score
- WP Consent API integration (Real Cookie Banner, Complianz, CookieYes)
- 3 consent modes: full, cookieless, disabled-until-consent
- DNT and GPC header support enabled by default

### Tracking
- GeoIP resolution via MaxMind GeoLite2-City
- Device detection via matomo/device-detector
- Referrer classification into 7 channels (Organic Search, Social Media, Direct, Referral, Email, Paid Search, Paid Social)
- UTM parameter extraction and campaign tracking
- Custom event tracking with auto-tracking (outbound links, forms, downloads)
- Engagement tracking (scroll depth, time-on-page)
- Bot detection (UA patterns, webdriver, Math.random entropy)

### Integrations
- WooCommerce revenue tracking
- Data import from WP Statistics and CSV
- Email reports (weekly/monthly)
- API key authentication for external access
- WordPress Site Health integration
- Keyboard shortcuts and WP Command Palette

## Requirements

- PHP 8.1 or higher
- WordPress 6.4 or higher

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

- PHP 8.1+
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
| **Database** | 21+ normalized tables, star schema, binary visitor hashes, pre-aggregated summaries |
| **Privacy** | SHA-256 hashing, daily rotating CSPRNG salts, zero persistent PII |

## Pricing

| | Free | Starter | Professional | Agency |
|---|---|---|---|---|
| **Price** | $0 | $49/yr | $99/yr | $199/yr |
| Real-time dashboard | ✓ | ✓ | ✓ | ✓ |
| Basic sources | ✓ | ✓ | ✓ | ✓ |
| Geo data | ✓ | ✓ | ✓ | ✓ |
| Data retention | 30 days | 1 year | Unlimited | Unlimited |
| Form tracking | — | ✓ | ✓ | ✓ |
| Custom events | — | 5 | Unlimited | Unlimited |
| Weekly email reports | — | ✓ | ✓ | ✓ |
| WooCommerce revenue | — | — | ✓ | ✓ |
| API access | — | — | ✓ | ✓ |
| WPML support | — | — | ✓ | ✓ |
| Heatmaps | — | — | — | ✓ |
| Meta CAPI | — | — | — | ✓ |
| White-label | — | — | — | ✓ |
| AI insights | — | — | — | ✓ |

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test && npm run test`)
5. Submit a pull request

## License

Statnive is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
