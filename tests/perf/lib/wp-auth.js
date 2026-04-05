/**
 * WordPress authentication helper.
 *
 * Handles login, session management, and REST nonce retrieval.
 * Reusable for any WordPress site — no plugin-specific logic.
 */

import http from 'k6/http';
import { BASE_URL } from './config.js';

/**
 * Log in to WordPress and return auth cookies.
 *
 * @param {string} [baseUrl] - WordPress base URL. Defaults to config BASE_URL.
 * @param {string} username  - WordPress username.
 * @param {string} password  - WordPress password.
 * @returns {{ cookies: string, success: boolean }}
 */
export function login(username, password, baseUrl = BASE_URL) {
	const loginUrl = `${baseUrl}/wp-login.php`;

	const res = http.post(
		loginUrl,
		{
			log: username,
			pwd: password,
			'wp-submit': 'Log In',
			redirect_to: `${baseUrl}/wp-admin/`,
			testcookie: '1',
		},
		{
			redirects: 0,
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				Cookie: 'wordpress_test_cookie=WP%20Cookie%20check',
			},
		}
	);

	// Extract Set-Cookie headers.
	const setCookies = res.headers['Set-Cookie'];
	if (!setCookies) {
		return { cookies: '', success: false };
	}

	// Combine all cookies into a single string.
	const cookieArray = Array.isArray(setCookies) ? setCookies : [setCookies];
	const cookies = cookieArray
		.map((c) => c.split(';')[0])
		.filter((c) => c.startsWith('wordpress_'))
		.join('; ');

	return {
		cookies,
		success: cookies.length > 0,
	};
}

/**
 * Fetch a REST API nonce from WordPress.
 *
 * @param {string} cookies - Auth cookie string from login().
 * @param {string} [baseUrl] - WordPress base URL.
 * @returns {string} REST nonce, or empty string on failure.
 */
export function getNonce(cookies, baseUrl = BASE_URL) {
	const res = http.get(`${baseUrl}/wp-admin/admin-ajax.php?action=rest-nonce`, {
		headers: { Cookie: cookies },
	});

	if (res.status === 200 && res.body) {
		return res.body.trim();
	}
	return '';
}

/**
 * Build authenticated headers for REST API requests.
 *
 * @param {string} nonce   - REST nonce from getNonce().
 * @param {string} cookies - Auth cookie string from login().
 * @returns {object} Headers object ready for http.get/post.
 */
export function authHeaders(nonce, cookies) {
	const headers = {};
	if (nonce) headers['X-WP-Nonce'] = nonce;
	if (cookies) headers['Cookie'] = cookies;
	return headers;
}

/**
 * Perform full login + nonce retrieval in one call.
 *
 * @param {string} username
 * @param {string} password
 * @param {string} [baseUrl]
 * @returns {{ headers: object, success: boolean, cookies: string, nonce: string }}
 */
export function authenticate(username, password, baseUrl = BASE_URL) {
	const { cookies, success } = login(username, password, baseUrl);
	if (!success) {
		return { headers: {}, success: false, cookies: '', nonce: '' };
	}

	const nonce = getNonce(cookies, baseUrl);
	return {
		headers: authHeaders(nonce, cookies),
		success: true,
		cookies,
		nonce,
	};
}
