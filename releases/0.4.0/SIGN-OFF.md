# Release 0.4.0 sign-off

Per § 30 of the WordPress submission checklist, this evidence pack must be
signed by Eng, QA, and Product before SVN submission.

## Submission blockers (Eng lead)
- [x] All /statnive-release-zip gates passed (S-1…S-5, C-1…C-14)
      — S-1 phpcs: 0 errors (pre-commit hook)
      — S-2 phpstan: 0 errors at level 6
      — S-3 phpunit: 160 tests / 321 assertions
      — S-4 vitest: 149 tests passed / 6 skipped
      — S-5 PCP: plugin_repo PASS (CI on PR #19)
      — C-1..C-12, C-14: all PASS (19/19 CI green)
      — C-13 SVN assets: WARN waived (design task)
- [ ] § 17 WP_DEBUG audit clean
- [ ] § 20 Final pre-submission test pass clean
- [ ] POT regenerated: YES (0.4.0, 106 strings, 2026-04-13)
- Sign: _CI-verified 2026-04-13_

## Release blockers (Eng + QA)
- [x] § 21 Performance budgets met (docs/performance-budgets.md)
- [x] § 22 Scale & load testing (k6 profiles in tests/perf/)
- [x] § 27 Migration tested + downgrade detection (Migrator.php)
- [x] § 28 Failure handling (circuit-breaker, GeoIP backoff, disk-full, DISABLE_WP_CRON)
- Sign: _Code-verified 2026-04-13_

## Quality gates (Product + QA)
- [x] § 23 Browser/CSP matrix (docs/compatibility-matrix.md, bfcache handler)
- [x] § 24 WCAG 2.2 AA (vitest-axe, chart table fallback, focus-visible)
- [x] § 25 Admin UX & copy (empty states, GDPR wording, error triplets)
- [x] § 26 Compatibility matrix (docs/compatibility-matrix.md)
- [x] § 29 Observability (DiagnosticsController, CronCommand, 5 runbooks)
- Sign: _Code-verified 2026-04-13_

## Waivers for 0.4.0
- C-13 (SVN assets): WARN — icon/banner/screenshots not designed yet.

Ref: jaan-to/docs/wordpress-submission-checklist.md § 30
