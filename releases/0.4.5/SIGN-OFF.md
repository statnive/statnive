# Release 0.4.5 sign-off

Per § 30 of the WordPress submission checklist, this evidence pack must be
signed by Eng, QA, and Product before SVN submission.

## Submission blockers (Eng lead)
- [ ] All /statnive-release-zip gates passed (S-1…S-5, C-1…C-17) — see [gate-run.log](gate-run.log)
- [ ] § 17 WP_DEBUG audit clean (activate → use → click "Enable city-level geography" → wait for download → use → deactivate → reactivate → uninstall)
- [ ] § 20 Final pre-submission test pass clean on a clean WP install
- [ ] **DEFERRED: regenerate POT** — see [pot-mtime.txt](pot-mtime.txt). Required before SVN.
- [ ] **DEFERRED: re-run S-5 (Plugin Check) on the built ZIP via wp-env or CI** — wp-env was unavailable locally.
- Sign:

## Release blockers (Eng + QA)
- [ ] § 21 Performance budgets met (verify the 80 MB DB-IP download does not block site users; cron should run in background)
- [ ] § 22 Scale & load testing
- [ ] § 27 Migration tested forward + rollback (no schema migrations in 0.4.5 — confirm)
- [ ] § 28 Failure handling rehearsed (DB-IP 404 mid-month → exponential backoff → next-month URL recovers)
- Sign:

## Quality gates (Product + QA)
- [ ] § 23 Browser/CSP matrix green (CTA button uses standard fetch — no special CSP needs)
- [ ] § 24 WCAG 2.2 AA pass — verify the new CTA card and disabled-button states are screen-reader friendly
- [ ] § 25 Admin UX & copy reviewed: button label, error copy ("Settings → GeoIP" not "Diagnostics"), attribution footer
- [ ] § 26 Compatibility matrix green — especially with WP_Cron disabled environments (DB-IP cron tick won't fire automatically; system cron must hit wp-cron.php)
- [ ] § 29 Observability/diagnostics tested (`detect_source()` returns `'dbip_city'` once file lands; `geoip.dbip_city_active` true)
- Sign:

## v0.4.5 specific risks worth a closer look

1. **Two sequential downloads on weekly cron** — MaxMind (~70 MB) + DB-IP (~80 MB) total ~150 MB. `@set_time_limit(300)` defends against PHP-FPM kills on managed hosts, but the actual time spent depends on bandwidth. Verify on a 10 Mbps host.
2. **Initial DB-IP download mid-cron-cycle** — user clicks the button, single-event fires in ~5s, but if WP-Cron is disabled and system cron only fires hourly, the user sees no progress for up to 60 min. Documented in [pot-mtime.txt notwithstanding] — consider a UI hint.
3. **DB-IP URL contains the year-month** — at month boundaries, the previous month's URL 404s for ~24 h before the new month appears. Existing exponential-backoff handles this; verify the user-facing experience is acceptable (one cron tick gap, no error notice).
4. **CC-BY-4.0 attribution placement** — verify the "GeoIP data © DB-IP under CC-BY 4.0" footer renders only when `dbip_city` is the active source (not on MaxMind installs).

Ref: jaan-to/docs/wordpress-submission-checklist.md § 30
