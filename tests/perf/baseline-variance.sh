#!/usr/bin/env zsh
#
# Baseline variance study.
#
# Runs the Phase 0 cache priming + baseline config N times back-to-back
# with all analytics plugins deactivated, then reports the run-to-run
# variance in Core Web Vitals (TTFB / FCP / LCP).
#
# Purpose: characterize how much the TEST ENVIRONMENT itself contributes
# to noise, independent of any analytics plugin. If the baseline varies
# by ±200ms between consecutive identical runs, then plugin-to-plugin
# differences smaller than ±200ms are measurement noise, not real.
#
# Phase 2 P2 of jaan-to/outputs/ROADMAP-PERFORMANCE.md.
#
# Usage:
#   ./baseline-variance.sh [light|medium|heavy] [RUNS]
#
# Examples:
#   ./baseline-variance.sh light 5    # Default — 5 runs, light tier (~12 min)
#   ./baseline-variance.sh medium 3   # Medium tier, 3 runs (~12 min)
#
# Results saved to: tests/perf/results/baseline-variance/
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
LOAD_TIER="${1:-light}"
RUNS="${2:-${RUNS:-5}}"

if ! [[ "$RUNS" =~ ^[0-9]+$ ]] || [ "$RUNS" -lt 2 ]; then
    echo "ERROR: RUNS must be an integer >= 2 (got: $RUNS)"
    exit 1
fi

RESULTS_DIR="$SCRIPT_DIR/results/baseline-variance"
/bin/mkdir -p "$RESULTS_DIR"

BATCH_TS=$(/bin/date +%s)
BATCH_DIR="$RESULTS_DIR/batch-${BATCH_TS}"
/bin/mkdir -p "$BATCH_DIR"

COOKIE_FILE="/tmp/wp_baseline_variance_cookies_$$.txt"

typeset -a BATCH_RUN_DIRS

# ---------------------------------------------------------------------------
# WordPress REST API helpers (mirrors perf-impact-runner.sh but without
# plugin toggling — this script never activates or deactivates any plugin)
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

warmup() {
    local pages=("/" "/sample-page/" "/hello-world/" "/shop/" "/product/hoodie/" "/wp-admin/")
    for pass in 1 2 3; do
        for path in "${pages[@]}"; do
            $CURL_BIN -s -o /dev/null "${BASE_URL}${path}" 2>/dev/null || true
        done
    done
    $CURL_BIN -s "${BASE_URL}/wp-cron.php?doing_wp_cron=1" > /dev/null 2>&1 || true
    /bin/sleep 2
}

cleanup() {
    /bin/rm -f "$COOKIE_FILE"
}
trap cleanup EXIT

run_k6_baseline() {
    cd "$SCRIPT_DIR"
    K6_BROWSER_ENABLED=true $K6_BIN run \
        -e "BASE_URL=${BASE_URL}" \
        -e "ADMIN_USER=${ADMIN_USER}" \
        -e "ADMIN_PASS=${ADMIN_PASS}" \
        -e "HMAC_SECRET=${HMAC_SECRET}" \
        -e "CONFIG_LABEL=baseline" \
        -e "LOAD_TIER=${LOAD_TIER}" \
        -e "RESULTS_DIR=${RUN_DIR}" \
        -e "RUN_INDEX=${RUN_INDEX}" \
        -e "RUN_TS=${RUN_TS}" \
        -e "CONFIG_ORDER=baseline" \
        "$SCRIPT_DIR/perf-impact-test.js" \
        2>&1 || true
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              BASELINE VARIANCE STUDY                        ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "  Site:       $BASE_URL"
echo "  Load Tier:  $LOAD_TIER"
echo "  Runs:       $RUNS  (baseline-only, no plugin toggling)"
echo "  Results:    $BATCH_DIR"
echo ""
echo "  NOTE: This script does NOT touch any plugin state. It assumes the"
echo "        analytics plugins are in whatever state they are in right now."
echo "        If you want a clean analytics-off baseline, deactivate the"
echo "        analytics plugins manually before running this script."
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

# ===========================================================================
# OUTER LOOP: run baseline N times back-to-back
# ===========================================================================
for run_num in $(seq 1 "$RUNS"); do
    # Re-export PATH at the start of every iteration — same workaround as
    # perf-impact-runner.sh for k6 browser module PATH stripping on macOS.
    export PATH="/opt/homebrew/bin:/opt/homebrew/opt/node@22/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${HOME}/.pyenv/shims:${HOME}/bin:${PATH}"

    RUN_INDEX="$run_num"
    RUN_TS=$(/bin/date +%s)
    RUN_DIR="$BATCH_DIR/run-${RUN_TS}"
    /bin/mkdir -p "$RUN_DIR"
    BATCH_RUN_DIRS+=("$RUN_DIR")

    echo ""
    echo "  ╔══════════════════════════════════════════════════════════════╗"
    echo "  ║  BASELINE RUN ${run_num} / ${RUNS}  (dir: run-${RUN_TS})"
    echo "  ╚══════════════════════════════════════════════════════════════╝"
    echo ""

    # Phase 0: cache priming (each run starts with identical cache state).
    echo "  Cache priming (5 passes across 8 pages)..."
    for pass in 1 2 3 4 5; do
        for path in "/" "/sample-page/" "/hello-world/" "/shop/" "/product/hoodie/" "/wp-admin/" "/cart/" "/my-account/"; do
            $CURL_BIN -s -o /dev/null "${BASE_URL}${path}" 2>/dev/null || true
        done
    done
    /bin/sleep 3
    echo "  Cache priming complete."
    echo ""

    echo "  Warming up..."
    warmup
    echo ""
    echo "  Running k6 baseline..."
    run_k6_baseline
    echo ""
done

# ===========================================================================
# Aggregate across all baseline runs
# ===========================================================================
echo "  ════════════════════════════════════════════════════"
echo "  Aggregating ${RUNS} baseline run(s) into variance report..."
echo ""

$NODE_BIN "$SCRIPT_DIR/aggregate-runs.mjs" "$BATCH_DIR" "$LOAD_TIER" "$BATCH_TS" "${BATCH_RUN_DIRS[@]}" \
    2>&1 || echo "  Aggregation failed. Raw per-run data is in $BATCH_DIR/run-*/"

# ===========================================================================
# Variance-specific summary (beyond what aggregate-runs.mjs prints)
# ===========================================================================
echo ""
echo "  ════════════════════════════════════════════════════"
echo "  BASELINE VARIANCE INTERPRETATION"
echo "  ════════════════════════════════════════════════════"
echo ""

$NODE_BIN -e "
const fs = require('fs');
const path = require('path');
const batchDir = process.argv[1];
const summaryPath = path.join(batchDir, 'summary-${BATCH_TS}.json');
if (!fs.existsSync(summaryPath)) {
    console.log('  (no summary file to interpret)');
    process.exit(0);
}
const s = JSON.parse(fs.readFileSync(summaryPath, 'utf8'));
const b = s.baseline;
const nf = s.noise_floor;
const range = (v) => b.vitals[v].max - b.vitals[v].min;
const pct = (r, m) => m > 0 ? ((r / m) * 100).toFixed(1) + '%' : '--';

console.log(\`  Baseline ran \${b.runs_count} times on load tier '\${s.load_tier}'.\`);
console.log('');
console.log(\`    LCP:  median \${b.vitals.lcp.median}ms   min \${b.vitals.lcp.min}ms   max \${b.vitals.lcp.max}ms   IQR \${b.vitals.lcp.iqr}   range \${range('lcp')}ms (\${pct(range('lcp'), b.vitals.lcp.median)})\`);
console.log(\`    TTFB: median \${b.vitals.ttfb.median}ms  min \${b.vitals.ttfb.min}ms  max \${b.vitals.ttfb.max}ms  IQR \${b.vitals.ttfb.iqr}   range \${range('ttfb')}ms (\${pct(range('ttfb'), b.vitals.ttfb.median)})\`);
console.log(\`    FCP:  median \${b.vitals.fcp.median}ms   min \${b.vitals.fcp.min}ms   max \${b.vitals.fcp.max}ms   IQR \${b.vitals.fcp.iqr}   range \${range('fcp')}ms (\${pct(range('fcp'), b.vitals.fcp.median)})\`);
console.log('');
console.log(\`  Noise floor (max |delta| between any two baseline runs):\`);
console.log(\`    LCP ±\${nf.lcp}ms   TTFB ±\${nf.ttfb}ms   FCP ±\${nf.fcp}ms\`);
console.log('');
console.log('  INTERPRETATION:');
const lcpNoise = nf.lcp;
if (lcpNoise <= 50) {
    console.log('    Noise floor is SMALL (<= 50ms LCP). Test environment is stable;');
    console.log('    plugin differences as small as 50-100ms are meaningful.');
} else if (lcpNoise <= 200) {
    console.log('    Noise floor is MEDIUM (50-200ms LCP). Only plugin differences');
    console.log('    larger than the noise floor are reliable. Smaller differences');
    console.log('    are measurement noise — do not report them as real.');
} else if (lcpNoise <= 500) {
    console.log('    Noise floor is LARGE (200-500ms LCP). This test environment');
    console.log('    cannot reliably detect plugin differences under 500ms.');
    console.log('    Consider running more iterations OR moving to a dedicated');
    console.log('    test environment (ROADMAP-PERFORMANCE.md Phase 4).');
} else {
    console.log('    Noise floor is VERY LARGE (> 500ms LCP). This environment is');
    console.log('    too noisy for meaningful plugin comparison. You need a cleaner');
    console.log('    test setup before publishing any numbers.');
}
console.log('');
console.log(\`  Full summary:   \${summaryPath}\`);
" "$BATCH_DIR" 2>&1

echo ""
echo "  Done."
echo ""
