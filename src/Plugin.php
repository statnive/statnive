<?php

declare(strict_types=1);

namespace Statnive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Container\AdminServiceProvider;
use Statnive\Container\AnalyticsServiceProvider;
use Statnive\Container\CoreServiceProvider;
use Statnive\Container\PrivacyServiceProvider;
use Statnive\Container\ServiceContainer;
use Statnive\Container\ServiceProvider;
use Statnive\Cron\CronRegistrar;
use Statnive\Database\DatabaseFactory;
use Statnive\Database\Migrator;

/**
 * Plugin bootstrap class.
 *
 * Handles plugin initialization, activation, and deactivation.
 */
final class Plugin {

	/**
	 * Whether the plugin has been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Service providers to register.
	 *
	 * @var array<class-string<ServiceProvider>>
	 */
	private static array $providers = [
		CoreServiceProvider::class,
		AnalyticsServiceProvider::class,
		PrivacyServiceProvider::class,
		AdminServiceProvider::class,
	];

	/**
	 * Initialize the plugin.
	 *
	 * Creates the service container, registers providers, and boots services.
	 * Safe to call multiple times — only runs once.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		// WordPress auto-loads translations for wp.org-hosted plugins since 4.6.
		// load_plugin_textdomain() is no longer needed and triggers a PCP warning.

		// Database schema migrations (runs on plugins_loaded, bails fast when nothing pending).
		Migrator::init();

		// WP-CLI commands (loaded only when WP-CLI is the SAPI).
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'statnive cron', \Statnive\Cli\CronCommand::class );
		}

		self::register_hooks();
		self::boot_container();
	}

	/**
	 * Get the service container instance.
	 */
	public static function container(): ServiceContainer {
		return ServiceContainer::get_instance();
	}

	/**
	 * Boot the service container and register all providers.
	 */
	private static function boot_container(): void {
		$container = ServiceContainer::get_instance();

		// Phase 1: Register all service factories.
		$instances = [];
		foreach ( self::$providers as $provider_class ) {
			$provider = new $provider_class();
			$provider->register( $container );
			$instances[] = $provider;
		}

		// Phase 2: Boot all providers (hooks into WordPress).
		foreach ( $instances as $provider ) {
			$provider->boot( $container );
		}
	}

	/**
	 * Register activation and deactivation hooks.
	 */
	private static function register_hooks(): void {
		register_activation_hook( STATNIVE_FILE, [ self::class, 'activate' ] );
		register_deactivation_hook( STATNIVE_FILE, [ self::class, 'deactivate' ] );
	}

	/**
	 * Plugin activation callback.
	 *
	 * Runs version checks and sets default options.
	 */
	public static function activate(): void {
		// Defensive capability check for web-request activations (WordPress core
		// also enforces this on the wp-admin Plugins screen). Skipped under
		// WP-CLI / WP_CLI test runs because there is no current user in those
		// contexts and the operator is already trusted by definition — the
		// official Plugin Check Action triggers exactly this path.
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'activate_plugins' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to activate plugins.', 'statnive' ),
				'Plugin Activation Error',
				[ 'back_link' => true ]
			);
		}

		if ( version_compare( PHP_VERSION, STATNIVE_MIN_PHP, '<' ) ) {
			deactivate_plugins( plugin_basename( STATNIVE_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'Statnive requires PHP %1$s or higher. You are running PHP %2$s.', 'statnive' ),
						STATNIVE_MIN_PHP,
						PHP_VERSION
					)
				),
				'Plugin Activation Error',
				[ 'back_link' => true ]
			);
		}

		if ( version_compare( get_bloginfo( 'version' ), STATNIVE_MIN_WP, '<' ) ) {
			deactivate_plugins( plugin_basename( STATNIVE_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Required WordPress version, 2: Current WordPress version */
						__( 'Statnive requires WordPress %1$s or higher. You are running WordPress %2$s.', 'statnive' ),
						STATNIVE_MIN_WP,
						get_bloginfo( 'version' )
					)
				),
				'Plugin Activation Error',
				[ 'back_link' => true ]
			);
		}

		// Set default options.
		// Options read on every frontend page keep autoload=yes (default).
		// Admin-only / migration-only options set autoload=false to avoid bloating alloptions.
		add_option( 'statnive_version', STATNIVE_VERSION, '', false );
		add_option( 'statnive_respect_dnt', true );
		add_option( 'statnive_respect_gpc', true );
		add_option( 'statnive_tracking_enabled', true );
		add_option( 'statnive_geoip_enabled', false, '', false );

		// Create database tables.
		DatabaseFactory::create_tables();
		update_option( 'statnive_db_version', STATNIVE_VERSION, false );

		// Schedule cron jobs (aggregation, salt rotation, data purge, etc.).
		CronRegistrar::register_all();

		// Flush rewrite rules for REST API endpoints.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * Performs cleanup without deleting data.
	 * Data removal happens only on uninstall (uninstall.php).
	 */
	public static function deactivate(): void {
		// Remove all scheduled cron events.
		CronRegistrar::deregister_all();

		flush_rewrite_rules();
	}
}
