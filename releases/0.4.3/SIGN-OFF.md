# Release 0.4.3 sign-off

Per § 30 of the WordPress submission checklist, this evidence pack must be
signed by Eng, QA, and Product before SVN submission.

## Submission blockers (Eng lead)
- [ ] All /statnive-release-zip gates passed (S-1…S-5, C-1…C-17) — see [gate-run.log](gate-run.log)
- [ ] § 17 WP_DEBUG audit clean (activate → use → deactivate → reactivate → uninstall)
- [ ] § 20 Final pre-submission test pass clean on a clean WP install
- [ ] **DEFERRED: regenerate POT** — see [pot-mtime.txt](pot-mtime.txt). Required before SVN.
- [ ] **DEFERRED: re-run S-5 (Plugin Check) on the built ZIP via wp-env or CI** — wp-env was unavailable locally.
- Sign:

## Release blockers (Eng + QA)
- [ ] § 21 Performance budgets met
- [ ] § 22 Scale & load testing
- [ ] § 27 Migration tested forward + rollback (no schema migrations in 0.4.3 — confirm)
- [ ] § 28 Failure-handling playbook rehearsed
- Sign:

## Quality gates (Product + QA)
- [ ] § 23 Browser/CSP matrix green (verify timezone-tier behaviour in browsers without `Intl.DateTimeFormat`)
- [ ] § 24 WCAG 2.2 AA pass
- [ ] § 25 Admin UX & copy reviewed (timezone-tier dashboard empty-state messages, "geography" page)
- [ ] § 26 Compatibility matrix green (especially with cache plugins now that the WP-nonce check is gone)
- [ ] § 29 Observability/diagnostics tested (`detect_source()` returns `'timezone'` on zero-config hosts)
- Sign:

## v0.4.3 specific risks worth a closer look

1. **Tracker bundle in cached pages** — sites running cached HTML still serve
   the v0.4.2 tracker bundle (with `_statnonce` send code). Server-side
   `_statnonce` handling is now silent-accept (kept in ALLOWED_KEYS). Verified
   live: stale-nonce hits return 204. Re-confirm on an actual cached host.
2. **Timezone-tier accuracy** — multi-country zones (e.g. Europe/Zurich = CH/DE/LI)
   map to the IANA-primary country. Acceptable for an analytics-grade signal;
   document in admin UI copy.
3. **Composer.json in ZIP** — flagged by C-14.3 leak regex. Per LEARN.md this
   is intentional (v0.4.2 PCP fix). The check script's regex needs a follow-up
   refinement to exclude composer.json while keeping composer.lock forbidden.

Ref: jaan-to/docs/wordpress-submission-checklist.md § 30
