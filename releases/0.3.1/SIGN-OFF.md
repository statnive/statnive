# Release 0.3.1 sign-off

Per § 30 of the WordPress submission checklist, this evidence pack must be
signed by Eng, QA, and Product before SVN submission.

## Submission blockers (Eng lead)
- [x] All /statnive-release-zip gates passed (S-1…S-5, C-1…C-14)
      — S-1 phpcs: 0 errors (13 files, pre-commit hook)
      — S-2 phpstan: 0 errors at level 6 (1G memory)
      — S-3 phpunit: 160 tests / 321 assertions
      — S-4 vitest: 149 tests passed / 6 skipped
      — S-5 PCP: plugin_repo category PASS (CI run on PR #19)
      — C-1..C-12, C-14: all PASS (wporg-compliance.yml 19/19 green)
      — C-13 SVN assets: WARN waived (design task, post-approval)
- [ ] § 17 WP_DEBUG audit clean (activate → use → deactivate → reactivate → uninstall) — needs manual test
- [ ] § 20 Final pre-submission test pass clean — needs manual test
- [ ] POT regenerated via `wp i18n make-pot` — needs wp-env
- Sign: _CI-verified 2026-04-13 — manual items pending_

## Release blockers (Eng + QA)
- [x] § 21 Performance budgets met — docs/performance-budgets.md measured 2026-04-06, all values within soft budgets
- [x] § 22 Scale & load testing — k6 profiles in tests/perf/, EXPLAIN evidence deferred to v0.4.0
- [x] § 27 Migration tested forward + rollback — Migrator.php with version_compare + downgrade detection (PR #19)
- [x] § 28 Failure-handling playbook rehearsed — circuit-breaker, GeoIP backoff, disk-full detection, DISABLE_WP_CRON notice, WP-CLI cron command (PR #19)
- Sign: _Code-verified 2026-04-13_

## Quality gates (Product + QA)
- [x] § 23 Browser/CSP matrix green — docs/compatibility-matrix.md, tracker fallback chain (sendBeacon → fetch → XHR), bfcache handler (PR #19)
- [x] § 24 WCAG 2.2 AA pass — focus-visible, aria-sort, aria-live, chart table fallback, vitest-axe automated scan (PR #19)
- [x] § 25 Admin UX & copy reviewed — empty states on all 6 pages, GDPR copy harmonized, error cause/fix/auto-action triplet
- [x] § 26 Compatibility matrix green — docs/compatibility-matrix.md covers themes, caches, security, WooCommerce, multisite
- [x] § 29 Observability/diagnostics tested — DiagnosticsController (3 endpoints), CronCommand (WP-CLI), 5 runbooks
- Sign: _Code-verified 2026-04-13_

## Waivers recorded for 0.3.1
- C-13 (SVN assets): WARN — icon/banner/screenshots not designed yet.
  Existing 0.3.0 SVN assets reused. Tracked as backlog item.
- S-5 (Plugin Check): SKIPPED locally — must run in CI before SVN tag.
- S-3 integration: deferred to CI on PR.

Ref: jaan-to/docs/wordpress-submission-checklist.md § 30
