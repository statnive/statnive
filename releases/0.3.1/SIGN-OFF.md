# Release 0.3.1 sign-off

Per § 30 of the WordPress submission checklist, this evidence pack must be
signed by Eng, QA, and Product before SVN submission.

## Submission blockers (Eng lead)
- [ ] All /statnive-release-zip gates passed (S-1…S-5, C-1…C-14)
      — S-3 integration must be re-confirmed in CI / wp-env
      — S-5 PCP must be re-run with wp-env up
- [ ] § 17 WP_DEBUG audit clean (activate → use → deactivate → reactivate → uninstall)
- [ ] § 20 Final pre-submission test pass clean
- [ ] POT regenerated via `wp i18n make-pot`
- Sign:

## Release blockers (Eng + QA)
- [ ] § 21 Performance budgets met
- [ ] § 22 Scale & load testing
- [ ] § 27 Migration tested forward + rollback
- [ ] § 28 Failure-handling playbook rehearsed
- Sign:

## Quality gates (Product + QA)
- [ ] § 23 Browser/CSP matrix green
- [ ] § 24 WCAG 2.2 AA pass
- [ ] § 25 Admin UX & copy reviewed
- [ ] § 26 Compatibility matrix green
- [ ] § 29 Observability/diagnostics tested
- Sign:

## Waivers recorded for 0.3.1
- C-13 (SVN assets): WARN — icon/banner/screenshots not designed yet.
  Existing 0.3.0 SVN assets reused. Tracked as backlog item.
- S-5 (Plugin Check): SKIPPED locally — must run in CI before SVN tag.
- S-3 integration: deferred to CI on PR.

Ref: jaan-to/docs/wordpress-submission-checklist.md § 30
