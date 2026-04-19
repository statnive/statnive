# Statnive — Performance Budgets

> Source: WordPress.org submission checklist §21 (`[RELEASE BLOCKER]`).
> Last measured: 2026-04-06 against `chore/wp-org-submission-audit`.
> Budget regressions block a release until reviewed.

These budgets are the soft + hard ceilings Statnive ships against. The "soft" number is the warning threshold; the "hard" number is a release blocker.

## 1. Front-end beacon overhead

Measured against the public-facing tracker bundle that Statnive injects into pages.

| Asset | Soft (gzip) | Hard (gzip) | Measured 2026-04-06 |
|---|---|---|---|
| `public/tracker/statnive.js` (full tracker) | 2.0 KB | 3.0 KB | ~1.2 KB ✓ |
| `public/tracker/statnive-core.js` (inline core) | 0.6 KB | 1.0 KB | ~0.5 KB ✓ |
| Per-pageview HTTP body to `/wp-json/statnive/v1/hit` | 600 B | 1.0 KB | ~280 B ✓ |
| Tracker `connect-src` origins | 1 (same-origin) | 1 (same-origin) | 1 ✓ |

Cold-cache impact (mobile, throttled 3G, Lighthouse mobile preset):
- Tracker JS adds ≤ +50 ms to TTFB on the first visit when cache is empty.
- Warm-cache cost: negligible (browser cache + HTTP cache).
- Two-stage loading (`statnive-core` inlined in `wp_footer`, full tracker async after `load`) keeps the LCP path clean.

## 2. Admin dashboard / reporting budget

Measured with Query Monitor on a clean WP install with the default theme on the `chore/wp-org-submission-audit` branch. All numbers are p95 of 5 reloads with both transient + object cache cleared.

| Screen | Queries (soft) | Queries (hard) | Total query time (soft) | Peak memory (soft) |
|---|---|---|---|---|
| Overview | 8 | 15 | 80 ms | 24 MB |
| Pages | 10 | 18 | 120 ms | 24 MB |
| Referrers | 8 | 15 | 100 ms | 24 MB |
| Geography | 6 | 12 | 80 ms | 24 MB |
| Devices | 6 | 12 | 80 ms | 24 MB |
| Languages | 5 | 10 | 60 ms | 24 MB |
| Real-time (poll) | 1 | 2 | 20 ms / poll | 8 MB |
| Settings | 3 | 6 | 30 ms | 16 MB |

Polling cadence on Real-time is 30 s by default; the admin bar widget polls at 60 s.

## 3. Database growth budget

Calibrated against three traffic profiles (sessions/day). Storage = compressed InnoDB row size including index pages.

| Profile | Sessions/day | Pageviews/day | New rows/day | Storage/day | 30-day | 90-day | 365-day |
|---|---|---|---|---|---|---|---|
| Small (blog) | 200 | 600 | ~700 | ~50 KB | ~1.5 MB | ~4.5 MB | ~18 MB |
| Medium | 5,000 | 15,000 | ~17,000 | ~1.2 MB | ~36 MB | ~108 MB | ~440 MB |
| Large | 50,000 | 150,000 | ~165,000 | ~12 MB | ~360 MB | ~1.1 GB | ~4.4 GB |

Operators on the **Large** profile should enable an aggressive retention policy (90 days or less) and run the `wp statnive cron run` command at least daily via system cron rather than relying on WP-Cron.

## 4. Cron job runtime budget

| Job | Soft | Hard | Notes |
|---|---|---|---|
| `statnive_daily_salt_rotation` | < 200 ms | < 1 s | Single `update_option()` write. |
| `statnive_daily_aggregation` | < 5 s | < 30 s | One day of summary rollups. Backfill (>1 day) reschedules. |
| `statnive_daily_data_purge` | < 5 s / 1k rows | < 30 s / 1k rows | Re-schedules itself when more than 1 000 rows remain (chunked). |
| `statnive_weekly_geoip_update` | < 30 s | < 120 s | MaxMind download + extract. Network-bound. |

If `DISABLE_WP_CRON` is set, the admin notice in `src/Admin/GeoIPNotice.php` warns about scheduled work and points the user at `wp statnive cron run` (Phase B follow-up — see §29 / fix-plan-pass-2).

## 5. Regression policy

- **Soft regression** → flagged in PR review, must be justified in the release ticket.
- **Hard regression** → blocks the release until the budget is renegotiated or the regression is fixed.
- Each release re-runs the measurements above on a clean WP install and records them in `releases/{version}/evidence-pack/perf.md`.

## 6. Out of scope (deferred to v0.3.2 / v0.4.0)

- 1 M-row and 10 M-row synthetic dataset measurements (§22.3) — needs a dataset builder.
- 72-hour soak test under cron starvation (§22.6) — needs k6 + system cron coordination.
- EXPLAIN evidence for every primary report query (§22.4) — partial; tracked under `docs/explain-evidence.md` (TODO).
