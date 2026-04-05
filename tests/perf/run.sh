#!/usr/bin/env bash
#
# Runner script for the analytics traffic simulation framework.
#
# Loads .env configuration, authenticates with WordPress,
# and dispatches to the appropriate k6 test script.
#
# Usage:
#   ./tests/perf/run.sh [continuous|accuracy|compare|browser|burst|perf-impact|all]
#
# Modes:
#   continuous   — Low-rate always-on traffic (runs until Ctrl+C)
#   accuracy     — Deterministic 100-hit validation test
#   compare      — Cross-plugin accuracy comparison
#   browser      — Real browser simulation (k6 browser module)
#   burst        — Default 3-minute burst test
#   perf-impact  — Web Vitals overhead comparison (toggles plugins via WP-CLI)
#   all          — Runs accuracy, then continuous with periodic comparison
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
ENV_FILE="$PLUGIN_DIR/../.env"

# Load .env if it exists.
# Uses grep to extract specific keys, avoiding shell expansion issues with special chars.
if [ -f "$ENV_FILE" ]; then
    echo "Loading .env from $ENV_FILE"
    extract_env() {
        local key="$1"
        grep "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | sed "s/^${key}=//"
    }
    _WP_SITE_URL="$(extract_env WP_SITE_URL)"
    _WP_ADMIN_USER="$(extract_env WP_ADMIN_USER)"
    _WP_ADMIN_PASS="$(extract_env WP_ADMIN_PASS)"
    _HMAC_SECRET="$(extract_env HMAC_SECRET)"
    _K6_MODE="$(extract_env K6_MODE)"
    _K6_RATE="$(extract_env K6_RATE)"
    # Only override if non-empty.
    [ -n "$_WP_SITE_URL" ] && WP_SITE_URL="$_WP_SITE_URL"
    [ -n "$_WP_ADMIN_USER" ] && WP_ADMIN_USER="$_WP_ADMIN_USER"
    [ -n "$_WP_ADMIN_PASS" ] && WP_ADMIN_PASS="$_WP_ADMIN_PASS"
    [ -n "$_HMAC_SECRET" ] && HMAC_SECRET="$_HMAC_SECRET"
    [ -n "$_K6_MODE" ] && K6_MODE="$_K6_MODE"
    [ -n "$_K6_RATE" ] && K6_RATE="$_K6_RATE"
fi

# Defaults (can be overridden by .env or environment).
BASE_URL="${WP_SITE_URL:-http://localhost:10013}"
ADMIN_USER="${WP_ADMIN_USER:-root}"
ADMIN_PASS="${WP_ADMIN_PASS:-q1w2e3}"
HMAC_SECRET="${HMAC_SECRET:-}"
K6_MODE="${K6_MODE:-burst}"
K6_RATE="${K6_RATE:-3}"

# Override mode from argument.
MODE="${1:-$K6_MODE}"

# Ensure results directory exists.
mkdir -p "$SCRIPT_DIR/results"

# Run k6 with environment variables passed directly (avoids shell expansion issues).
run_k6() {
    local script="$1"
    shift
    # Pass all env vars via individual -e flags with proper quoting.
    k6 run \
        -e "BASE_URL=${BASE_URL}" \
        -e "ADMIN_USER=${ADMIN_USER}" \
        -e "ADMIN_PASS=${ADMIN_PASS}" \
        -e "HMAC_SECRET=${HMAC_SECRET}" \
        "$@" \
        "$script"
}

echo "============================================"
echo "  Analytics Traffic Simulation Framework"
echo "============================================"
echo "  Site:    $BASE_URL"
echo "  User:    $ADMIN_USER"
echo "  Mode:    $MODE"
if [ -n "$HMAC_SECRET" ]; then echo "  HMAC:    set (${#HMAC_SECRET} chars)"; else echo "  HMAC:    not set"; fi
echo "  Rate:    $K6_RATE/min (continuous mode)"
echo "============================================"

# Change to script dir so k6 open() resolves data/ correctly.
cd "$SCRIPT_DIR"

case "$MODE" in
    continuous)
        echo "Starting continuous traffic simulation (Ctrl+C to stop)..."
        run_k6 simulate-traffic.js \
            -e MODE=continuous \
            -e "RATE=$K6_RATE"
        ;;

    accuracy)
        echo "Running deterministic accuracy test (100 hits)..."
        run_k6 data-accuracy.js
        ;;

    compare)
        echo "Running cross-plugin comparison..."
        run_k6 cross-plugin-compare.js
        ;;

    browser)
        echo "Running real browser simulation..."
        K6_BROWSER_ENABLED=true run_k6 browser-journeys.js
        ;;

    burst)
        echo "Running burst traffic test (3 minutes)..."
        run_k6 simulate-traffic.js -e MODE=burst
        ;;

    discover)
        echo "Discovering installed analytics plugins..."
        run_k6 cross-plugin-compare.js -e MODE=discover
        ;;

    perf-impact)
        echo "Running performance impact comparison..."
        echo "This toggles plugins via WP-CLI and measures Web Vitals overhead."
        echo "Estimated time: ~30-80 minutes depending on load tier."
        echo ""
        exec "$SCRIPT_DIR/perf-impact-runner.sh" "${2:-medium}"
        ;;

    compare-browser)
        echo "Running production-realistic browser comparison test..."
        echo "10 visitors, ~30 page views via real Chromium browser."
        echo ""
        K6_BROWSER_ENABLED=true run_k6 browser-compare-test.js
        ;;

    all)
        echo "Running full test suite..."

        echo ""
        echo "--- Phase 1: Accuracy Test ---"
        run_k6 data-accuracy.js || true

        echo ""
        echo "--- Phase 2: Burst Traffic (3 min) ---"
        run_k6 simulate-traffic.js -e MODE=burst || true

        echo ""
        echo "--- Phase 3: Cross-Plugin Comparison ---"
        run_k6 cross-plugin-compare.js || true

        echo ""
        echo "All phases complete. Reports saved to $SCRIPT_DIR/results/"
        ;;

    *)
        echo "Unknown mode: $MODE"
        echo "Usage: $0 [continuous|accuracy|compare|browser|burst|discover|perf-impact|compare-browser|all]"
        exit 1
        ;;
esac
