import { useState, useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { cn } from '@/lib/utils';
import { ArrowUp, ArrowDown, ArrowUpDown } from 'lucide-react';

export interface Column<T> {
	key: string;
	header: string;
	render: (row: T) => React.ReactNode;
	sortable?: boolean;
	align?: 'left' | 'right' | 'center';
	className?: string;
}

interface DataTableProps<T> {
	data: T[];
	columns: Column<T>[];
	title?: string;
	isLoading?: boolean;
	emptyMessage?: string;
	defaultSortKey?: string;
	defaultSortDir?: 'asc' | 'desc';
	getRowKey: (row: T, index: number) => string;
}

export function DataTable<T>({
	data,
	columns,
	title,
	isLoading = false,
	emptyMessage,
	defaultSortKey,
	defaultSortDir = 'desc',
	getRowKey,
}: DataTableProps<T>) {
	const [sortKey, setSortKey] = useState(defaultSortKey);
	const [sortDir, setSortDir] = useState<'asc' | 'desc'>(defaultSortDir);

	const resolvedEmptyMessage = emptyMessage ?? __('No data available', 'statnive');

	const sortedData = useMemo(() => {
		if (!sortKey) return data;

		return [...data].sort((a, b) => {
			const aVal = (a as Record<string, unknown>)[sortKey];
			const bVal = (b as Record<string, unknown>)[sortKey];

			if (typeof aVal === 'number' && typeof bVal === 'number') {
				return sortDir === 'asc' ? aVal - bVal : bVal - aVal;
			}

			const aStr = String(aVal ?? '');
			const bStr = String(bVal ?? '');
			return sortDir === 'asc' ? aStr.localeCompare(bStr) : bStr.localeCompare(aStr);
		});
	}, [data, sortKey, sortDir]);

	function handleSort(key: string) {
		if (sortKey === key) {
			setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'));
		} else {
			setSortKey(key);
			setSortDir('desc');
		}
	}

	if (isLoading) {
		return (
			<div className="space-y-2">
				{title && <h3 className="text-sm font-medium text-muted-foreground">{title}</h3>}
				{Array.from({ length: 5 }).map((_, i) => (
					<div key={i} className="h-8 animate-pulse rounded bg-muted" />
				))}
			</div>
		);
	}

	return (
		<div>
			{title && (
				<h3 className="mb-3 text-sm font-medium text-muted-foreground">{title}</h3>
			)}
			<div className="overflow-x-auto">
				<table className="w-full text-sm" role="table">
					<thead>
						<tr className="border-b border-border">
							{columns.map((col) => (
								<th
									key={col.key}
									scope="col"
									tabIndex={col.sortable ? 0 : undefined}
									role={col.sortable ? 'button' : undefined}
									className={cn(
										'px-3 py-2 text-xs font-medium uppercase tracking-wider text-muted-foreground',
										col.align === 'right' ? 'text-right' : 'text-left',
										col.sortable ? 'cursor-pointer select-none hover:text-foreground' : '',
										col.className,
									)}
									onClick={col.sortable ? () => handleSort(col.key) : undefined}
									onKeyDown={
										col.sortable
											? (e) => {
													if (e.key === 'Enter' || e.key === ' ') {
														e.preventDefault();
														handleSort(col.key);
													}
												}
											: undefined
									}
									aria-sort={
										sortKey === col.key
											? sortDir === 'asc'
												? 'ascending'
												: 'descending'
											: col.sortable
												? 'none'
												: undefined
									}
								>
									<span className="inline-flex items-center gap-1">
										{col.header}
										{col.sortable && sortKey === col.key ? (
											sortDir === 'asc' ? (
												<ArrowUp className="h-3 w-3" />
											) : (
												<ArrowDown className="h-3 w-3" />
											)
										) : col.sortable ? (
											<ArrowUpDown className="h-3 w-3 opacity-30" />
										) : null}
									</span>
								</th>
							))}
						</tr>
					</thead>
					<tbody>
						{sortedData.length === 0 ? (
							<tr>
								<td
									colSpan={columns.length}
									className="px-3 py-8 text-center text-muted-foreground"
								>
									{resolvedEmptyMessage}
								</td>
							</tr>
						) : (
							sortedData.map((row, index) => (
								<tr
									key={getRowKey(row, index)}
									className="border-b border-border/50 transition-colors duration-150 hover:bg-muted/50"
								>
									{columns.map((col) => (
										<td
											key={col.key}
											className={cn(
												'px-3 py-2',
												col.align === 'right' ? 'text-right' : 'text-left',
												col.className,
											)}
										>
											{col.render(row)}
										</td>
									))}
								</tr>
							))
						)}
					</tbody>
				</table>
			</div>
		</div>
	);
}
