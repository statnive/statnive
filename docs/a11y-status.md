# Statnive — Accessibility Status

> Source: WordPress.org submission checklist §24 (`[QUALITY GATE]`).
> Target: WCAG 2.2 AA.
> Last reviewed: 2026-04-06 (Pass 2 audit, Phase A landed).

This document tracks Statnive's accessibility posture screen-by-screen and lists explicit waivers (deferred items) so a sighted reviewer can verify what is in scope for v0.3.1.

## Pass 2 Phase A landed (2026-04-06)

| Item | Status | Evidence |
|---|---|---|
| Focus-visible styles on all interactive elements | ✓ | `resources/react/globals.css` adds a base `:focus-visible` outline (2 px primary colour, offset 2 px) for `a`, `button`, `[role="button"]`, `[role="tab"]`, `[role="menuitem"]`, `input`, `select`, `textarea`, `summary`. Tested with Tab navigation in Chrome + Firefox. |
| Sortable column headers keyboard accessible | ✓ | `resources/react/components/shared/data-table.tsx` adds `tabIndex={0}`, `role="button"`, and `onKeyDown` (Enter/Space) on `<th>` cells when `col.sortable` is true. |
| Table headers have `scope="col"` | ✓ | Same file — every `<th>` now has `scope="col"`. |
| `aria-sort="none"` on sortable but unsorted columns | ✓ | Same file — defaults to `none` when sortable, omitted when not. |
| KPI delta badge conveys direction without colour | ✓ | `resources/react/components/shared/kpi-card.tsx` prepends `↑` / `↓` arrow + adds an `aria-label="Change up/down N% versus previous period"`. |
| Empty-state copy has next-step explanation | ✓ (Overview page) | `resources/react/pages/overview.tsx` empty-state strings now include "if nothing shows after 10 minutes, run the self-test under Settings → Diagnostics". Other pages still have terse copy — see Waivers below. |
| Admin notice (GeoIP) follows cause / fix / auto-action triplet | ✓ | `src/Admin/GeoIPNotice.php` now displays an explicit impact line and retry policy. |

## Per-screen status snapshot

| Screen | Tab order | Focus visible | `<th>` semantics | Colour-only state | Empty state copy | Notes |
|---|---|---|---|---|---|---|
| Overview | ✓ | ✓ | ✓ | ✓ | ✓ (P2 fix) | KPI cards now show ↑/↓ arrows. |
| Pages | ✓ | ✓ | ✓ | ✓ | ⚠ defer | Uses bare "No data available" — track in v0.3.2. |
| Referrers | ✓ | ✓ | ✓ | ✓ | ⚠ defer | Same as Pages. |
| Geography | ✓ | ✓ | ✓ | ✓ | ⚠ defer | Same. |
| Devices | ✓ | ✓ | ✓ | ✓ | ⚠ defer | Same. |
| Languages | ✓ | ✓ | ✓ | ✓ | ⚠ defer | Same. |
| Real-time | ✓ | ✓ | n/a | ✓ | ⚠ defer | List-based; no `<table>`. |
| Settings | ✓ | ✓ | n/a | ✓ | n/a | Form-based, no data tables. |

## Known waivers (deferred to v0.3.2)

| Item | Reason | Owner | Track |
|---|---|---|---|
| Charts (Recharts) accessible-name / data-table fallback | Recharts has limited a11y; needs an `<table>` fallback per chart | Frontend | Roadmap |
| Empty-state copy on Pages / Referrers / Geography / Devices / Languages / Real-time | Wave 1 of P2-B11 was Overview only; the rest follow the same pattern | Frontend | Roadmap |
| Automated a11y in CI (axe / pa11y / lighthouse-ci) (P2-W4 / §24.6) | Needs a Vitest-axe wiring + budget calibration | Eng | Roadmap |
| Skip-to-content link | Statnive lives inside the WP admin frame; the existing WP "Skip to main content" link covers admin pages | n/a | Won't fix |
| High-contrast mode test | Not yet validated against Windows High Contrast Mode | QA | Roadmap |

## Manual test recipe

A sighted reviewer can validate the Phase A fixes in 5 minutes:

1. Open Statnive admin.
2. Press Tab repeatedly. Every interactive element should show a visible blue ring.
3. Open the Overview page. Tab to a sortable column header. Press Enter — sort direction should toggle. Press Space — same.
4. Look at the KPI delta badges. They should show `↑ +12%` or `↓ -3%` (arrow before the percent), not just colour.
5. Inspect the Statnive GeoIP admin notice (enable GeoIP without a key). It should have:
   - A bold one-line cause
   - An "Impact:" line
   - An italic auto-action line ("retry weekly")
   - A clear "To fix:" call-to-action with a link
6. Trigger an empty Overview state (clear data) and confirm both empty boxes show the longer "if nothing shows after 10 minutes" copy with the Diagnostics pointer.

If any of these fails, file a bug with the screen + Tab step.
