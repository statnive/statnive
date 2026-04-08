# Runbook: "Statnive was working, then it stopped"

> Symptom: dashboards used to show data, but the most recent visit timestamp is hours or days old.

## Triage in 60 seconds

1. Generate a fresh visit in an incognito window.
2. Wait 60 seconds.
3. Reload the dashboard.

If the new visit appears, the gap was a transient issue. If not, work the checks below.

## Check 1 — Last-tracked timestamp

The dashboard's "Recent" feed shows the most recent pageview. Note the timestamp. Compare to:

- Last successful WP-Cron run (Tools → Site Health → Info → Cron Events).
- Last database write (run `SELECT MAX(created_at) FROM {prefix}statnive_views`).

Gap pattern → root cause:
- **Stops at the same time every day** → cron is being killed by the host (memory limit, max execution time).
- **Stops on Sundays** → host weekend maintenance window.
- **Stops at random** → DB write failures (see Check 3).
- **Stops the day a plugin was added** → conflict (see Check 5).

## Check 2 — DISABLE_WP_CRON

If `wp-config.php` defines `DISABLE_WP_CRON`, scheduled jobs (retention, aggregation, salt rotation, GeoIP update) only run when something else triggers them. Tracking still works because it does not depend on cron — but data retention won't run, so old data won't be purged.

Add a system cron:

```cron
*/5 * * * * cd /var/www/html && wp cron event run --due-now > /dev/null 2>&1
```

Or trigger Statnive's cron directly (Phase B follow-up):

```bash
wp statnive cron run
```

## Check 3 — DB write failures

Look in `wp-content/debug.log` for entries like `[Statnive][...] DB write failed`. If you see them:

- Check `wp_statnive_views` table schema is intact: `SHOW CREATE TABLE wp_statnive_views`.
- Check the database is not full: `SHOW VARIABLES LIKE 'innodb_buffer_pool_size'` and disk free space.
- Check `max_allowed_packet` is large enough (at least 16 MB).

## Check 4 — Tracker bundle was replaced by a stale build

If you recently updated Statnive and the tracker file hash changed, browser caches may serve the old hash. Symptoms:

- New visits don't appear.
- Hard-reloading (Cmd+Shift+R) brings them back for that browser.

Fix: bump your CDN/proxy cache for `/wp-content/plugins/statnive/public/tracker/*.js`.

## Check 5 — A new plugin or theme update broke compatibility

Use Health Check → Troubleshooting Mode to disable everything except Statnive. If data starts flowing again, re-enable plugins one at a time to find the conflict.

Likely culprits:
- Aggressive page-cache plugins that started caching the REST endpoint.
- Security plugins whose WAF started blocking POST `/wp-json/statnive/v1/hit`.
- Theme changes that removed `wp_footer()`.

## Check 6 — Salt rotation jammed

Statnive rotates the visitor salt daily via `statnive_daily_salt_rotation`. If WP-Cron has been broken for >2 days, the same salt is being reused — not a tracking failure but a privacy posture concern.

```bash
wp option get statnive_visitor_salt_today
wp option get statnive_visitor_salt_yesterday
```

If both are identical or stale, run `wp cron event run statnive_daily_salt_rotation`.

## Escalate

Same as `no-data.md` — file at https://github.com/statnive/statnive/issues with versions, plugin list, and `debug.log`.
