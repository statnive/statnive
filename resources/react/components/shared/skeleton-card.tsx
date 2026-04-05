import { cn } from '@/lib/utils';

interface SkeletonCardProps {
	className?: string;
	lines?: number;
}

export function SkeletonCard({ className, lines = 3 }: SkeletonCardProps) {
	return (
		<div className={cn('rounded-lg border border-border bg-card p-4', className)}>
			{Array.from({ length: lines }).map((_, i) => (
				<div
					key={i}
					className={cn(
						'animate-pulse rounded bg-muted',
						i === 0 ? 'mb-3 h-4 w-1/3' : 'mb-2 h-3 w-full',
						i === lines - 1 ? 'w-2/3' : '',
					)}
				/>
			))}
		</div>
	);
}
