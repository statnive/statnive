import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
	createRouter,
	createRootRoute,
	createRoute,
	RouterProvider,
	Outlet,
	createHashHistory,
} from '@tanstack/react-router';
import { DashboardLayout } from '@/components/layouts/dashboard-layout';
import { OverviewPage } from '@/pages/overview';
import { PagesPage } from '@/pages/pages';
import { ReferrersPage } from '@/pages/referrers';
import { GeographyPage } from '@/pages/geography';
import { DevicesPage } from '@/pages/devices';
import { LanguagesPage } from '@/pages/languages';
import { RealtimePage } from '@/pages/realtime';
import { SettingsPage } from '@/pages/settings';

const queryClient = new QueryClient({
	defaultOptions: {
		queries: {
			staleTime: 60_000,
			retry: 1,
			refetchOnWindowFocus: false,
		},
	},
});

// Root route with dashboard layout shell.
const rootRoute = createRootRoute({
	component: () => (
		<DashboardLayout>
			<Outlet />
		</DashboardLayout>
	),
});

const overviewRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/',
	component: OverviewPage,
});

const pagesRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/pages',
	component: PagesPage,
});

const referrersRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/referrers',
	component: ReferrersPage,
});

const geographyRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/geography',
	component: GeographyPage,
});

const devicesRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/devices',
	component: DevicesPage,
});

const languagesRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/languages',
	component: LanguagesPage,
});

const realtimeRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/realtime',
	component: RealtimePage,
});

const settingsRoute = createRoute({
	getParentRoute: () => rootRoute,
	path: '/settings',
	component: SettingsPage,
});

const routeTree = rootRoute.addChildren([
	overviewRoute,
	pagesRoute,
	referrersRoute,
	geographyRoute,
	devicesRoute,
	languagesRoute,
	realtimeRoute,
	settingsRoute,
]);

// Use hash history since we're inside WP admin (single admin page).
const hashHistory = createHashHistory();

const router = createRouter({
	routeTree,
	history: hashHistory,
});

declare module '@tanstack/react-router' {
	interface Register {
		router: typeof router;
	}
}

export function App() {
	return (
		<QueryClientProvider client={queryClient}>
			<RouterProvider router={router} />
		</QueryClientProvider>
	);
}
