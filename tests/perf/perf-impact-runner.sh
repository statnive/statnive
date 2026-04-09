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
RUNS="${RUNS:-1}"

if ! [[ "$RUNS" =~ ^[0-9]+$ ]] || [ "$RUNS" -lt 1 ]; then
    echo "ERROR: RUNS must be a positive integer (got: $RUNS)"
    exit 1
fi

# Results directory.
RESULTS_DIR="$SCRIPT_DIR/results/perf-impact"
mkdir -p "$RESULTS_DIR"

BATCH_TS=$(/bin/date +%s)
COOKIE_FILE="/tmp/wp_perf_impact_cookies_$$.txt"

# Collected run directories for this batch (populated inside the outer loop).
typeset -a BATCH_RUN_DIRS

# Per-run state (reset inside the outer loop).
RUN_INDEX=0
RUN_TS=""
RUN_DIR=""
CONFIG_ORDER_CSV=""

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

    local plugins_file="/tmp/wp_plugins_$$.json"
    $CURL_BIN -s -H "X-WP-Nonce: $WP_NONCE" -b "$COOKIE_FILE" \
        "${BASE_URL}/wp-json/wp/v2/plugins" > "$plugins_file"

    for keyword in "${ANALYTICS_KEYWORDS[@]}"; do
        p_slug=$($PYTHON_BIN -c "
import json, sys
with open('${plugins_file}') as f:
    plugins = json.load(f)
for p in plugins:
    if p['plugin'].startswith('${keyword}/'):
        print(p['plugin'])
        break
" 2>/dev/null)

        if [ -n "$p_slug" ]; then
            p_status=$($PYTHON_BIN -c "
import json, sys
with open('${plugins_file}') as f:
    plugins = json.load(f)
for p in plugins:
    if p['plugin'] == '${p_slug}':
        print(p['status'])
        break
" 2>/dev/null)

            PLUGIN_NAMES+=("$keyword")
            PLUGIN_SLUGS+=("$p_slug")
            PLUGIN_ORIG+=("$p_status")
            echo "    [OK] $keyword = $p_slug ($p_status)"
        else
            echo "    [--] $keyword - not installed"
        fi
    done

    /bin/rm -f "$plugins_file"
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
    /bin/rm -f "$COOKIE_FILE"
}

warmup() {
    local pages=("/" "/sample-page/" "/hello-world/" "/shop/" "/product/hoodie/" "/wp-admin/")
    # Run 3 passes to fully warm OPcache + MySQL query cache.
    for pass in 1 2 3; do
        for path in "${pages[@]}"; do
            $CURL_BIN -s -o /dev/null "${BASE_URL}${path}" 2>/dev/null || true
        done
    done
    $CURL_BIN -s "${BASE_URL}/wp-cron.php?doing_wp_cron=1" > /dev/null 2>&1 || true
    /bin/sleep 2
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
        -e "RESULTS_DIR=${RUN_DIR}" \
        -e "RUN_INDEX=${RUN_INDEX}" \
        -e "RUN_TS=${RUN_TS}" \
        -e "CONFIG_ORDER=${CONFIG_ORDER_CSV}" \
        "$SCRIPT_DIR/perf-impact-test.js" \
        2>&1 || true
}

# Fisher-Yates shuffle of PLUGIN_NAMES / PLUGIN_SLUGS / PLUGIN_ORIG arrays in
# place, seeded from the argument for run-to-run reproducibility. Baseline
# and all-plugins are handled separately outside the for-loop, so only the
# middle 8 plugins actually randomize here.
shuffle_plugin_order() {
    local seed="$1"
    local count=${#PLUGIN_NAMES[@]}

    # Use awk for the shuffle — portable and deterministic given a seed.
    # Absolute path used because something in the k6 browser teardown has
    # been observed to strip PATH between outer-loop iterations on macOS.
    local shuffled_indices
    shuffled_indices=$(/usr/bin/awk -v n="$count" -v seed="$seed" 'BEGIN {
        srand(seed);
        for (i = 0; i < n; i++) a[i] = i;
        for (i = n - 1; i > 0; i--) {
            j = int(rand() * (i + 1));
            tmp = a[i]; a[i] = a[j]; a[j] = tmp;
        }
        for (i = 0; i < n; i++) printf "%d ", a[i];
    }')

    typeset -a new_names new_slugs new_orig
    for idx in ${=shuffled_indices}; do
        # zsh arrays are 1-indexed; awk emits 0-indexed.
        new_names+=("${PLUGIN_NAMES[$((idx + 1))]}")
        new_slugs+=("${PLUGIN_SLUGS[$((idx + 1))]}")
        new_orig+=("${PLUGIN_ORIG[$((idx + 1))]}")
    done
    PLUGIN_NAMES=("${new_names[@]}")
    PLUGIN_SLUGS=("${new_slugs[@]}")
    PLUGIN_ORIG=("${new_orig[@]}")
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
echo "  Runs:       $RUNS"
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
echo "  Est. Time:  ~$(( (per_config + 1) * TOTAL_CONFIGS * RUNS )) minutes (${RUNS} run(s) x ${TOTAL_CONFIGS} configs)"
echo ""

trap restore_plugin_state EXIT

# ===========================================================================
# OUTER LOOP: run the full matrix RUNS times for variance reporting
# ===========================================================================
for run_num in $(seq 1 "$RUNS"); do
    # Re-export PATH at the start of every iteration. Something in the k6
    # browser module subprocess teardown (observed on macOS between runs)
    # was stripping PATH so that basic commands like `date`, `mkdir`, and
    # `rm` became unfindable on the second iteration. Re-setting here is
    # cheap and guarantees every iteration starts with a known-good PATH.
    export PATH="/opt/homebrew/bin:/opt/homebrew/opt/node@22/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${HOME}/.pyenv/shims:${HOME}/bin:${PATH}"

    RUN_INDEX="$run_num"
    RUN_TS=$(/bin/date +%s)
    RUN_DIR="$RESULTS_DIR/run-${RUN_TS}"
    /bin/mkdir -p "$RUN_DIR"
    BATCH_RUN_DIRS+=("$RUN_DIR")

    echo ""
    echo "  ╔══════════════════════════════════════════════════════════════╗"
    echo "  ║  RUN ${run_num} / ${RUNS}  (dir: run-${RUN_TS})"
    echo "  ╚══════════════════════════════════════════════════════════════╝"
    echo ""

    # Shuffle the middle plugins for this run (baseline + all-plugins stay in
    # fixed positions — they are handled outside the for-loop below).
    shuffle_plugin_order "$RUN_TS"
    CONFIG_ORDER_CSV="baseline,$(IFS=,; echo "${PLUGIN_NAMES[*]}"),all-plugins"
    echo "  Plugin order: ${PLUGIN_NAMES[*]}"
    echo ""

    CONFIG_NUM=0

    # --- Phase 0: CACHE PRIMING (each run starts with identical cache state) ---
    echo "  [0/$TOTAL_CONFIGS] CACHE PRIMING (warming OPcache + MySQL)"
    echo "    Running 5 passes across 8 pages..."
    for pass in 1 2 3 4 5; do
        for path in "/" "/sample-page/" "/hello-world/" "/shop/" "/product/hoodie/" "/wp-admin/" "/cart/" "/my-account/"; do
            $CURL_BIN -s -o /dev/null "${BASE_URL}${path}" 2>/dev/null || true
        done
    done
    /bin/sleep 3
    echo "    Cache priming complete."
    echo ""

    # --- Phase 1: BASELINE ---
    CONFIG_NUM=$((CONFIG_NUM + 1))
    echo "  [$CONFIG_NUM/$TOTAL_CONFIGS] BASELINE (all analytics OFF)"
    refresh_auth
    echo "    Deactivating all analytics plugins..."
    deactivate_all_analytics
    echo "    Warming up..."
    warmup
    run_k6_vitals "baseline"
    echo ""

    # --- Phase 2: INDIVIDUAL (randomized order via shuffle_plugin_order) ---
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
done

# ===========================================================================
# Aggregation: compute median-of-medians, IQR, and noise floor across all runs
# ===========================================================================
echo "  ════════════════════════════════════════════════════"
echo "  Aggregating ${RUNS} run(s) into summary report..."
echo ""

$NODE_BIN "$SCRIPT_DIR/aggregate-runs.mjs" "$RESULTS_DIR" "$LOAD_TIER" "$BATCH_TS" "${BATCH_RUN_DIRS[@]}" \
    || echo "  Aggregation failed. Raw per-run data is in $RESULTS_DIR/run-*/"

echo ""
echo "  Done! Plugin state will be restored automatically."
echo ""
