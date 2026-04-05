import { defineConfig } from 'vitest/config';
import { resolve } from 'path';

export default defineConfig({
	resolve: {
		alias: {
			'@': resolve(__dirname, 'resources/react'),
		},
	},
	test: {
		environment: 'jsdom',
		globals: true,
		setupFiles: ['resources/react/__tests__/setup.ts'],
		include: ['resources/react/__tests__/**/*.test.{ts,tsx}'],
	},
});
