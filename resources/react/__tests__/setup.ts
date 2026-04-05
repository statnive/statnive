import '@testing-library/jest-dom/vitest';

// Mock window.StatniveDashboard for all tests.
Object.defineProperty(window, 'StatniveDashboard', {
	value: {
		restUrl: 'http://localhost/wp-json/statnive/v1/',
		nonce: 'test-nonce-123',
		siteTitle: 'Test Site',
		version: '0.1.0',
	},
	writable: true,
});

// Mock matchMedia for prefers-reduced-motion.
Object.defineProperty(window, 'matchMedia', {
	value: (query: string) => ({
		matches: false,
		media: query,
		onchange: null,
		addListener: () => {},
		removeListener: () => {},
		addEventListener: () => {},
		removeEventListener: () => {},
		dispatchEvent: () => false,
	}),
});
