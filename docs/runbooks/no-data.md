# Runbook: "I see no data in Statnive"

> Symptom: dashboards show zeros / "No traffic sources recorded yet" / "No page data recorded yet" even though the site has visitors.

## Triage in 60 seconds

1. **Visit the public site once in a private/incognito window** to generate a real visit (your own admin sessions are excluded by default).
2. Wait 30 seconds.
3. Reload the Statnive dashboard.

If data appears, the tracker is healthy. If not, work through the checks below.

## Check 1 — Tracking is enabled

Settings → Tracking → "Enable tracking" must be on. Default: on.

## Check 2 — Tracker JS is being injected

In an incognito window, view-source on any front-end page. You should see something like:

```html
<script id="statnive-tracker-core-inline-js"> ... </script>
```

near the closing `</body>` tag. If it is missing:

- Check that your theme calls `wp_footer()` somewhere (block themes always do; classic themes occasionally forget).
- Check that no other plugin has filtered out `script_loader_tag` for `statnive`.
- Check that you are not on an admin page (the tracker only fires on the front end by design).

## Check 3 — DNT / GPC opt-out

Statnive honours `Sec-GPC: 1` (Global Privacy Control) as the **primary** opt-out signal, and `DNT: 1` as a legacy fallback. Some browsers (Brave, DuckDuckGo, Firefox with strict mode) send these by default. Test in a fresh Chrome profile to rule this out.

To temporarily ignore both: Settings → Privacy → uncheck "Respect Global Privacy Control" and "Respect Do Not Track". (Not recommended for production.)

## Check 4 — Ad blocker / privacy extension

Most ad blockers don't filter same-origin REST endpoints, but uBlock Origin's "annoyances" lists sometimes do. Open DevTools → Network → reload the front-end page and look for a POST to `/wp-json/statnive/v1/hit`. If the request is missing or red:

- Disable the ad blocker.
- Allowlist `/wp-json/statnive/v1/hit` in the blocker.
- Check the blocker's logs.

## Check 5 — CSP blocking the request

Open DevTools → Console. If you see:

```
Refused to connect to '/wp-json/statnive/v1/hit' because it violates the following Content Security Policy directive: connect-src ...
```

…your site CSP needs `connect-src 'self'` (or whatever origin Statnive's REST is on). This is a site-config fix, not a Statnive bug.

## Check 6 — Page cache serving a stale tracker

If your page-cache plugin caches the tracker JS file with a stale hash, the tracker may load but POST to a no-longer-existent endpoint. Purge the cache and re-test.

## Check 7 — Self-test (Phase B follow-up)

When the diagnostics endpoint ships, run Settings → Diagnostics → "Test tracking" — this performs a full pipeline check (synthetic hit → DB read-back → admin report query) and reports each step.

## Escalate

If you've cleared all 7 checks and still see no data, file an issue at https://github.com/statnive/statnive/issues with:

- Your WordPress + PHP version
- Active plugins list
- Browser + version
- Network tab screenshot showing the POST request (or its absence)
- Server `debug.log` if `WP_DEBUG_LOG` is enabled
