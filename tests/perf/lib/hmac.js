/**
 * HMAC signature helper for Statnive tracker payloads.
 *
 * Reusable across all test scripts. Extracted from the original
 * simulate-traffic.js to avoid duplication.
 */

import crypto from 'k6/crypto';

/**
 * Compute HMAC-SHA256 signature for a tracker hit.
 *
 * @param {string} secret  - HMAC secret key (from Statnive settings).
 * @param {string} resourceType - 'page' or 'post'.
 * @param {number} resourceId   - WordPress post/page ID.
 * @returns {string} Hex-encoded HMAC signature, or fallback for testing.
 */
export function computeSignature(secret, resourceType, resourceId) {
	if (!secret) return 'test-signature';
	return crypto.hmac('sha256', secret, resourceType + '|' + String(resourceId), 'hex');
}
