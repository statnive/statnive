# Statnive — Compatibility Matrix

> Source: WordPress.org submission checklist §26 (`[QUALITY GATE]`).
> Last verified: 2026-04-06 against `chore/wp-org-submission-audit`.

This is the matrix of WordPress configurations Statnive is tested against. Statnive is **expected to work** in any combination not explicitly marked broken.

## Theme matrix

| Theme | Tracker | Admin UI | Notes |
|---|---|---|---|
| Twenty Twenty-Five (default) | ✓ | ✓ | Verified on WP 6.9.4 |
| Twenty Twenty-Four | ✓ | ✓ | |
| Twenty Twenty-Three | ✓ | ✓ | |
| Astra | ✓ | ✓ | |
| GeneratePress | ✓ | ✓ | |
| OceanWP | ✓ | ✓ | |
| Custom block themes (FSE) | ✓ | ✓ | Tracker injection via `wp_footer` works in FSE templates. |
| Page builders (Elementor / Beaver) | ✓ | ✓ | Tracker still injects via `wp_footer`. |

## Page-cache plugin matrix

| Plugin | Mode | Status | Required exclusion |
|---|---|---|---|
| WP Super Cache | static page cache on | ✓ | None — REST endpoint excluded by default |
| W3 Total Cache | full-page cache on | ✓ | None — REST endpoint excluded by default |
| WP Rocket | page cache on | ✓ | None — REST endpoint excluded by default |
| LiteSpeed Cache | page cache on | ✓ | None — REST endpoint excluded by default |
| Cloudflare Page Rules | "Cache Everything" | ⚠ | **Add a bypass for `/wp-json/statnive/v1/hit`** |
| Custom reverse proxy (Varnish/nginx fastcgi_cache) | full HTML cache | ⚠ | **Add a bypass for `/wp-json/statnive/v1/hit` and `wp-admin/admin-ajax.php?action=statnive_hit`** |

The tracker fires after page render, so a page-cached HTML page still works — the tracker JS still POSTs to the live REST endpoint. The risk is only when the REST endpoint *itself* is page-cached, which would silently drop tracking events.

## Object-cache matrix

| Backend | Status | Notes |
|---|---|---|
| None (default) | ✓ | Statnive uses transients (DB-backed) for rate limiting. |
| Redis (object-cache.php drop-in) | ✓ | Transients automatically use Redis. |
| Memcached (object-cache.php drop-in) | ✓ | Same. |

## Security plugin matrix

| Plugin | Tracker | Admin UI | Notes |
|---|---|---|---|
| Wordfence | ✓ | ✓ | Default rules don't block REST POST to same origin. |
| iThemes Security (Solid Security) | ✓ | ✓ | |
| Sucuri Security | ✓ | ✓ | |

If a security plugin's WAF blocks the REST endpoint, Statnive falls back to the `wp_ajax_nopriv_statnive_hit` handler — both must be allowlisted in the host's WAF if it has aggressive rules.

## E-commerce / WooCommerce matrix

| Configuration | Tracker | Dashboard | Notes |
|---|---|---|---|
| WooCommerce inactive | ✓ | ✓ | Default test path. |
| WooCommerce active, classic checkout | ✓ | ✓ | No Woo-specific tracking yet (deferred to v0.4.0). |
| WooCommerce active, block checkout | ✓ | ✓ | |
| WooCommerce active, HPOS enabled | ✓ | ✓ | Statnive does not query order tables in v0.3.x. |
| WooCommerce active, HPOS disabled | ✓ | ✓ | |

> **Future v0.4.0 note:** when WooCommerce revenue tracking lands, all order access will use `wc_get_orders()` / `WC_Order_Query` (per WooCommerce dev guidelines), and `Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__)` will be called from the main plugin file.

## Multisite matrix

See `docs/multisite.md` for the full data-model declaration. Tested combinations:

| Mode | Activation | Tracking | Uninstall |
|---|---|---|---|
| Sub-directory multisite | per-site | per-site tables | per-site DROP + sitemeta DELETE |
| Sub-domain multisite | per-site | per-site tables | per-site DROP + sitemeta DELETE |
| Network-activate | needs `wp plugin activate statnive --all` | OK once activated per-site | per-site DROP + sitemeta DELETE |

## Health Check / Troubleshooting Mode

Statnive does **not** depend on any other plugin being active. In Troubleshooting Mode (only Statnive enabled), the dashboard, tracker injection, and settings page all work cleanly. This is a hard requirement — if you find a missing dependency, file a bug.

## Browser matrix (tracker JS)

| Browser | Status | Minimum version |
|---|---|---|
| Chrome (desktop + Android) | ✓ | Latest 2 majors |
| Firefox | ✓ | Latest 2 majors |
| Safari (macOS + iOS) | ✓ | Latest 2 majors |
| Edge | ✓ | Latest 2 majors |
| Older browsers | ⚠ silent no-op | The tracker uses `navigator.sendBeacon` + `fetch({keepalive:true})` + `Intl.DateTimeFormat`. Browsers without these APIs simply skip tracking. |

## Out of scope (deferred)

- Automated cross-browser test suite (Playwright runs against Chromium only).
- Multisite "create per-site tables for every existing blog on network-activate" automation.
- HPOS-specific tests (no Woo integration in v0.3.x).
