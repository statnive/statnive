#!/bin/bash
#
# WordPress seed script for the performance test Docker environment.
#
# Installs WP core, creates admin user, installs WooCommerce + sample data,
# installs 8 analytics plugins, sets permalinks, and creates sample content.
#
# Idempotent: checks for .seeded marker and skips if already done.
#
# Usage (called by run-docker.sh, not directly):
#   docker compose exec php /seed/init.sh
#

set -euo pipefail

MARKER="/var/www/html/.seeded"
WP_PATH="/var/www/html"

# Defaults (can be overridden via env vars)
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASS="${WP_ADMIN_PASS:-admin}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
SITE_URL="${WP_SITE_URL:-http://localhost:8080}"
SITE_TITLE="Statnive Test Site"

if [ -f "$MARKER" ]; then
    echo "[seed] Already seeded (marker exists at $MARKER). Skipping."
    echo "[seed] To re-seed, run: docker compose exec php rm $MARKER"
    exit 0
fi

echo "[seed] Starting WordPress seed..."

# ---------------------------------------------------------------------------
# Wait for WordPress files to be ready (docker-entrypoint may still be running)
# ---------------------------------------------------------------------------
echo "[seed] Waiting for wp-config.php..."
for i in $(seq 1 30); do
    if [ -f "$WP_PATH/wp-config.php" ]; then break; fi
    sleep 2
done
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo "[seed] ERROR: wp-config.php not found after 60s"
    exit 1
fi

# ---------------------------------------------------------------------------
# Install WordPress core
# ---------------------------------------------------------------------------
if ! wp core is-installed --path="$WP_PATH" --allow-root 2>/dev/null; then
    echo "[seed] Installing WordPress core..."
    wp core install \
        --url="$SITE_URL" \
        --title="$SITE_TITLE" \
        --admin_user="$ADMIN_USER" \
        --admin_password="$ADMIN_PASS" \
        --admin_email="$ADMIN_EMAIL" \
        --skip-email \
        --path="$WP_PATH" \
        --allow-root
else
    echo "[seed] WordPress already installed."
fi

# ---------------------------------------------------------------------------
# Set permalink structure (required for REST API and pretty URLs)
# ---------------------------------------------------------------------------
echo "[seed] Setting permalink structure..."
wp rewrite structure '/%postname%/' --path="$WP_PATH" --allow-root
wp rewrite flush --path="$WP_PATH" --allow-root

# ---------------------------------------------------------------------------
# Install and activate WooCommerce
# ---------------------------------------------------------------------------
if ! wp plugin is-installed woocommerce --path="$WP_PATH" --allow-root 2>/dev/null; then
    echo "[seed] Installing WooCommerce..."
    wp plugin install woocommerce --activate --path="$WP_PATH" --allow-root
else
    echo "[seed] Activating WooCommerce..."
    wp plugin activate woocommerce --path="$WP_PATH" --allow-root 2>/dev/null || true
fi

# Create WooCommerce pages (shop, cart, checkout, my-account)
echo "[seed] Setting up WooCommerce pages..."
wp wc tool run install_pages --user="$ADMIN_USER" --path="$WP_PATH" --allow-root 2>/dev/null || true

# ---------------------------------------------------------------------------
# Install 8 analytics plugins
# ---------------------------------------------------------------------------
echo "[seed] Installing analytics plugins..."
bash /seed/install-plugins.sh "$WP_PATH" "$ADMIN_USER"

# ---------------------------------------------------------------------------
# Create sample content
# ---------------------------------------------------------------------------
echo "[seed] Creating sample content..."

# Sample page (used by test framework: /sample-page/)
wp post create --post_type=page --post_title="Sample Page" --post_name="sample-page" \
    --post_status=publish --post_content="This is a sample page for performance testing." \
    --path="$WP_PATH" --allow-root 2>/dev/null || true

# Blog post (used by test framework: /hello-world/)
if ! wp post list --name=hello-world --post_type=post --format=count --path="$WP_PATH" --allow-root 2>/dev/null | grep -q '[1-9]'; then
    wp post create --post_type=post --post_title="Hello world" --post_name="hello-world" \
        --post_status=publish --post_content="Welcome to the Statnive test site." \
        --path="$WP_PATH" --allow-root 2>/dev/null || true
fi

# ---------------------------------------------------------------------------
# Import WooCommerce sample products
# ---------------------------------------------------------------------------
echo "[seed] Importing WooCommerce sample products..."
SAMPLE_XML="$WP_PATH/wp-content/plugins/woocommerce/sample-data/sample_products.xml"
if [ -f "$SAMPLE_XML" ]; then
    # Install the WordPress importer plugin (required for XML import)
    wp plugin install wordpress-importer --activate --path="$WP_PATH" --allow-root 2>/dev/null || true
    wp import "$SAMPLE_XML" --authors=create --path="$WP_PATH" --allow-root 2>/dev/null || true
    echo "[seed] WooCommerce sample products imported."
else
    echo "[seed] WARNING: WooCommerce sample data XML not found at $SAMPLE_XML"
    echo "[seed] Creating minimal products via WP-CLI..."
    for name in "Hoodie" "T-Shirt" "Beanie" "Belt" "Cap"; do
        wp wc product create --name="$name" --regular_price="29.99" --status=publish \
            --user="$ADMIN_USER" --path="$WP_PATH" --allow-root 2>/dev/null || true
    done
fi

# ---------------------------------------------------------------------------
# Statnive-specific setup (activate plugin + run schema creation)
# ---------------------------------------------------------------------------
echo "[seed] Activating Statnive plugin..."
wp plugin activate statnive --path="$WP_PATH" --allow-root 2>/dev/null || true

# Trigger Statnive's schema creation by visiting the admin page
curl -s -o /dev/null "http://localhost/wp-admin/" 2>/dev/null || true

# ---------------------------------------------------------------------------
# Set marker
# ---------------------------------------------------------------------------
touch "$MARKER"
echo "[seed] Done! WordPress is seeded and ready for testing."
echo "[seed] Site URL: $SITE_URL"
echo "[seed] Admin:    $ADMIN_USER / $ADMIN_PASS"
echo "[seed] Plugins:  $(wp plugin list --status=active --field=name --path="$WP_PATH" --allow-root 2>/dev/null | tr '\n' ', ')"
