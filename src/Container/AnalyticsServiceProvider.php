<?php

declare(strict_types=1);

namespace Statnive\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Cron\CronRegistrar;
use Statnive\Entity\VisitorProfile;
use Statnive\Service\DeviceService;
use Statnive\Service\GeoIPService;
use Statnive\Service\ParameterService;
use Statnive\Service\ReferrerService;
use Statnive\Service\SourceDetector;

/**
 * Analytics Service Provider.
 *
 * Registers Phase 2 analytics services: GeoIP, device detection,
 * referrer/channel classification, aggregation, and dashboard REST endpoints.
 *
 * Hooks into the profile enrichment pipeline via the 'statnive_enrich_profile' action.
 */
final class AnalyticsServiceProvider implements ServiceProvider {

	/**
	 * Register analytics service factories.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function register( ServiceContainer $container ): void {
		// Services are static-method-based, no container registration needed.
		// REST controllers are registered in boot() via rest_api_init.
	}

	/**
	 * Bootstrap analytics services.
	 *
	 * @param ServiceContainer $container The service container.
	 */
	public function boot( ServiceContainer $container ): void {
		// Wire the enrichment pipeline.
		add_action( 'statnive_enrich_profile', [ self::class, 'enrich_profile' ] );

		// Persist UTM parameters once session_id / view_id exist.
		add_action( 'statnive_profile_persisted', [ self::class, 'persist_utm' ] );

		// Register cron jobs.
		CronRegistrar::register_all();

		// Register dashboard REST API routes.
		add_action(
			'rest_api_init',
			static function (): void {
				$controllers = [
					new \Statnive\Api\SummaryController(),
					new \Statnive\Api\SourcesController(),
					new \Statnive\Api\PagesController(),
					new \Statnive\Api\DimensionsController(),
					new \Statnive\Api\UtmController(),
					new \Statnive\Api\PagesDetailController(),
					new \Statnive\Api\RealtimeController(),
					new \Statnive\Api\SettingsController(),
					new \Statnive\Api\EventController(),
					new \Statnive\Api\EngagementController(),
					new \Statnive\Api\EventsStatsController(),
					new \Statnive\Api\ImportController(),
					new \Statnive\Api\DiagnosticsController(),
				];

				foreach ( $controllers as $controller ) {
					$controller->register_routes();
				}
			}
		);
	}

	/**
	 * Enrich a VisitorProfile with GeoIP, device, and referrer data.
	 *
	 * Hooked into 'statnive_enrich_profile' action.
	 * All service calls are wrapped in try/catch — failures must not block hit recording.
	 *
	 * @param VisitorProfile $profile The profile to enrich.
	 */
	public static function enrich_profile( VisitorProfile $profile ): void {
		$raw_ip = $profile->get( 'ip', '' );
		$ua     = $profile->get( 'user_agent', '' );

		// GeoIP resolution (uses raw IP before it's discarded).
		try {
			$geo = GeoIPService::resolve( $raw_ip );
			// CDN country headers cover sites without a MaxMind database.
			if ( '' === $geo['country_code'] ) {
				$geo = GeoIPService::resolve_from_request_headers();
			}
			$profile->with_geo_ip(
				$geo['country_code'],
				$geo['country_name'],
				$geo['city_name'],
				$geo['region_code'],
				$geo['continent_code'],
				$geo['continent']
			);
		} catch ( \Exception $e ) {
			self::log_error( 'GeoIP', $e );
		}

		// Device detection.
		try {
			$device = DeviceService::parse( $ua );
			$profile->with_device_data(
				$device['device_type'],
				$device['browser_name'],
				$device['browser_version'],
				$device['os_name']
			);
		} catch ( \Exception $e ) {
			self::log_error( 'Device', $e );
		}

		// UTM parse-only — must run BEFORE referrer classification so
		// SourceDetector can use utm_medium / utm_source as overrides.
		// DB persistence happens later in persist_utm() (post-persist hook).
		try {
			ParameterService::apply_to_profile( $profile );
		} catch ( \Exception $e ) {
			self::log_error( 'UTM', $e );
		}

		// Referrer classification.
		try {
			$referrer_url = $profile->get( 'referrer', '' );
			if ( ! empty( $referrer_url ) && ! ReferrerService::is_self_referral( $referrer_url ) ) {
				$domain = ReferrerService::extract_domain( $referrer_url );

				if ( ! ReferrerService::is_spam( $domain ) ) {
					$utm_medium = $profile->get( 'utm_medium', '' );
					$source     = SourceDetector::classify( $domain, $referrer_url, $utm_medium );
					$profile->with_referrer_data( $source['channel'], $source['name'], $domain );
				}
			} else {
				// No HTTP referrer (or self-referral). If the URL carried UTM
				// tags, surface them as the source so the visit doesn't get
				// silently bucketed as Direct in the All Sources report.
				$utm_source = (string) $profile->get( 'utm_source', '' );
				$utm_medium = (string) $profile->get( 'utm_medium', '' );
				if ( '' !== $utm_source || '' !== $utm_medium ) {
					$source = SourceDetector::classify( strtolower( $utm_source ), '', $utm_medium );
					$profile->with_referrer_data(
						$source['channel'],
						'' !== $source['name'] ? $source['name'] : $utm_source,
						$utm_source
					);
				} else {
					$profile->with_referrer_data( 'Direct', '', '' );
				}
			}
		} catch ( \Exception $e ) {
			self::log_error( 'Referrer', $e );
		}
	}

	/**
	 * Persist UTM parameters into the parameters table.
	 *
	 * Hooked into 'statnive_profile_persisted' — runs after Visitor → Session →
	 * View have been recorded so `session_id` / `view_id` are available.
	 *
	 * @param VisitorProfile $profile The persisted profile.
	 */
	public static function persist_utm( VisitorProfile $profile ): void {
		try {
			ParameterService::record( $profile );
		} catch ( \Exception $e ) {
			self::log_error( 'UTM', $e );
		}
	}

	/**
	 * Log an enrichment error without blocking hit recording.
	 *
	 * @param string     $service Service name.
	 * @param \Exception $error   The exception.
	 */
	private static function log_error( string $service, \Exception $error ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[Statnive][%s] Enrichment failed: %s', $service, $error->getMessage() ) );
		}
	}
}
