# Dashboard Performance Baseline

> Captured: 2026-04-11
> Environment: Local by Flywheel, PHP 8.2.29, MySQL 8.0.35, macOS
> Method: 10 curl requests per endpoint, cold cache, authenticated admin user

## Backend — REST Endpoint Response Times (seconds)

| Endpoint | p50 | p95 | Min | Max |
|----------|-----|-----|-----|-----|
| `GET /summary` (7d+today) | 0.383 | 0.967 | 0.199 | 1.031 |
| `GET /sources` (7d, limit=10) | 0.301 | 0.697 | 0.214 | 0.775 |
| `GET /pages` (7d, limit=10) | 0.258 | 0.562 | 0.183 | 0.695 |
| `GET /dimensions/countries` (7d) | 0.272 | 0.646 | 0.197 | 0.669 |
| `GET /utm` (7d) | 0.382 | 0.493 | 0.224 | 0.548 |
| `GET /pages/entry` (7d) | 0.389 | 0.530 | 0.231 | 0.562 |

**Overview page estimated TTFB** (max of summary + sources + pages, parallel): ~1.031s worst case

## Frontend — Bundle Size

| Asset | Raw (KB) | Gzipped (KB) |
|-------|----------|-------------|
| main.js | 755 | 227 |
| main.css | 22 | 5 |
| **Total** | **777** | **232** |

- JS chunk count: **1** (single monolithic bundle)
- Vite warns: "Some chunks are larger than 500 kB after minification"

## Targets (After Optimization)

| Metric | Before | Target | After | Delta |
|--------|--------|--------|-------|-------|
| Summary endpoint p50 (ms) | 383 | <190 (>50% reduction) | — | — |
| Overview worst-case TTFB (ms) | 1031 | <620 (>40% reduction) | — | — |
| Overview DB queries (cached) | 8+ | ≤2 | — | — |
| Main JS bundle (KB gz) | 227 | <159 (>30% reduction) | — | — |
| JS chunk count | 1 | ≥4 | — | — |
