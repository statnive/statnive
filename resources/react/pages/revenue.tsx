import { __ } from '@wordpress/i18n';
import { HEADING_H2 } from '@/lib/typography';

/** Revenue Report — stub; full implementation in PR 8. */
export function RevenuePage() {
	return (
		<div className="space-y-4">
			<header>
				<h1 className={HEADING_H2}>{__('Revenue Report', 'statnive')}</h1>
				<p className="mt-1 text-sm text-muted-foreground">
					{__('WooCommerce revenue, channels, products, funnel, coupons, and refunds — in one screen.', 'statnive')}
				</p>
			</header>

			<div className="rounded-lg border border-border bg-card p-10 text-center">
				<p className="text-sm text-muted-foreground">
					{__('The Revenue Report is being built. It will appear here once a WooCommerce store with orders is connected.', 'statnive')}
				</p>
			</div>
		</div>
	);
}
