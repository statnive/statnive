# Statnive — Multisite Data Model

> Source: WordPress.org submission checklist §27.6 (`[RELEASE BLOCKER]`).
> Last reviewed: 2026-04-06 (Pass 2 audit).

This document declares how Statnive stores data on multisite installs and how each WordPress lifecycle hook (activation, deactivation, uninstall) behaves across the network.

## TL;DR

- **Data tables are per-site.** Statnive creates `{wp_2_}statnive_*` tables under each blog's `$wpdb->prefix`. There is **no network-wide aggregation table**.
- **Settings are per-site.** Statnive options (`statnive_tracking_enabled`, `statnive_consent_mode`, `statnive_geoip_enabled`, `statnive_maxmind_license_key`, etc.) live in the per-blog `wp_options` table — each site configures itself independently.
- **`statnive_db_version` is per-site.** A migration on Site A does not silently affect Site B.
- **Network-wide options are NOT used.** The only `wp_sitemeta` rows Statnive could ever own come from a future Pro add-on; the .org build does not write to `wp_sitemeta` at runtime.
- **Uninstall is multisite-aware** but does **not** loop synchronously over every blog — see "Uninstall" below.

## Activation

```text
register_activation_hook( STATNIVE_FILE, [ Plugin::class, 'activate' ] )
   └── current_user_can( 'activate_plugins' )
   └── DatabaseFactory::create_tables()
        └── dbDelta() against $wpdb->prefix . 'statnive_*'
   └── update_option( 'statnive_db_version', STATNIVE_VERSION )
   └── CronRegistrar::register_all()
   └── flush_rewrite_rules()
```

When a network admin clicks **Network Activate**, WordPress invokes the activation hook **once** with the network admin's prefix in scope. To get per-site tables on every blog, the operator must either:

1. Activate Statnive on each site individually (per-site activation), or
2. Use WP-CLI: `wp plugin activate statnive --all` (loops every blog).

WordPress does not provide a built-in "create per-site tables for every existing blog" hook — Statnive intentionally lazy-creates the tables on a `wp_initialize_site` action so that **new** blogs added to the network get tables automatically.

> **Operator note:** if you network-activate Statnive on an existing multisite with many blogs, run `wp plugin activate statnive --all` afterwards or visit each site once.

## Deactivation

```text
register_deactivation_hook( STATNIVE_FILE, [ Plugin::class, 'deactivate' ] )
   └── CronRegistrar::deregister_all()
   └── flush_rewrite_rules()
```

Per WordPress.org guideline 4 we never delete data on deactivation — only scheduled crons are unhooked.

## Uninstall

`uninstall.php` runs **once** when WordPress fully removes the plugin.

```text
WP_UNINSTALL_PLUGIN guard
   └── DROP TABLE IF EXISTS for every {prefix}statnive_*
        - SHOW TABLES LIKE 'wp_%statnive_%' (covers wp_, wp_2_, wp_3_, … prefixes in one call)
   └── DELETE FROM wp_options WHERE option_name LIKE 'statnive_%'
   └── DELETE FROM wp_options WHERE option_name LIKE '_transient_statnive_%'
   └── DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_statnive_%'
   └── if ( is_multisite() ) {
            DELETE FROM wp_sitemeta WHERE meta_key LIKE 'statnive_%'
        }
   └── wp_clear_scheduled_hook( 'statnive_*' ) for every cron hook
   └── Remove wp-content/uploads/statnive/ (GeoIP database directory)
```

### Why we do NOT loop over every blog synchronously

The Plugin Developer Handbook explicitly warns:

> "Looping through `get_sites()` and calling `delete_option()` for every blog can become very resource intensive on large networks."

Instead Statnive uses a **single `LIKE`-scoped DELETE** against `wp_options` and `wp_sitemeta`. WordPress core stores per-site options in `wp_X_options` tables that share the `wp_options` schema; the `LIKE 'statnive_%'` clause only matches rows whose `option_name` begins with `statnive_`, so the query is bounded.

If a network has hundreds of blogs and each blog has its own `wp_X_options`, the existing `SHOW TABLES LIKE` approach (used for `DROP TABLE`) plus a small loop is acceptable because the loop runs at MySQL server speed, not WordPress hook overhead.

## Capability surface

| Action | Capability required | Notes |
|---|---|---|
| Activate plugin | `activate_plugins` | Per-site for site admin, network for super admin. |
| View dashboard | `manage_options` | Per-site. |
| Edit settings (`/wp-json/statnive/v1/settings`) | `manage_options` | Per-site. |
| Run manual cron (`/wp-json/statnive/v1/cron/run`) | `manage_options` | Per-site (Phase B follow-up). |
| Network admin diagnostics | `manage_network_options` | Reserved for future Pro add-on. |

## Storage paths

- **GeoIP database:** `wp-content/uploads/statnive/GeoLite2-City.mmdb`. The uploads directory is per-site on multisite (`wp-content/uploads/sites/2/statnive/...`), so each blog gets its own copy if it enables GeoIP. This is intentional — license keys are per-site too.
- **Encryption key for archives:** `statnive_archive_key` option, per-site, base64-encoded sodium key.
- **Daily rotating salts:** `statnive_visitor_salt_*` options, per-site, rotated by `SaltRotationJob`.

## Tested matrix (verified by hand on 2026-04-06)

| Multisite mode | Activation | Uninstall | Status |
|---|---|---|---|
| Single site (no MS) | per-site | DROP + DELETE | ✓ |
| Multisite, sub-directory | per-site (recommended) | DROP + DELETE + sitemeta | ✓ |
| Multisite, sub-domain | per-site | DROP + DELETE + sitemeta | ✓ |
| Multisite network-activate | needs `wp plugin activate statnive --all` afterwards | DROP + DELETE | ⚠ documented, not auto |

## Out of scope

- **Pro add-on with network-level aggregation tables** — when the Pro add-on ships, it may introduce a `wp_sitemeta`-stored aggregator. That add-on owns its own uninstall handler.
- **Background backfill across all blogs** — not implemented; not required for the .org build.
