# Runbook: "Statnive cleanup is not running"

> Symptom: tables keep growing past the retention window. Old data is not being purged.

## Triage

```bash
wp cron event list | grep statnive_
```

Look for `statnive_daily_data_purge`. The "Next Run" column should be in the future.

## Check 1 — DISABLE_WP_CRON

Most "cleanup not running" cases come down to `wp-config.php` having:

```php
define( 'DISABLE_WP_CRON', true );
```

Statnive shows an admin notice on its settings page when this is detected. The notice points you here.

When `DISABLE_WP_CRON` is true, WordPress relies on **system cron** (or a manual trigger) to actually run scheduled events. If your host has not configured a system cron, **nothing in WordPress runs on a schedule** — including Statnive's data purge.

### Fix A — set up a system cron

```cron
*/5 * * * * cd /var/www/html && wp cron event run --due-now > /dev/null 2>&1
```

Or hit `wp-cron.php` over HTTP every 5 minutes:

```cron
*/5 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron > /dev/null
```

### Fix B — manual one-shot run

For a one-time cleanup, in the admin click Settings → Diagnostics → "Run cleanup now" (Phase B follow-up). Or via WP-CLI:

```bash
wp statnive cron run --job=data-purge
```

(WP-CLI command is part of the Phase B follow-up — until then use `wp cron event run statnive_daily_data_purge`.)

## Check 2 — Cron is scheduled but never fires

```bash
wp cron event list | grep statnive_daily_data_purge
```

If the "Next Run" column is in the **past** by a long margin, WP-Cron is broken. See Check 1.

## Check 3 — Cron fires but errors out

```bash
wp cron event run statnive_daily_data_purge --debug
```

Look for fatals, memory exhaustion, or "Class … not found" — that's a code bug; file an issue.

## Check 4 — Retention setting is too generous

Settings → Data Retention. If it's set to "Keep forever", purge will never delete anything. Drop it to a finite value (90 days is the recommended default).

## Check 5 — Disk full

Statnive's `DataPurgeJob` uses chunked DELETEs. If the database server has no free disk space, those DELETEs may fail silently with an InnoDB error. Check disk free space on the DB host.

## Check 6 — Long-running purge keeps re-scheduling

Statnive purges in batches of 1 000 rows per run. If you have a 10 M-row table and purge once a day, it will take 10 000 days to drain. The job re-schedules itself with a 5-minute gap when more than 1 000 rows remain.

For a one-time large cleanup:

```bash
# Run repeatedly until no rows remain
while true; do
  wp cron event run statnive_daily_data_purge
  sleep 5
done
```

…or upgrade to a Pro add-on with bulk-purge support (when shipped).

## Escalate

If purge is failing and you've cleared the checks above, file at https://github.com/statnive/statnive/issues with:

- `wp cron event list` output
- `debug.log` excerpt
- Approximate row count (`SELECT COUNT(*) FROM wp_statnive_views`)
