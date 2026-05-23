# Release 0.4.9 sign-off

Per § 30 of `jaan-to/docs/wordpress-submission-checklist.md`, this evidence pack
must be signed by Eng, QA, and Product before SVN submission.

> v0.4.9 supersedes v0.4.8, which was withdrawn for a packaging bug:
> the dist ZIP shipped three dotfiles that WP.org PCP rejects (rule:
> `hidden_files`) — `.env.local` (with dev credentials + filesystem path),
> `.githooks/pre-commit`, and `public/react/.vite/manifest.json`. v0.4.9
> is identical to v0.4.8 in code behavior except: the Vite manifest is
> now at `public/react/manifest.json` (Vite config + `ReactHandler` +
> integration test all updated to match), and `.distignore` excludes
> `.env*` and `.githooks`.

## Submission blockers (Eng lead)

- [ ] All `/statnive-release-zip` gates passed (S-1…S-5, C-1…C-17). See `gate-run.log`.
      **S-5 PCP** is deferred to CI on the release commit (local wp-env env blockers
      documented in v0.4.8's gate-run.log are still unresolved). The CI run on the
      v0.4.9 release commit will run `wordpress/plugin-check-action strict:true`
      against the dist ZIP — that's the binding evidence; do not SVN-push until
      it's green. Tighten `releases/0.4.8/SIGN-OFF.md` deferral once verified.
- [ ] § 17 WP_DEBUG audit clean (manual: install on clean WP, walk activate → use →
      deactivate → reactivate → uninstall, debug.log empty)
- [ ] § 20 Final pre-submission test pass (clean WP install, every UI surface, every
      settings field, every report; deactivate clears cron; delete clears options +
      drops tables + removes uploads/statnive/; **role-exclusion gate manual test**:
      log in as a subscriber with `excluded_roles=['subscriber']`, view-source on the
      front-end → confirm the tracker `<script id="statnive-tracker-js">` is absent;
      reverse for an editor → confirm tracker IS present)
- [ ] § 31 SVN structure ready (trunk + tags + assets directories)
- Sign:

## Release blockers (Eng + QA)

- [ ] § 21 Performance budgets met
- [ ] § 22 Scale & load testing
- [ ] § 27 Migration tested forward + rollback (v0.4.7 → v0.4.9 directly skipping the
      withdrawn v0.4.8; verify schema-version path handles the gap)
- [ ] § 28 Failure-handling playbook rehearsed
- Sign:

## Quality gates (Product + QA)

- [ ] § 23 Browser/CSP matrix green
- [ ] § 24 WCAG 2.2 AA pass
- [ ] § 25 Admin UX & copy reviewed
- [ ] § 26 Compatibility matrix green
- [ ] § 29 Observability/diagnostics tested
- Sign:

## New rejection killers (Appendix A items #17–#24)

- [ ] #17 Admin notices restricted to plugin pages, dismissible
- [ ] #18 No required "Powered by Statnive" front-end credit (off by default)
- [ ] #19 `Tested up to:` matches the WordPress Releases page at tag time (currently 6.9)
- [ ] #20 GPC (Sec-GPC) is the primary privacy signal; DNT is legacy fallback
- [ ] #21 Plugin name starts with unique brand term ("Statnive")
- [ ] #22 No compressed archives inside plugin directory (verified by C-14;
      source-tree WARN noted in gate-run.log relates to dev `statnive-dist/` and
      `playwright-report/` dirs, both excluded by `.distignore`)
- [ ] #23 Unminified source available for all minified/compiled files
- [ ] #24 Slug does not contain `wordpress` or `plugin` ("statnive" — clean)

## Outstanding HIGH warnings

| Gate | Status | Notes |
|------|--------|-------|
| S-5 PCP | DEFERRED-TO-CI | Re-run locally once wp-env env blockers fixed; CI on the release commit is the binding evidence in the meantime |
| C-13 SVN assets | PASS | `.wordpress-org/icon-128x128.png`, `banner-772x250.jpg`, `screenshot-1.png` all present |
| C-14 no-leaks | WAIVED (false positive) | grep flags 7 `composer.json` files (1 plugin root + 6 vendor metadata). Per LEARN.md: composer.json MUST ship alongside vendor/. Pattern needs follow-up tightening — and **adding `\.env`, `\.githooks`, `\.vite` to the C-14 leak grep is a follow-up that would have caught the v0.4.8 packaging bug locally**. |

## Pre-SVN-push reminders (operator)

1. **Re-run PCP on the built ZIP** is now optional once CI on the release commit
   (`gh run list --limit 5`) is green — that covers the same code path. The wp-env
   env blockers (DNS, git+SSH, virtiofs path-with-spaces) are still tracked.
2. **POT** is FRESH — `wp i18n make-pot` regenerated 2026-05-04 directly on host.
3. **Walk the live admin** at the Local site, confirming the role-exclusion gate
   works (subscriber: tracker absent; editor: tracker present).
4. **Credential rotation**: `WP_ADMIN_PASSWORD` from `.env.local` was in the
   v0.4.8 GitHub Release ZIP for ~3 hours. If it's reused outside the dev box,
   rotate. (Operator confirmed in v0.4.9 ship decision: dev-only password,
   defer rotation.)

## Post-release follow-ups (track separately)

- [ ] Tighten C-14 leak grep in `.claude/skills/statnive-release-zip/checks/c14-zip-integrity.md`
      to include `\.env`, `\.githooks`, `\.vite` — would have caught v0.4.8 locally.
- [ ] Add a generic "no dotfiles" assertion to C-14 (zero hidden files in the ZIP).
- [ ] Update `.claude/skills/statnive-release/SKILL.md` Step 4 to list the
      4th version source: `STATNIVE_VERSION` constant in `statnive.php`.
- [ ] Update `.claude/skills/statnive-release-zip/LEARN.md` with the local
      wp-env recovery recipe (DNS fix, git+SSH HTTPS rewrite, dev-only --config
      to skip tests env mounts) and the host-only `wp i18n make-pot` recipe.
- [ ] Fix the workflow matrix-expansion bug that has the Integration job permanently
      SKIPPED — `${{ matrix.php }}` literal in job name suggests a YAML file is
      not in a job-matrix context.

Ref: `jaan-to/docs/wordpress-submission-checklist.md` § 30
