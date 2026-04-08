# Runbook: "Statnive GeoIP is not working"

> Symptom: Geography page shows "Unknown" or no countries; visitor records have no `country_id`.

## Triage in 60 seconds

1. Settings → GeoIP. Is the **"Enable GeoIP database downloads"** toggle ON?
2. Is the **MaxMind license key** field populated?
3. Does the file `wp-content/uploads/statnive/GeoLite2-City.mmdb` exist?

If any of those is "no", you've found the issue.

## Check 1 — GeoIP is opt-in

Per WordPress.org Guideline 6 + 7, Statnive does **not** download a GeoIP database on activation. You have to:

1. Get a free MaxMind account at https://www.maxmind.com/en/geolite2/signup
2. Generate a license key at https://www.maxmind.com/en/accounts/current/license-key
3. Accept the GeoLite2 EULA: https://www.maxmind.com/en/geolite2/eula
4. Paste the key into Statnive Settings → GeoIP → MaxMind license key
5. Toggle "Enable GeoIP database downloads" ON
6. Save settings

The first download happens within an hour via WP-Cron. Or trigger it manually:

```bash
wp cron event run statnive_weekly_geoip_update
```

## Check 2 — License key is wrong

If the download fails with HTTP 401, the key is invalid. Symptoms in `debug.log`:

```
[Statnive][GeoIP] Download failed: HTTP 401
```

Re-generate a new key on the MaxMind dashboard, paste it, save.

## Check 3 — Disk write permissions

Statnive writes to `wp-content/uploads/statnive/`. If this directory doesn't exist or isn't writable, the download fails silently.

```bash
ls -la wp-content/uploads/statnive/
```

You should see `GeoLite2-City.mmdb` with mode `0640` and owner matching the PHP-FPM user. If the directory is missing or owned by `root`, fix:

```bash
mkdir -p wp-content/uploads/statnive
chown www-data:www-data wp-content/uploads/statnive
chmod 755 wp-content/uploads/statnive
```

(Adjust user/group to match your stack.)

## Check 4 — WP-Cron can't fetch the file

If `DISABLE_WP_CRON` is true and you don't have a system cron, the weekly GeoIP update never runs. See `cleanup-not-running.md` Check 1.

The first time you enable GeoIP, run the update by hand:

```bash
wp cron event run statnive_weekly_geoip_update
```

…or via the manual button under Settings → Diagnostics (Phase B follow-up).

## Check 5 — Outbound HTTP blocked

Some hosts block outbound HTTPS to non-allowlisted domains. MaxMind's download endpoint is `https://download.maxmind.com`. Allowlist that hostname in your host's egress firewall.

Test from the server:

```bash
curl -I "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=YOURKEY&suffix=tar.gz"
```

You should see `HTTP/2 200`. If you see `Could not resolve host` or `Connection refused`, your host is blocking outbound traffic.

## Check 6 — File downloaded but extraction failed

Look for:

```
[Statnive][GeoIP] Archive extraction failed: ...
```

This usually means PHP's `PharData` (or libsodium) is missing. Check:

```bash
php -m | grep -E 'phar|zlib|sodium'
```

All three should be present. On Debian/Ubuntu: `apt install php8.1-zlib php8.1-sodium`.

## Check 7 — Database write succeeded but geography query joins on wrong key

This is a code bug — file an issue with:

- `SELECT COUNT(*) FROM wp_statnive_visitors WHERE country_id IS NULL`
- `SELECT COUNT(*) FROM wp_statnive_countries`

If `wp_statnive_countries` has rows but no visitors are tagged, the lookup is broken.

## What if I don't want GeoIP at all?

That's fine — leave the toggle off. Statnive works without country data; the Geography page will show "GeoIP is disabled" instead of country breakdowns.
