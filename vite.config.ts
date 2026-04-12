import { defineConfig } from 'vite';
import { resolve } from 'path';
import { readFileSync } from 'fs';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const pkg = JSON.parse(readFileSync(resolve(__dirname, 'package.json'), 'utf-8'));
const licenseBanner = `/*! Statnive v${pkg.version} | GPL-2.0-or-later | https://statnive.com */`;

// Shared Terser options for all tracker builds.
const trackerTerserOptions = {
	compress: {
		drop_console: true,
		passes: 2,
	},
	output: {
		preamble: licenseBanner,
	},
};

export default defineConfig(({ mode }) => {
	// Full tracker build (async external script with all features).
	if (mode === 'tracker') {
		return {
			publicDir: false,
			build: {
				lib: {
					entry: resolve(__dirname, 'resources/tracker/tracker.js'),
					name: 'statnive',
					formats: ['iife'],
					fileName: () => 'statnive.js',
				},
				outDir: resolve(__dirname, 'public/tracker'),
				emptyOutDir: false,
				minify: 'terser',
				terserOptions: trackerTerserOptions,
				sourcemap: true,
				reportCompressedSize: true,
			},
			define: {
				__FEATURE_SPA__: false,
				__FEATURE_EVENTS__: true,
				__FEATURE_ENGAGEMENT__: true,
				__FEATURE_BOT_DETECTION__: true,
			},
		};
	}

	// Core inline tracker build (~300B minified, inlined in wp_footer).
	if (mode === 'tracker-core') {
		return {
			publicDir: false,
			build: {
				lib: {
					entry: resolve(__dirname, 'resources/tracker/tracker-core.js'),
					name: 'statnive_core',
					formats: ['iife'],
					fileName: () => 'statnive-core.js',
				},
				outDir: resolve(__dirname, 'public/tracker'),
				emptyOutDir: false,
				minify: 'terser',
				terserOptions: {
					...trackerTerserOptions,
					mangle: { toplevel: true },
				},
				sourcemap: false,
				reportCompressedSize: true,
			},
			define: {
				__FEATURE_SPA__: false,
				__FEATURE_EVENTS__: false,
				__FEATURE_ENGAGEMENT__: false,
				__FEATURE_BOT_DETECTION__: false,
			},
		};
	}

	// React SPA build for WordPress admin dashboard.
	return {
		plugins: [react(), tailwindcss()],
		publicDir: false,
		base: './',
		resolve: {
			alias: {
				'@': resolve(__dirname, 'resources/react'),
			},
		},
		build: {
			outDir: resolve(__dirname, 'public/react'),
			emptyOutDir: true,
			manifest: true,
			rollupOptions: {
				input: resolve(__dirname, 'resources/react/main.tsx'),
				external: ['@wordpress/i18n'],
				output: {
					entryFileNames: 'assets/[name]-[hash].js',
					chunkFileNames: 'assets/[name]-[hash].js',
					assetFileNames: 'assets/[name]-[hash].[ext]',
					globals: {
						'@wordpress/i18n': 'wp.i18n',
					},
				},
			},
		},
	};
});
