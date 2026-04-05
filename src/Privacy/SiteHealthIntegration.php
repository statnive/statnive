<?php

declare(strict_types=1);

namespace Statnive\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Site Health integration.
 *
 * Adds Statnive privacy compliance checks to the WP Site Health screen.
 */
final class SiteHealthIntegration {

	/**
	 * Register Site Health checks.
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', [ self::class, 'add_tests' ] );
	}

	/**
	 * Add Statnive tests to Site Health.
	 *
	 * @param array<string, array<string, mixed>> $tests Existing tests.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_tests( array $tests ): array {
		$tests['direct']['statnive_privacy'] = [
			'label' => __( 'Statnive Privacy Compliance', 'statnive' ),
			'test'  => [ self::class, 'test_privacy_compliance' ],
		];

		$tests['direct']['statnive_retention'] = [
			'label' => __( 'Statnive Data Retention', 'statnive' ),
			'test'  => [ self::class, 'test_data_retention' ],
		];

		return $tests;
	}

	/**
	 * Test: Overall privacy compliance score.
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public static function test_privacy_compliance(): array {
		$score = ComplianceAuditor::score();

		if ( $score >= 80 ) {
			$status      = 'good';
			$description = __( 'Statnive privacy compliance is strong.', 'statnive' );
		} elseif ( $score >= 60 ) {
			$status      = 'recommended';
			$description = __( 'Statnive privacy settings could be improved. Review the Privacy Audit in your dashboard.', 'statnive' );
		} else {
			$status      = 'critical';
			$description = __( 'Statnive privacy compliance needs attention. Open Settings to configure privacy options.', 'statnive' );
		}

		return [
			'label'       => sprintf(
				/* translators: %d: compliance score */
				__( 'Statnive Privacy Score: %d/100', 'statnive' ),
				$score
			),
			'status'      => $status,
			'badge'       => [
				'label' => 'Statnive',
				'color' => 'blue',
			],
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=statnive#/settings' ) ),
				__( 'Review Privacy Settings', 'statnive' )
			),
			'test'        => 'statnive_privacy',
		];
	}

	/**
	 * Test: Data retention configuration.
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public static function test_data_retention(): array {
		$mode = RetentionManager::get_mode();
		$days = RetentionManager::get_retention_days();

		if ( 'forever' === $mode ) {
			return [
				'label'       => __( 'Statnive: Data retained indefinitely', 'statnive' ),
				'status'      => 'recommended',
				'badge'       => [
					'label' => 'Statnive',
					'color' => 'blue',
				],
				'description' => '<p>' . __( 'Analytics data is retained forever. Consider setting a retention period for GDPR compliance.', 'statnive' ) . '</p>',
				'actions'     => sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=statnive#/settings' ) ),
					__( 'Configure Retention', 'statnive' )
				),
				'test'        => 'statnive_retention',
			];
		}

		return [
			'label'       => sprintf(
				/* translators: %1$d: days, %2$s: mode */
				__( 'Statnive: %1$d-day retention (%2$s)', 'statnive' ),
				$days,
				$mode
			),
			'status'      => 'good',
			'badge'       => [
				'label' => 'Statnive',
				'color' => 'blue',
			],
			'description' => '<p>' . __( 'Data retention is configured with automatic cleanup.', 'statnive' ) . '</p>',
			'test'        => 'statnive_retention',
		];
	}
}
