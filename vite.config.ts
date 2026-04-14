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
	// Rewrite WordPress-provided packages (React, ReactDOM, @wordpress/i18n) to
	// their window globals so they are NOT bundled. wp-element ships React 18 as
	// window.React / window.ReactDOM. Every named export is enumerated for
	// tree-shaking; the plugin runs with enforce:'pre' so it resolves before
	// @vitejs/plugin-react.
	const wpExternalMap: Record<string, { id: string; code: string }> = {
		'@wordpress/i18n': {
			id: '\0wp-i18n',
			code: 'const { __, sprintf, _n, _x } = window.wp.i18n; export { __, sprintf, _n, _x };',
		},
		'react': {
			id: '\0wp-react',
			code: [
				'const R = window.React;',
				'export default R;',
				'export const Children = R.Children;',
				'export const Component = R.Component;',
				'export const Fragment = R.Fragment;',
				'export const Profiler = R.Profiler;',
				'export const PureComponent = R.PureComponent;',
				'export const StrictMode = R.StrictMode;',
				'export const Suspense = R.Suspense;',
				'export const cloneElement = R.cloneElement;',
				'export const createContext = R.createContext;',
				'export const createElement = R.createElement;',
				'export const createFactory = R.createFactory;',
				'export const createRef = R.createRef;',
				'export const forwardRef = R.forwardRef;',
				'export const isValidElement = R.isValidElement;',
				'export const lazy = R.lazy;',
				'export const memo = R.memo;',
				'export const startTransition = R.startTransition;',
				'export const useCallback = R.useCallback;',
				'export const useContext = R.useContext;',
				'export const useDebugValue = R.useDebugValue;',
				'export const useDeferredValue = R.useDeferredValue;',
				'export const useEffect = R.useEffect;',
				'export const useId = R.useId;',
				'export const useImperativeHandle = R.useImperativeHandle;',
				'export const useInsertionEffect = R.useInsertionEffect;',
				'export const useLayoutEffect = R.useLayoutEffect;',
				'export const useMemo = R.useMemo;',
				'export const useReducer = R.useReducer;',
				'export const useRef = R.useRef;',
				'export const useState = R.useState;',
				'export const useSyncExternalStore = R.useSyncExternalStore;',
				'export const useTransition = R.useTransition;',
				'export const version = R.version;',
				// React 19 API used by @tanstack/react-router — provide a shim.
				'export const use = R.use || function use(p) { throw new Error("React.use is not available in React 18"); };',
				'export const act = R.act;',
			].join('\n'),
		},
		'react-dom': {
			id: '\0wp-react-dom',
			code: [
				'const RD = window.ReactDOM;',
				'export default RD;',
				'export const createPortal = RD.createPortal;',
				'export const flushSync = RD.flushSync;',
				'export const unmountComponentAtNode = RD.unmountComponentAtNode;',
				'export const version = RD.version;',
				'export const render = RD.render;',
				'export const hydrate = RD.hydrate;',
				'export const findDOMNode = RD.findDOMNode;',
			].join('\n'),
		},
		'react-dom/client': {
			id: '\0wp-react-dom-client',
			code: 'const RD = window.ReactDOM; export const createRoot = RD.createRoot; export const hydrateRoot = RD.hydrateRoot;',
		},
		'react/jsx-runtime': {
			id: '\0wp-react-jsx',
			code: 'const R = window.React; export const jsx = R.createElement; export const jsxs = R.createElement; export const Fragment = R.Fragment; export const jsxDEV = R.createElement;',
		},
		// react-is is NOT externalized — WordPress does not expose ReactIs
		// as a window global. Let libraries (e.g., Recharts) bundle their own copy.
	};

	const wpExternals = {
		name: 'wp-externals',
		enforce: 'pre' as const,
		resolveId(id: string) {
			return wpExternalMap[id]?.id ?? null;
		},
		load(id: string) {
			for (const entry of Object.values(wpExternalMap)) {
				if (entry.id === id) return entry.code;
			}
			return null;
		},
	};

	return {
		plugins: [wpExternals, react({ jsxRuntime: 'classic' }), tailwindcss()],
		publicDir: false,
		base: './',
		resolve: {
			alias: {
				'@': resolve(__dirname, 'resources/react'),
			},
		},
		esbuild: {
			legalComments: 'inline',
			banner: licenseBanner,
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
