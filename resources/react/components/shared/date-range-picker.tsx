import { __ } from '@wordpress/i18n';
import { cn } from '@/lib/utils';
import type { DateRange } from '@/types/api';

const presets: { value: DateRange; label: string }[] = [
	{ value: 'today', label: 'Today' },
	{ value: '7d', label: '7 Days' },
	{ value: '30d', label: '30 Days' },
	{ value: 'this-month', label: 'This Month' },
	{ value: 'last-month', label: 'Last Month' },
];

interface DateRangePickerProps {
	value: DateRange;
	onChange: (range: DateRange) => void;
}

export function DateRangePicker({ value, onChange }: DateRangePickerProps) {
	return (
		<div className="flex gap-1" role="group" aria-label={__('Date range', 'statnive')}>
			{presets.map((preset) => (
				<button
					key={preset.value}
					type="button"
					onClick={() => onChange(preset.value)}
					className={cn(
						'cursor-pointer rounded-md px-[18px] py-[10px] text-sm font-medium transition-colors duration-150',
						value === preset.value
							? 'bg-primary text-primary-foreground'
							: 'text-muted-foreground hover:bg-muted hover:text-foreground',
					)}
				>
					{__(preset.label, 'statnive')}
				</button>
			))}
		</div>
	);
}
