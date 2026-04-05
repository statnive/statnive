import { formatNumber } from '@/lib/utils';

interface DualBarCellProps {
	visitors: number;
	secondaryValue: number;
	secondaryLabel?: string;
	maxVisitors: number;
	maxSecondary: number;
}

export function DualBarCell({
	visitors,
	secondaryValue,
	secondaryLabel = '',
	maxVisitors,
	maxSecondary,
}: DualBarCellProps) {
	const visitorWidth = maxVisitors > 0 ? (visitors / maxVisitors) * 100 : 0;
	const secondaryWidth = maxSecondary > 0 ? (secondaryValue / maxSecondary) * 100 : 0;

	return (
		<div className="flex min-w-[120px] flex-col gap-1">
			<div className="flex items-center gap-2">
				<div
					className="h-[6px] rounded-full bg-primary transition-all duration-200"
					style={{ width: `${visitorWidth}%` }}
				/>
				<span className="whitespace-nowrap text-xs tabular-nums text-foreground">
					{formatNumber(visitors)}
				</span>
			</div>
			<div className="flex items-center gap-2">
				<div
					className="h-[6px] rounded-full bg-revenue transition-all duration-200"
					style={{ width: `${secondaryWidth}%` }}
				/>
				<span className="whitespace-nowrap text-xs tabular-nums text-muted-foreground">
					{secondaryLabel}{formatNumber(secondaryValue)}
				</span>
			</div>
		</div>
	);
}
