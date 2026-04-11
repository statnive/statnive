# Performance Test Docker Environment

> Reproducible WordPress + WooCommerce + 8 analytics plugins stack for
> performance, accuracy, browser, and E2E testing.

---

## Quick Start

```bash
cd statnive/tests/perf/docker

# First time (~2-3 min: builds PHP image, downloads WP + plugins, seeds DB):
./run-docker.sh up

# Subsequent starts (~5 sec: containers + volumes already exist):
./run-docker.sh up

# Run any test mode:
./run-docker.sh test perf-impact light
RUNS=5 ./run-docker.sh test perf-impact light
./run-docker.sh test baseline-variance light 5
./run-docker.sh test accuracy
./run-docker.sh test browser
./run-docker.sh test compare-runs results/perf-impact/summary-A.json summary-B.json

# Stop (preserves data):
./run-docker.sh down

# Full cleanup (removes DB + WP volumes — next 'up' re-seeds):
./run-docker.sh clean
```

---

## Architecture

```
Host machine (macOS / Linux)
  |
  k6 (with Chromium browser module)
  |  HTTP requests to localhost:8080
  |
  +-- docker-compose ----------------------------+
  |                                              |
  |  nginx:80 --> php-fpm:9000 --> mariadb:3306  |
  |  (reverse     (4 static       (dedicated,    |
  |   proxy)       workers)        512MB limit)  |
  |                                              |
  +----------------------------------------------+
```

**k6 runs on the host**, not inside Docker. This is because:
- k6's Chromium browser module needs host display access
- The test scripts (`tests/perf/*.sh`, `tests/perf/*.js`) already work on the host
- Host → container via `localhost:8080` is loopback (zero network latency)

**Docker provides only the WordPress server** with:
- Deterministic PHP-FPM worker count (4 static, no dynamic fork/kill)
- Isolated MariaDB with fixed memory limit (512MB)
- No macOS background noise (Spotlight, Time Machine, etc.)
- Reproducible state via volume management

---

## Prerequisites

| Tool | Version | Install |
|------|---------|---------|
| Docker + Docker Compose | 24+ | [docker.com](https://docker.com) |
| k6 | 0.51+ | `brew install k6` |
| Node.js | 22+ | `brew install node@22` |

**k6 browser module** is required for `perf-impact`, `baseline-variance`, and `browser` modes. It's bundled with k6 — no separate install needed.

---

## Environment Variables

Set these before running `./run-docker.sh` or put them in `docker/.env`:

| Variable | Default | Description |
|----------|---------|-------------|
| `WP_PORT` | `8080` | WordPress port on the host |
| `WP_ADMIN_USER` | `admin` | WordPress admin username |
| `WP_ADMIN_PASS` | `admin` | WordPress admin password |
| `WP_ADMIN_EMAIL` | `admin@example.com` | Admin email |
| `MYSQL_ROOT_PASSWORD` | `root` | MariaDB root password |
| `MYSQL_DATABASE` | `wordpress` | Database name |
| `MYSQL_USER` | `wordpress` | Database user |
| `MYSQL_PASSWORD` | `wordpress` | Database password |
| `WP_FPM_WORKERS` | `4` | PHP-FPM static worker count |
| `HMAC_SECRET` | (hardcoded default) | Statnive tracker HMAC signature |
| `RUNS` | `1` | Number of multi-run iterations for perf-impact |

---

## What's Installed

### Services

| Service | Image | Purpose |
|---------|-------|---------|
| `nginx` | `nginx:1.27-alpine` | Reverse proxy, static file serving |
| `php` | `wordpress:6.9-php8.2-fpm` + custom | PHP-FPM with WP-CLI, OPcache, 4 static workers |
| `mariadb` | `mariadb:11.4` | Database with 512MB memory limit |

### WordPress Plugins (activated after seed)

| Plugin | Source | Notes |
|--------|--------|-------|
| Statnive | Volume-mounted from host | Always uses latest local code |
| WP Statistics | WordPress.org | Downloaded during seed |
| Koko Analytics | WordPress.org | Downloaded during seed |
| Burst Statistics | WordPress.org | Downloaded during seed |
| Independent Analytics | WordPress.org | Downloaded during seed |
| WP Slimstat | WordPress.org | Downloaded during seed |
| Jetpack | WordPress.org | Downloaded during seed |
| MonsterInsights (GA4) | WordPress.org | Downloaded during seed |
| WooCommerce | WordPress.org | Downloaded during seed |

### Sample Data

- 5+ WooCommerce products (sample_products.xml or WP-CLI created)
- Sample Page (`/sample-page/`)
- Hello World post (`/hello-world/`)
- WooCommerce pages (`/shop/`, `/cart/`, `/checkout/`, `/my-account/`)

### mu-plugins

- `ground-truth.php` — REST API endpoints for test validation (`/ground-truth/v1/compare-db`, `/ground-truth/v1/site-pages`)

---

## Available Test Modes

All modes are run via `./run-docker.sh test <mode> [args]`:

| Mode | Description | Duration | Needs Browser? |
|------|-------------|----------|:--------------:|
| `perf-impact light` | Plugin overhead comparison (3 browser VUs + 10 HTTP VUs) | ~30-50 min per run | Yes |
| `perf-impact medium` | Same, heavier load (5 + 25 VUs) | ~50-80 min per run | Yes |
| `perf-impact heavy` | Same, stress test (10 + 50 VUs) | ~80-120 min per run | Yes |
| `baseline-variance light 5` | Noise floor calibration (N baseline-only runs) | ~2 min per run | Yes |
| `compare-runs A.json B.json` | Diff two multi-run summaries | Instant | No |
| `accuracy` | 100-hit deterministic validation | ~5 min | No |
| `compare` | Cross-plugin accuracy comparison | ~10 min | No |
| `browser` | Real Chromium browser simulation | ~5 min | Yes |
| `compare-browser` | Browser-based plugin comparison | ~10 min | Yes |
| `burst` | 3-minute burst traffic | ~3 min | No |

### Multi-run mode (recommended for trustworthy results)

```bash
# Run the full plugin matrix 5 times with randomized order per run:
RUNS=5 ./run-docker.sh test perf-impact light

# Output includes median-of-medians, IQR, noise floor, and
# "within noise floor" flags per plugin. See the console table.
```

---

## Typical Workflow for AI Agents

### 1. Performance comparison (the main use case)

```bash
cd statnive/tests/perf/docker

# Start stack
./run-docker.sh up

# Calibrate: measure the Docker env's noise floor
./run-docker.sh test baseline-variance light 5

# If noise floor < 50ms LCP: proceed to comparison
RUNS=5 ./run-docker.sh test perf-impact light

# Read results:
cat ../results/perf-impact/summary-*.json | python3 -m json.tool | head -50

# Compare against a previous run:
./run-docker.sh test compare-runs \
    ../results/perf-impact/summary-PREVIOUS.json \
    ../results/perf-impact/summary-LATEST.json

# Stop when done
./run-docker.sh down
```

### 2. After a code change (regression check)

```bash
cd statnive/tests/perf/docker
./run-docker.sh up

# Run a single perf-impact pass (quick check)
./run-docker.sh test perf-impact light

# Compare against the last known-good summary
./run-docker.sh test compare-runs \
    ../results/perf-impact/summary-LASTGREEN.json \
    ../results/perf-impact/summary-*.json
# Exit code 1 = significant regression detected
```

### 3. Accuracy testing

```bash
cd statnive/tests/perf/docker
./run-docker.sh up
./run-docker.sh test accuracy
# Check ground-truth comparison via REST API
```

---

## Adding a New Test Mode

1. Create your test script at `tests/perf/my-new-test.js`
2. Add a case to `tests/perf/run.sh`:
   ```bash
   my-new-mode)
       echo "Running my new test..."
       run_k6 my-new-test.js
       ;;
   ```
3. Run it: `./run-docker.sh test my-new-mode`

No changes needed to the Docker environment or `run-docker.sh`.

---

## Adding a New Analytics Plugin

1. Add the slug to `seed/install-plugins.sh`:
   ```bash
   PLUGINS=(
       ...existing plugins...
       "new-plugin-slug"
   )
   ```
2. Add the slug to `ANALYTICS_KEYWORDS` in `perf-impact-runner.sh` (line ~109)
3. Force re-seed: `./run-docker.sh seed`
4. Run the comparison: `RUNS=5 ./run-docker.sh test perf-impact light`

---

## Troubleshooting

**Port 8080 already in use:**
```bash
WP_PORT=9090 ./run-docker.sh up
```

**Seed script fails:**
```bash
./run-docker.sh logs php    # Check PHP container logs
./run-docker.sh seed        # Retry seed
```

**k6 not found:**
```bash
brew install k6
```

**Chromium fails to launch (headless):**
```bash
# k6 browser module needs a display. On headless Linux:
apt-get install -y xvfb
xvfb-run ./run-docker.sh test perf-impact light
```

**Old test data contaminating results:**
```bash
# Per-run data is isolated under results/perf-impact/run-<ts>/
# Old loose JSONs in results/perf-impact/ are from pre-multi-run tests
# The aggregator only reads the run dirs passed to it — old files don't contaminate
```

**Full reset (start from scratch):**
```bash
./run-docker.sh clean
./run-docker.sh up
```

---

## Architecture Decision Records

### Why k6 on host, not in Docker?

k6's browser module spawns Chromium, which needs display access (or Xvfb on headless Linux). Running k6 inside Docker would require mounting `/tmp/.X11-unix`, installing Chromium dependencies, and managing Xvfb — all for zero benefit since loopback network from host to container is trivially fast. Keeping k6 on the host is simpler and matches the existing test workflow.

### Why 4 static PHP-FPM workers?

Dynamic pools (`pm = dynamic`) fork and kill workers based on load, which adds scheduling noise to benchmark measurements. A static pool of 4 workers gives deterministic behavior: the same 4 processes serve every request, with predictable memory usage and no fork overhead. For heavier load tiers (medium: 25 VUs, heavy: 50 VUs), increase `WP_FPM_WORKERS` to 8 or 16.

### Why nginx, not Apache?

The production `wordpress:fpm` image is PHP-FPM only (no web server). Nginx is the standard reverse proxy for FPM — it's lighter, faster at static file serving, and has lower per-connection overhead than Apache with mod_php. Since we're measuring PHP processing time, not web server overhead, the choice of reverse proxy matters less than the FPM configuration.

### Why MariaDB, not MySQL?

MariaDB is a drop-in MySQL replacement with better defaults for small/medium workloads. The `healthcheck.sh` script is built into the official MariaDB image (not available in the MySQL image), which simplifies `depends_on: condition: service_healthy`. Performance difference is negligible for our use case.

---

## File Map

| File | Purpose |
|------|---------|
| `docker-compose.yml` | 3-service stack definition |
| `nginx/default.conf` | nginx → PHP-FPM reverse proxy |
| `php/Dockerfile` | PHP 8.2 FPM with WP-CLI + OPcache |
| `php/www.conf` | PHP-FPM pool: 4 static workers |
| `php/php.ini` | PHP config: OPcache, memory, limits |
| `seed/init.sh` | WordPress install + WooCommerce + sample data |
| `seed/install-plugins.sh` | Download + activate 8 analytics plugins |
| `mu-plugins/ground-truth.php` | REST API endpoints for test validation |
| `run-docker.sh` | One-command wrapper (up/down/test/clean) |
| `.env.example` | Documented environment variable defaults |
| `DEVENV.md` | This file |
