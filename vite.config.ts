import { defineConfig } from 'vite';
import { resolve } from 'path';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
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
				emptyOutDir: true,
				minify: 'terser',
				terserOptions: {
					compress: {
						drop_console: true,
						passes: 2,
					},
				},
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
				output: {
					entryFileNames: 'assets/[name]-[hash].js',
					chunkFileNames: 'assets/[name]-[hash].js',
					assetFileNames: 'assets/[name]-[hash].[ext]',
				},
			},
		},
	};
});
