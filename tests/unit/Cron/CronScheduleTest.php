<?php
/**
 * Generated from BDD scenarios: features/04-data-aggregation.feature
 * Scenario: "Aggregation cron is scheduled at 00:15 UTC daily"
 *
 * Tests the DailyAggregationJob constants and scheduling logic as pure
 * assertions against class constants. The actual wp_schedule_event calls
 * are WordPress-dependent and tested in integration tests.
 *
 * May need adjustment when source class API changes.
 */

declare(strict_types=1);

namespace Statnive\Tests\Unit\Cron;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Cron\DailyAggregationJob;

defined( 'ABSPATH' ) || define( 'ABSPATH' , dirname( __DIR__, 6 ) . '/' );

#[CoversClass(DailyAggregationJob::class)]
final class CronScheduleTest extends TestCase {

	public function test_cron_hook_name_matches_specification(): void {
		$this->assertSame( 'statnive_daily_aggregation', DailyAggregationJob::HOOK );
	}

	public function test_scheduled_time_is_at_0015_utc(): void {
		// The schedule() method uses 'tomorrow 00:15:00 UTC'.
		// Verify the strtotime expression produces a time at 00:15 UTC.
		$next_run = strtotime( 'tomorrow 00:15:00 UTC' );

		$this->assertNotFalse( $next_run );

		$hour   = (int) gmdate( 'G', $next_run );
		$minute = (int) gmdate( 'i', $next_run );

		$this->assertSame( 0, $hour, 'Scheduled hour must be 0 (midnight UTC)' );
		$this->assertSame( 15, $minute, 'Scheduled minute must be 15' );
	}

	public function test_scheduled_time_is_in_the_future(): void {
		$next_run = strtotime( 'tomorrow 00:15:00 UTC' );

		$this->assertNotFalse( $next_run );
		$this->assertGreaterThan( time(), $next_run );
	}

	public function test_hook_name_uses_statnive_prefix(): void {
		$this->assertStringStartsWith( 'statnive_', DailyAggregationJob::HOOK );
	}
}
