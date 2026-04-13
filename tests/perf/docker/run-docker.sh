#!/usr/bin/env bash
#
# Statnive Docker Test Environment — one-command wrapper.
#
# Manages the Docker stack lifecycle and dispatches test runs.
# k6 runs on the HOST (needs Chromium for browser tests).
# The Docker stack provides only the WordPress server.
#
# Usage:
#   ./run-docker.sh up                           # Start stack + seed (first time ~2 min)
#   ./run-docker.sh down                         # Stop containers (keeps data)
#   ./run-docker.sh clean                        # Stop + remove volumes (full reset)
#   ./run-docker.sh status                       # Show container health
#   ./run-docker.sh seed                         # Re-run seed script (force)
#   ./run-docker.sh logs [service]               # Tail logs
#   ./run-docker.sh test <mode> [args...]        # Start stack + run any test mode
#
# Test mode examples:
#   ./run-docker.sh test perf-impact light
#   RUNS=5 ./run-docker.sh test perf-impact light
#   ./run-docker.sh test baseline-variance light 5
#   ./run-docker.sh test accuracy
#   ./run-docker.sh test browser
#   ./run-docker.sh test compare-runs results/perf-impact/summary-A.json summary-B.json
#
# Environment variables:
#   WP_PORT           WordPress port on host (default: 8080)
#   WP_ADMIN_USER     Admin username (default: admin)
#   WP_ADMIN_PASS     Admin password (default: admin)
#   RUNS              Number of multi-run iterations for perf-impact
#   HMAC_SECRET       Statnive tracker HMAC secret
#
# See DEVENV.md for full documentation.
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PERF_DIR="$(dirname "$SCRIPT_DIR")"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"

# Defaults
WP_PORT="${WP_PORT:-8080}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASS="${WP_ADMIN_PASS:-admin}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

export WP_PORT WP_ADMIN_USER WP_ADMIN_PASS WP_ADMIN_EMAIL

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

compose() {
    docker compose -f "$COMPOSE_FILE" "$@"
}

ensure_up() {
    if ! compose ps --status running 2>/dev/null | grep -q "nginx"; then
        echo "[docker] Starting stack..."
        compose up -d --build --wait
        echo "[docker] Stack is up. Waiting 5s for WordPress entrypoint..."
        sleep 5
    fi
}

ensure_seeded() {
    # Check if the .seeded marker exists inside the php container
    if ! compose exec -T php test -f /var/www/html/.seeded 2>/dev/null; then
        echo "[docker] Running seed script..."
        compose cp "$SCRIPT_DIR/seed/init.sh" php:/seed/init.sh
        compose cp "$SCRIPT_DIR/seed/install-plugins.sh" php:/seed/install-plugins.sh
        compose exec -T php bash -c "chmod +x /seed/*.sh"
        compose exec -T \
            -e WP_ADMIN_USER="$WP_ADMIN_USER" \
            -e WP_ADMIN_PASS="$WP_ADMIN_PASS" \
            -e WP_ADMIN_EMAIL="$WP_ADMIN_EMAIL" \
            -e WP_SITE_URL="http://localhost:${WP_PORT}" \
            php bash /seed/init.sh
    else
        echo "[docker] Already seeded."
    fi
}

wait_for_healthy() {
    echo "[docker] Waiting for nginx healthcheck..."
    local max=30
    for i in $(seq 1 $max); do
        if curl -sf -o /dev/null "http://localhost:${WP_PORT}/wp-login.php" 2>/dev/null; then
            echo "[docker] WordPress is ready at http://localhost:${WP_PORT}"
            return 0
        fi
        sleep 2
    done
    echo "[docker] WARNING: WordPress not responding after ${max} attempts."
    echo "[docker] Check logs with: ./run-docker.sh logs"
    return 1
}

# ---------------------------------------------------------------------------
# Commands
# ---------------------------------------------------------------------------

CMD="${1:-help}"
shift || true

case "$CMD" in
    up)
        echo "============================================"
        echo "  Statnive Docker Test Environment"
        echo "============================================"
        echo "  Port:  $WP_PORT"
        echo "  Admin: $WP_ADMIN_USER / $WP_ADMIN_PASS"
        echo "============================================"
        compose up -d --build --wait
        sleep 5
        ensure_seeded
        wait_for_healthy
        ;;

    down)
        echo "[docker] Stopping stack..."
        compose down
        echo "[docker] Stack stopped. Data preserved in Docker volumes."
        ;;

    clean)
        echo "[docker] Stopping stack and removing ALL data..."
        compose down -v --remove-orphans
        echo "[docker] Clean. Next 'up' will re-seed from scratch."
        ;;

    status)
        compose ps
        echo ""
        echo "Site: http://localhost:${WP_PORT}"
        curl -sf -o /dev/null "http://localhost:${WP_PORT}/wp-login.php" \
            && echo "WordPress: HEALTHY" \
            || echo "WordPress: NOT RESPONDING"
        ;;

    seed)
        ensure_up
        # Force re-seed by removing marker
        compose exec -T php rm -f /var/www/html/.seeded 2>/dev/null || true
        ensure_seeded
        ;;

    logs)
        compose logs -f "$@"
        ;;

    test)
        if [ $# -eq 0 ]; then
            echo "Usage: ./run-docker.sh test <mode> [args...]"
            echo ""
            echo "Modes: perf-impact, baseline-variance, compare-runs, accuracy,"
            echo "       compare, browser, burst, continuous, compare-browser, all"
            echo ""
            echo "Examples:"
            echo "  ./run-docker.sh test perf-impact light"
            echo "  RUNS=5 ./run-docker.sh test perf-impact light"
            echo "  ./run-docker.sh test baseline-variance light 5"
            echo "  ./run-docker.sh test accuracy"
            exit 1
        fi

        # Ensure stack is up and seeded
        ensure_up
        ensure_seeded
        wait_for_healthy || true

        # Export env vars that the test framework reads from .env
        export WP_SITE_URL="http://localhost:${WP_PORT}"
        export WP_ADMIN_URL="http://localhost:${WP_PORT}/wp-admin"
        export HMAC_SECRET="${HMAC_SECRET:-docker_test_hmac_secret_replace_me_with_64_chars_of_randomness!}"

        echo ""
        echo "[docker] Dispatching to test runner: $*"
        echo "[docker] WP_SITE_URL=$WP_SITE_URL"
        echo ""

        # Dispatch to the main run.sh (which lives one directory up from docker/)
        exec "$PERF_DIR/run.sh" "$@"
        ;;

    help|--help|-h)
        echo "Statnive Docker Test Environment"
        echo ""
        echo "Usage: ./run-docker.sh <command> [args...]"
        echo ""
        echo "Commands:"
        echo "  up                    Start stack + seed WordPress"
        echo "  down                  Stop containers (keeps data)"
        echo "  clean                 Stop + remove all data"
        echo "  status                Show container health"
        echo "  seed                  Force re-run seed script"
        echo "  logs [service]        Tail container logs"
        echo "  test <mode> [args]    Run a test mode via run.sh"
        echo ""
        echo "See DEVENV.md for full documentation."
        ;;

    *)
        echo "Unknown command: $CMD"
        echo "Run ./run-docker.sh help for usage."
        exit 1
        ;;
esac
