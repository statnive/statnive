/**
 * Two action buttons rendered in the dashboard header's right slot on
 * the Settings page (in place of the DateRangePicker). Used to invite
 * a wp.org review and surface the GitHub issue tracker.
 */
import { __ } from '@wordpress/i18n';
import { Star, Bug } from 'lucide-react';

const REVIEW_URL = 'https://wordpress.org/support/plugin/statnive/reviews/#new-post';
const ISSUES_URL = 'https://github.com/statnive/statnive/issues';

const baseClasses =
	'inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-sm font-medium hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';

export function SettingsActions() {
	return (
		<div className="flex items-center gap-2">
			<a
				href={REVIEW_URL}
				target="_blank"
				rel="noopener noreferrer"
				className={baseClasses}
			>
				<Star className="h-4 w-4" aria-hidden="true" />
				{__('Give 5 Stars :D', 'statnive')}
			</a>
			<a
				href={ISSUES_URL}
				target="_blank"
				rel="noopener noreferrer"
				className={baseClasses}
			>
				<Bug className="h-4 w-4" aria-hidden="true" />
				{__('Report Issues', 'statnive')}
			</a>
		</div>
	);
}
