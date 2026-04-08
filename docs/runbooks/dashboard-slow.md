# Runbook: "The Statnive dashboard is slow"

> Symptom: opening the Overview, Pages, or Geography page takes more than 2 seconds.

## Triage in 60 seconds

Open Tools → Site Health → Info → check disk space + DB size. If `wp_statnive_views` is over 1 GB, jump to "Check 3 — Retention".

Otherwise, run through the checks in order.

## Check 1 — Object cache enabled?

If your site does not have a Redis or Memcached object cache, every dashboard reload re-runs every report query against the DB. Statnive's transient cache helps, but an object cache turns transient reads into in-memory hits.

Recommended for any site with > 1 K visits/day. Install one of:

- Redis Object Cache plugin
- W3 Total Cache (object cache)
- LiteSpeed Cache (object cache)

## Check 2 — Query Monitor — which query is slow?

Install Query Monitor temporarily. Open the slow Statnive page. Click the QM toolbar → Database Queries → filter by `statnive_`. The slowest query is the bottleneck.

If it is `wp_statnive_summary` → the daily aggregation rollup hasn't run; investigate `statnive_daily_aggregation` cron.

If it is `wp_statnive_views` with no `LIMIT` → expected: this should be served by the summary table, not raw views. File a bug.

If it is `wp_statnive_sessions` JOIN `wp_statnive_visitors` → that's the geography query; should hit the `idx_started_country` composite index. Run:

```sql
EXPLAIN SELECT ... FROM wp_statnive_sessions s JOIN wp_statnive_visitors v ...
```

…and verify `key` is non-NULL.

## Check 3 — Retention

Statnive keeps every raw pageview by default for 90 days. Long retention windows make the dashboard slower as the table grows.

Settings → Data Retention → drop to 30 or 90 days for production. Then run:

```bash
wp cron event run statnive_daily_data_purge
```

(or wait for the daily cron). Tables shrink and queries speed up.

## Check 4 — Concurrent dashboard loads

If multiple admins are loading the dashboard at the same time and the DB is small, you may be hitting `innodb_buffer_pool_size`. Increase it on the server (typical: 256 MB → 1 GB) and restart MySQL.

## Check 5 — PHP memory limit

`PHP-FPM` workers with low `memory_limit` (< 128 MB) may OOM on large dashboard responses. Check `error_log` for `Allowed memory size of N bytes exhausted`. Bump to 256 MB.

## Check 6 — Disable real-time polling

The Real-time page polls every 30 s. If you have many admins viewing it, that's a sustained load on the realtime query. Close the tab when not actively watching, or increase the polling interval (Settings → Real-time → poll cadence).

## Performance budgets

Statnive's performance budget for each dashboard screen is documented in `docs/performance-budgets.md`. If your install routinely exceeds the "soft" thresholds and you've cleared the checks above, file a perf bug with Query Monitor output.
