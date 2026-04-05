# Perf Ground Truth Recorder

Standalone WordPress mu-plugin that records expected analytics hits for validation testing.

## Installation

Copy to your WordPress mu-plugins directory:

```bash
cp ground-truth.php /path/to/wp-content/mu-plugins/
```

The table is created automatically on first load. No activation needed.

## REST API Endpoints

All endpoints require `manage_options` capability (admin auth).

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ground-truth/v1/record` | Record an expected hit |
| GET | `/wp-json/ground-truth/v1/summary?from=&to=` | Aggregated totals |
| GET | `/wp-json/ground-truth/v1/by-channel?from=&to=` | Breakdown by channel |
| GET | `/wp-json/ground-truth/v1/by-device?from=&to=` | Breakdown by device |
| GET | `/wp-json/ground-truth/v1/by-page?from=&to=` | Breakdown by page |
| DELETE | `/wp-json/ground-truth/v1/clear?test_run_id=` | Clear test data |

## Record Payload

```json
{
  "test_run_id": "run-123",
  "profile_id": "d01",
  "resource_type": "page",
  "resource_id": 2,
  "page_url": "/sample-page/",
  "referrer_url": "https://www.google.com/search?q=test",
  "expected_channel": "Organic Search",
  "utm_source": "",
  "utm_medium": "",
  "utm_campaign": "",
  "device_type": "desktop",
  "is_bot": false,
  "is_logged_in": false,
  "user_agent": "Mozilla/5.0 ..."
}
```

## Usage with k6

```javascript
import { GROUND_TRUTH_URL } from './lib/config.js';
import { recordHit } from './lib/ground-truth.js';

// After each simulated hit, record ground truth
recordHit({ test_run_id: 'run-123', profile_id: 'd01', ... });
```

## No Dependencies

This mu-plugin has zero dependencies on any analytics plugin. It works with any WordPress site and can be used to validate any analytics tool.
