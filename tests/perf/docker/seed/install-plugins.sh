#!/bin/bash
#
# Install and activate the 8 analytics plugins for comparison testing.
#
# Statnive is volume-mounted (not downloaded from WordPress.org).
# All others are installed from the WordPress.org plugin directory.
#
# Usage (called by init.sh):
#   bash install-plugins.sh <wp-path> <admin-user>
#

set -euo pipefail

WP_PATH="${1:-/var/www/html}"
ADMIN_USER="${2:-admin}"

# Plugins to install from WordPress.org.
# Statnive is excluded — it's volume-mounted into wp-content/plugins/statnive/.
PLUGINS=(
    "wp-statistics"
    "koko-analytics"
    "burst-statistics"
    "independent-analytics"
    "wp-slimstat"
    "jetpack"
    "google-analytics-for-wordpress"
)

for slug in "${PLUGINS[@]}"; do
    if wp plugin is-installed "$slug" --path="$WP_PATH" --allow-root 2>/dev/null; then
        echo "  [OK] $slug already installed"
        wp plugin activate "$slug" --path="$WP_PATH" --allow-root 2>/dev/null || true
    else
        echo "  [DL] Installing $slug from WordPress.org..."
        wp plugin install "$slug" --activate --path="$WP_PATH" --allow-root 2>/dev/null || {
            echo "  [!!] Failed to install $slug — skipping"
            continue
        }
        echo "  [OK] $slug installed and activated"
    fi
done

echo "[plugins] All analytics plugins processed."
