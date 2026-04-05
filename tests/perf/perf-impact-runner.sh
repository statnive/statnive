#!/usr/bin/env zsh
#
# Performance impact orchestrator.
#
# Toggles analytics plugins via the WordPress REST API (wp/v2/plugins),
# runs k6 browser vitals test for each configuration, and generates
# a final comparison report.
#
# Usage:
#   ./perf-impact-runner.sh [light|medium|heavy]
#
# Results saved to: tests/perf/results/perf-impact/
#

set -eo pipefail

# Ensure PATH includes homebrew and common binary locations.
PATH="/opt/homebrew/bin:/opt/homebrew/opt/node@22/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${HOME}/.pyenv/shims:${HOME}/bin:${PATH}"
export PATH

# Verify critical binaries are available.
K6_BIN="$(command -v k6 2>/dev/null || echo "")"
CURL_BIN="$(command -v curl 2>/dev/null || echo "/usr/bin/curl")"
PYTHON_BIN="$(command -v python3 2>/dev/null || echo "/usr/bin/python3")"
NODE_BIN="$(command -v node 2>/dev/null || echo "/usr/local/bin/node")"

if [ -z "$K6_BIN" ]; then
    echo "ERROR: k6 not found in PATH. Install: brew install k6"
    exit 1
fi

SCRIPT_DIR="${0:A:h}"
PLUGIN_DIR="${SCRIPT_DIR:h:h}"
ENV_FILE="$PLUGIN_DIR/../.env"

# Load .env.
if [ -f "$ENV_FILE" ]; then
    extract_env() {
        local key="$1"
        grep "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | sed "s/^${key}=//"
    }
    _WP_SITE_URL="$(extract_env WP_SITE_URL)"
    _WP_ADMIN_USER="$(extract_env WP_ADMIN_USER)"
    _WP_ADMIN_PASS="$(extract_env WP_ADMIN_PASS)"
    _HMAC_SECRET="$(extract_env HMAC_SECRET)"
    [ -n "$_WP_SITE_URL" ] && WP_SITE_URL="$_WP_SITE_URL"
    [ -n "$_WP_ADMIN_USER" ] && WP_ADMIN_USER="$_WP_ADMIN_USER"
    [ -n "$_WP_ADMIN_PASS" ] && WP_ADMIN_PASS="$_WP_ADMIN_PASS"
    [ -n "$_HMAC_SECRET" ] && HMAC_SECRET="$_HMAC_SECRET"
fi

BASE_URL="${WP_SITE_URL:-http://localhost:10013}"
ADMIN_USER="${WP_ADMIN_USER:-root}"
ADMIN_PASS="${WP_ADMIN_PASS:-q1w2e3}"
HMAC_SECRET="${HMAC_SECRET:-}"
LOAD_TIER="${1:-medium}"

# Results directory.
RESULTS_DIR="$SCRIPT_DIR/results/perf-impact"
mkdir -p "$RESULTS_DIR"

RUN_TS=$(date +%s)
COOKIE_FILE="/tmp/wp_perf_impact_cookies_$$.txt"

# ---------------------------------------------------------------------------
# WordPress REST API helpers
# ---------------------------------------------------------------------------

WP_NONCE=""

wp_login() {
    $CURL_BIN -s -c "$COOKIE_FILE" -X POST "${BASE_URL}/wp-login.php" \
        -d "log=${ADMIN_USER}&pwd=${ADMIN_PASS}&wp-submit=Log+In&redirect_to=${BASE_URL}/wp-admin/&testcookie=1" \
        -H "Cookie: wordpress_test_cookie=WP%20Cookie%20check" -L -o /dev/null

    WP_NONCE=$($CURL_BIN -s -b "$COOKIE_FILE" "${BASE_URL}/wp-admin/admin-ajax.php?action=rest-nonce")

    if [ -z "$WP_NONCE" ] || [ "$WP_NONCE" = "0" ]; then
        echo "ERROR: Failed to get WordPress nonce"
        exit 1
    fi
}

refresh_auth() {
    WP_NONCE=$($CURL_BIN -s -b "$COOKIE_FILE" "${BASE_URL}/wp-admin/admin-ajax.php?action=rest-nonce")
    if [ -z "$WP_NONCE" ] || [ "$WP_NONCE" = "0" ]; then
        echo "    Auth expired, re-logging in..."
        wp_login
    fi
}

set_plugin_status() {
    local p_slug="$1"
    local p_state="$2"
    $CURL_BIN -s -X POST \
        -H "X-WP-Nonce: $WP_NONCE" -b "$COOKIE_FILE" \
        -H "Content-Type: application/json" \
        -d "{\"status\":\"${p_state}\"}" \
        "${BASE_URL}/wp-json/wp/v2/plugins/${p_slug}" > /dev/null 2>&1 || true
}

# ---------------------------------------------------------------------------
# Plugin discovery via REST API
# ---------------------------------------------------------------------------

# Arrays: PLUGIN_NAMES[i] = short name, PLUGIN_SLUGS[i] = REST slug, PLUGIN_ORIG[i] = original status
typeset -a PLUGIN_NAMES PLUGIN_SLUGS PLUGIN_ORIG

ANALYTICS_KEYWORDS=(
    statnive
    wp-statistics
    koko-analytics
    burst-statistics
    independent-analytics
    wp-slimstat
    jetpack
    google-analytics-for-wordpress
)

discover_analytics_plugins() {
    echo "  Discovering analytics plugins via REST API..."

    local all_plugins
    all_plugins=$($CURL_BIN -s -H "X-WP-Nonce: $WP_NONCE" -b "$COOKIE_FILE" \
        "${BASE_URL}/wp-json/wp/v2/plugins")

    for keyword in "${ANALYTICS_KEYWORDS[@]}"; do
        p_slug=$(echo "$all_plugins" | $PYTHON_BIN -c "
import json, sys
plugins = json.load(sys.stdin)
for p in plugins:
    if p['plugin'].startswith('${keyword}/'):
        print(p['plugin'])
        break
" 2>/dev/null)

        if [ -n "$p_slug" ]; then
            p_status=$(echo "$all_plugins" | $PYTHON_BIN -c "
import json, sys
plugins = json.load(sys.stdin)
for p in plugins:
    if p['plugin'] == '${p_slug}':
        print(p['status'])
        break
" 2>/dev/null)

            PLUGIN_NAMES+=("$keyword")
            PLUGIN_SLUGS+=("$p_slug")
            PLUGIN_ORIG+=("$p_status")
            echo "    ✓ $keyword → $p_slug ($p_status)"
        else
            echo "    ✗ $keyword — not installed"
        fi
    done

    echo "  Found ${#PLUGIN_NAMES[@]} analytics plugins."
}

deactivate_all_analytics() {
    for ps in "${PLUGIN_SLUGS[@]}"; do
        set_plugin_status "$ps" "inactive"
    done
}

activate_one_plugin() {
    local target_name="$1"
    for i in {1..${#PLUGIN_NAMES[@]}}; do
        if [ "${PLUGIN_NAMES[$i]}" = "$target_name" ]; then
            set_plugin_status "${PLUGIN_SLUGS[$i]}" "active"
            return
        fi
    done
}

activate_all_analytics() {
    for ps in "${PLUGIN_SLUGS[@]}"; do
        set_plugin_status "$ps" "active"
    done
}

restore_plugin_state() {
    echo ""
    echo "  Restoring original plugin state..."
    refresh_auth
    for i in {1..${#PLUGIN_NAMES[@]}}; do
        set_plugin_status "${PLUGIN_SLUGS[$i]}" "${PLUGIN_ORIG[$i]}"
    done
    echo "  Plugin state restored."
    rm -f "$COOKIE_FILE"
}

warmup() {
    local pages=("/" "/sample-page/" "/hello-world/" "/shop/" "/wp-admin/")
    for path in "${pages[@]}"; do
        $CURL_BIN -s -o /dev/null "${BASE_URL}${path}" 2>/dev/null || true
    done
    $CURL_BIN -s "${BASE_URL}/wp-cron.php?doing_wp_cron=1" > /dev/null 2>&1 || true
    /bin/sleep 3
}

run_k6_vitals() {
    local config_label="$1"
    echo "    Running k6 browser vitals test..."

    cd "$SCRIPT_DIR"
    K6_BROWSER_ENABLED=true $K6_BIN run \
        -e "BASE_URL=${BASE_URL}" \
        -e "ADMIN_USER=${ADMIN_USER}" \
        -e "ADMIN_PASS=${ADMIN_PASS}" \
        -e "HMAC_SECRET=${HMAC_SECRET}" \
        -e "CONFIG_LABEL=${config_label}" \
        -e "LOAD_TIER=${LOAD_TIER}" \
        "$SCRIPT_DIR/perf-impact-test.js" \
        2>&1 || true
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║          PERFORMANCE IMPACT TEST ORCHESTRATOR               ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "  Site:       $BASE_URL"
echo "  Load Tier:  $LOAD_TIER"
echo "  Results:    $RESULTS_DIR"
echo ""

# Verify site is reachable.
if ! $CURL_BIN -s -o /dev/null "$BASE_URL" 2>/dev/null; then
    echo "ERROR: Cannot reach $BASE_URL"
    exit 1
fi

echo "  Authenticating with WordPress REST API..."
wp_login
echo "  Nonce: $WP_NONCE"
echo ""

discover_analytics_plugins
echo ""

if [ ${#PLUGIN_NAMES[@]} -eq 0 ]; then
    echo "ERROR: No analytics plugins found."
    exit 1
fi

local_count=${#PLUGIN_NAMES[@]}
TOTAL_CONFIGS=$(( local_count + 2 ))

case "$LOAD_TIER" in
    light)  per_config=3 ;;
    medium) per_config=5 ;;
    heavy)  per_config=8 ;;
    *)      per_config=5 ;;
esac
echo "  Est. Time:  ~$(( (per_config + 1) * TOTAL_CONFIGS )) minutes"
echo ""

trap restore_plugin_state EXIT

CONFIG_NUM=0

# --- Phase 1: BASELINE ---
CONFIG_NUM=$((CONFIG_NUM + 1))
echo "  [$CONFIG_NUM/$TOTAL_CONFIGS] BASELINE (all analytics OFF)"
echo "    Deactivating all analytics plugins..."
deactivate_all_analytics
echo "    Warming up..."
warmup
run_k6_vitals "baseline"
echo ""

# --- Phase 2: INDIVIDUAL ---
for name in "${PLUGIN_NAMES[@]}"; do
    CONFIG_NUM=$((CONFIG_NUM + 1))
    echo "  [$CONFIG_NUM/$TOTAL_CONFIGS] $name (isolated)"
    refresh_auth
    echo "    Deactivating all analytics plugins..."
    deactivate_all_analytics
    echo "    Activating $name..."
    activate_one_plugin "$name"
    echo "    Warming up..."
    warmup
    run_k6_vitals "$name"
    echo ""
done

# --- Phase 3: ALL PLUGINS ---
CONFIG_NUM=$((CONFIG_NUM + 1))
echo "  [$CONFIG_NUM/$TOTAL_CONFIGS] ALL PLUGINS (combined)"
refresh_auth
echo "    Activating all analytics plugins..."
activate_all_analytics
echo "    Warming up..."
warmup
run_k6_vitals "all-plugins"
echo ""

# --- Phase 4: REPORT ---
echo "  ════════════════════════════════════════════════════"
echo "  Generating comparison report..."
echo ""

$NODE_BIN -e "
const fs = require('fs');
const path = require('path');

const dir = '${RESULTS_DIR}';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.json') && !f.startsWith('summary')).sort();

if (files.length === 0) {
    console.error('No result files found in ' + dir);
    process.exit(1);
}

const results = {};
for (const f of files) {
    const data = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
    results[data.config] = data;
}

const baseline = results['baseline'];
if (!baseline) {
    console.error('No baseline result found!');
    process.exit(1);
}

const plugins = {};
const round = (v, d = 1) => Math.round(v * Math.pow(10, d)) / Math.pow(10, d);

for (const [name, data] of Object.entries(results)) {
    if (name === 'baseline') continue;
    const delta = {};
    const deltaPct = {};
    for (const vital of ['ttfb', 'fcp', 'lcp', 'cls', 'inp']) {
        const bVal = baseline.vitals[vital].p50;
        const pVal = data.vitals[vital].p50;
        const diff = pVal - bVal;
        delta[vital] = round(diff, vital === 'cls' ? 4 : 1);
        deltaPct[vital] = bVal > 0 ? round((diff / bVal) * 100, 1) : 0;
    }
    const bTTFB = Math.max(baseline.vitals.ttfb.p50, 1);
    const bLCP = Math.max(baseline.vitals.lcp.p50, 1);
    const bFCP = Math.max(baseline.vitals.fcp.p50, 1);
    const score = round((
        (Math.max(0, delta.ttfb) / bTTFB) * 0.25 +
        (Math.max(0, delta.lcp) / bLCP) * 0.30 +
        (Math.max(0, delta.fcp) / bFCP) * 0.20 +
        (Math.max(0, delta.cls) / 0.1) * 0.15 +
        (Math.max(0, delta.inp) / 200) * 0.10
    ) * 100, 1);
    plugins[name] = { vitals: data.vitals, delta, deltaPct, impact_score: score, samples: data.samples };
}

const ranking = Object.entries(plugins).sort((a, b) => a[1].impact_score - b[1].impact_score);

console.log('');
console.log('  ╔══════════════════════════════════════════════════════════════════════════════════╗');
console.log('  ║                      PERFORMANCE IMPACT COMPARISON                              ║');
console.log('  ╚══════════════════════════════════════════════════════════════════════════════════╝');
console.log('');
console.log('  Load Tier:   ${LOAD_TIER}');
console.log('  Baseline:    TTFB ' + baseline.vitals.ttfb.p50 + 'ms | FCP ' + baseline.vitals.fcp.p50 + 'ms | LCP ' + baseline.vitals.lcp.p50 + 'ms');
console.log('');
console.log('  ┌───────────────────────────┬────────┬────────┬────────┬────────┬────────┬───────┐');
console.log('  │ Plugin                    │ TTFB   │  FCP   │  LCP   │  CLS   │  INP   │Impact │');
console.log('  │                           │ Δ ms   │ Δ ms   │ Δ ms   │  Δ     │ Δ ms   │ Score │');
console.log('  ├───────────────────────────┼────────┼────────┼────────┼────────┼────────┼───────┤');
console.log('  │ baseline                  │   ---  │   ---  │   ---  │  ---   │   ---  │  0.0  │');

for (const [name, p] of ranking) {
    const d = p.delta;
    const fmtMs = (v) => ((v >= 0 ? '+' : '') + Math.round(v) + 'ms').padStart(6);
    const fmtCls = (v) => ((v >= 0 ? '+' : '') + v.toFixed(3)).padStart(6);
    const fmtScore = (v) => v.toFixed(1).padStart(5);
    console.log('  │ ' + name.padEnd(25) + ' │ ' + fmtMs(d.ttfb) + ' │ ' + fmtMs(d.fcp) + ' │ ' + fmtMs(d.lcp) + ' │ ' + fmtCls(d.cls) + ' │ ' + fmtMs(d.inp) + ' │ ' + fmtScore(p.impact_score) + ' │');
}

console.log('  └───────────────────────────┴────────┴────────┴────────┴────────┴────────┴───────┘');
console.log('');
if (ranking.length > 0) {
    console.log('  Lowest impact:  ' + ranking[0][0] + ' (score: ' + ranking[0][1].impact_score + ')');
    console.log('  Highest impact: ' + ranking[ranking.length - 1][0] + ' (score: ' + ranking[ranking.length - 1][1].impact_score + ')');
    console.log('');
}

const summary = {
    timestamp: new Date().toISOString(),
    load_tier: '${LOAD_TIER}',
    baseline, plugins,
    ranking: ranking.map(([n]) => n),
};
fs.writeFileSync(path.join(dir, 'summary-${RUN_TS}.json'), JSON.stringify(summary, null, 2));
console.log('  Summary saved to: results/perf-impact/summary-${RUN_TS}.json');
console.log('');
" || echo "  Report generation failed. Check results/perf-impact/ for raw data."

echo ""
echo "  Done! Plugin state will be restored automatically."
echo ""
