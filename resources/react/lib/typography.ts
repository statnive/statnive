/**
 * Typography class tokens sourced from Statnive Brand Guidelines v1.0 § 04.
 *
 * The `#statnive-app`-scoped preflight in `globals.css` already applies
 * `font-family: var(--font-display)` to h1–h6, so the Tailwind
 * `font-display` utility is only used here for non-heading elements
 * (labels, numbers, column headers) where the font doesn't cascade.
 */

// H2 / 28 px — page titles.
export const HEADING_H2 = 'text-[28px] leading-tight font-medium tracking-tight';

// H3 / 20 px — card titles, section headings.
export const HEADING_H3 = 'text-[20px] leading-snug font-medium tracking-tight';
