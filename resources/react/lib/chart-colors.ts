/**
 * Chart palette sourced from Statnive Brand Guidelines v1.0 § Data Viz.
 *
 * Recharts primitives take raw CSS color strings on their stroke/fill
 * props, so Tailwind tokens have to be materialized as hex literals
 * here. Keep this file as the single source of truth for every
 * charting surface in the dashboard.
 */
export const CHART_SERIES = [
	'#00A693', // Persian Green — primary series
	'#0A2540', // Navy          — secondary series
	'#C9A961', // Gold           — tertiary
	'#7B9FB8', // Muted blue    — quaternary
	'#B85C4A', // Terracotta    — negative / accent
	'#6B7A8F', // Neutral
] as const;

export const CHART_GRID = '#E8E5DC';
export const CHART_AXIS_TICK = 'rgba(10, 37, 64, 0.60)';
export const CHART_NEGATIVE = '#B85C4A';
