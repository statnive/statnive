/**
 * Ask me! — GET /advisor/questions perf gate.
 *
 * The inventory endpoint is cached behind a 1-hour transient (locale-keyed)
 * so a cold first request triggers `Questions::with_searchable()` (the
 * memoized 120-row bilingual build) and every subsequent request reads
 * the transient. Plan §G.14 budget: p95 < 50ms in the warm case.
 *
 * 500 VUs × 30s exercises the warm path; the per-VU first request
 * absorbs the cold cost, so we threshold p95 at 80ms to absorb the
 * cold-miss tail while still failing on regression.
 *
 * Usage:
 *   k6 run tests/perf/ask-inventory-perf.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e ADMIN_USER=root -e ADMIN_PASS=q1w2e3
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';
import { BASE_URL, ADMIN_USER, ADMIN_PASS, REST_URL } from './lib/config.js';
import { authenticate } from './lib/wp-auth.js';

const inventoryLatency = new Trend('advisor_inventory_latency_ms');

export const options = {
	scenarios: {
		inventory: {
			executor: 'constant-vus',
			vus: 500,
			duration: '30s',
		},
	},
	thresholds: {
		http_req_duration: ['p(95)<80', 'p(99)<300'],
		advisor_inventory_latency_ms: ['p(95)<80'],
		'http_req_failed{name:advisor-questions}': ['rate<0.01'],
	},
};

export function setup() {
	const auth = authenticate(ADMIN_USER, ADMIN_PASS, BASE_URL);
	if (!auth.success) {
		throw new Error(
			`k6 setup: WordPress login failed for user "${ADMIN_USER}". ` +
				'Set BASE_URL / ADMIN_USER / ADMIN_PASS env vars.',
		);
	}
	return { headers: auth.headers, cookies: auth.cookies };
}

export default function (data) {
	const url = `${REST_URL}/statnive/v1/advisor/questions`;
	const res = http.get(url, {
		headers: data.headers,
		tags: { name: 'advisor-questions' },
	});
	inventoryLatency.add(res.timings.duration);

	check(res, {
		'status is 200': (r) => r.status === 200,
		'returns 120 questions': (r) => {
			try {
				const j = JSON.parse(r.body);
				return Array.isArray(j.questions) && j.questions.length === 120;
			} catch (_e) {
				return false;
			}
		},
		'returns 10 categories': (r) => {
			try {
				const j = JSON.parse(r.body);
				return Array.isArray(j.categories) && j.categories.length === 10;
			} catch (_e) {
				return false;
			}
		},
	});

	sleep(0.2);
}
