/**
 * IP spoof helper — pairs with `mu-plugins/statnive-ip-filter.php`.
 *
 * The MU plugin is gated on `STATNIVE_E2E_IP_FILTER=1`. When present, it
 * reads the `X-Test-Client-IP` header and feeds it to the
 * `statnive_client_ip` filter so `PrivacyManager::check_request_privacy()`
 * sees the spoofed IP in exclusion matching.
 */

import type { BrowserContext } from '@playwright/test';

export async function withClientIp(context: BrowserContext, ip: string): Promise<void> {
	await context.setExtraHTTPHeaders({ 'X-Test-Client-IP': ip });
}
