// Generated from BDD scenarios — Feature: Custom Events and Engagement Tracking
// NOTE: EventsPage, useEvents, and useEventProperties do not exist yet.
// All tests are skipped until these components/hooks are implemented.

import { describe, it } from 'vitest';

describe('EventsPage', () => {
	// REQ-5.11 — Event dashboard shows event name with counts
	it.skip('renders event names with their occurrence counts (EventsPage not implemented yet)', () => {
		// Expected: EventsPage renders a list of events with name and count columns.
		// Depends on: @/pages/events (EventsPage), @/hooks/use-events (useEvents)
	});

	// REQ-5.11 — Selecting an event shows property breakdown
	it.skip('shows property breakdown table when an event name is selected (EventsPage not implemented yet)', () => {
		// Expected: Clicking an event name shows a detail table with key/value/count rows.
		// Depends on: @/pages/events (EventsPage), @/hooks/use-event-properties (useEventProperties)
	});

	// Empty state
	it.skip('renders empty state when no events are recorded (EventsPage not implemented yet)', () => {
		// Expected: Displays "no events recorded" message when useEvents returns empty data.
		// Depends on: @/pages/events (EventsPage), @/hooks/use-events (useEvents)
	});
});
