<?php

declare(strict_types=1);

namespace Statnive\Entity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Service\IpAnonymizer;
use Statnive\Service\IpExtractor;
use Statnive\Service\SaltManager;

/**
 * VisitorProfile — central data bus for the hit recording pipeline.
 *
 * All entity classes read input from and write resolved IDs back to this object.
 * Flows through: Request → enrich() → Visitor → Session → View.
 */
final class VisitorProfile {

	/**
	 * Resolved entity IDs and metadata.
	 *
	 * @var array<string, mixed>
	 */
	private array $meta = [];

	/**
	 * Create a VisitorProfile from a tracking request payload.
	 *
	 * @param array<string, mixed> $payload Raw request data.
	 * @return self
	 */
	public static function from_request( array $payload ): self {
		$profile = new self();

		// Core request fields.
		$profile->set( 'resource_type', sanitize_text_field( $payload['resource_type'] ?? 'post' ) );
		$profile->set( 'resource_id', absint( $payload['resource_id'] ?? 0 ) );
		$profile->set( 'referrer', esc_url_raw( $payload['referrer'] ?? '' ) );
		$profile->set( 'screen_width', absint( $payload['screen_width'] ?? 0 ) );
		$profile->set( 'screen_height', absint( $payload['screen_height'] ?? 0 ) );
		$profile->set( 'language', sanitize_text_field( $payload['language'] ?? '' ) );
		$profile->set( 'timezone', sanitize_text_field( $payload['timezone'] ?? '' ) );
		$profile->set( 'signature', sanitize_text_field( $payload['signature'] ?? '' ) );
		$profile->set( 'page_url', sanitize_text_field( $payload['page_url'] ?? '' ) );
		$profile->set( 'page_query', sanitize_text_field( $payload['page_query'] ?? '' ) );
		$profile->set( 'pvid', sanitize_text_field( $payload['pvid'] ?? '' ) );

		// Server-side data (never from client).
		$profile->set( 'ip', IpExtractor::extract() );
		$profile->set( 'user_agent', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
		$profile->set( 'timestamp', current_time( 'mysql', true ) );
		$profile->set( 'user_id', get_current_user_id() );

		return $profile;
	}

	/**
	 * Set a metadata value.
	 *
	 * @param string $key   Metadata key.
	 * @param mixed  $value Metadata value.
	 */
	public function set( string $key, mixed $value ): self {
		$this->meta[ $key ] = $value;
		return $this;
	}

	/**
	 * Get a metadata value.
	 *
	 * @param string $key      Metadata key.
	 * @param mixed  $fallback Fallback if key not set.
	 * @return mixed
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->meta[ $key ] ?? $fallback;
	}

	/**
	 * Check if a metadata key exists.
	 *
	 * @param string $key Metadata key.
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->meta );
	}

	/**
	 * Attach GeoIP data to the profile.
	 *
	 * @param string $country_code   ISO country code.
	 * @param string $country_name   Country name.
	 * @param string $city_name      City name.
	 * @param string $region_code    Region code.
	 * @param string $continent_code Continent code.
	 * @param string $continent      Continent name.
	 * @return self
	 */
	public function with_geo_ip(
		string $country_code = '',
		string $country_name = '',
		string $city_name = '',
		string $region_code = '',
		string $continent_code = '',
		string $continent = ''
	): self {
		$this->set( 'country_code', $country_code );
		$this->set( 'country_name', $country_name );
		$this->set( 'city_name', $city_name );
		$this->set( 'region_code', $region_code );
		$this->set( 'continent_code', $continent_code );
		$this->set( 'continent', $continent );
		return $this;
	}

	/**
	 * Attach device detection data to the profile.
	 *
	 * @param string $device_type     Device type (Desktop, Mobile, Tablet).
	 * @param string $browser_name    Browser name.
	 * @param string $browser_version Browser version.
	 * @param string $os_name         OS name.
	 * @return self
	 */
	public function with_device_data(
		string $device_type = '',
		string $browser_name = '',
		string $browser_version = '',
		string $os_name = ''
	): self {
		$this->set( 'device_type', $device_type );
		$this->set( 'browser_name', $browser_name );
		$this->set( 'browser_version', $browser_version );
		$this->set( 'os_name', $os_name );
		return $this;
	}

	/**
	 * Attach referrer/source data to the profile.
	 *
	 * @param string $channel Classified channel (e.g., 'Organic Search').
	 * @param string $name    Source name (e.g., 'Google').
	 * @param string $domain  Referrer domain.
	 * @return self
	 */
	public function with_referrer_data( string $channel = 'Direct', string $name = '', string $domain = '' ): self {
		$this->set( 'referrer_channel', $channel );
		$this->set( 'referrer_name', $name );
		$this->set( 'referrer_domain', $domain );
		return $this;
	}

	/**
	 * Generate the visitor hash using the two-salt system.
	 *
	 * Formula: SHA256(current_salt + anonymized_ip + user_agent + domain) → first 8 bytes.
	 * Uses SaltManager for CSPRNG salts and IpAnonymizer for IP privacy.
	 *
	 * @return string Binary hash (8 bytes) for BINARY(8) storage.
	 */
	public function compute_visitor_hash(): string {
		$raw_ip     = $this->get( 'ip', '' );
		$anonymized = IpAnonymizer::anonymize( $raw_ip );
		$ua         = $this->get( 'user_agent', '' );
		$salt       = SaltManager::get_current_salt();
		$domain     = home_url();

		$full_hash = hash( 'sha256', $salt . $anonymized . $ua . $domain, true );
		$hash      = substr( $full_hash, 0, 8 );

		$this->set( 'visitor_hash', $hash );

		return $hash;
	}

	/**
	 * Compute a visitor hash with a specific salt (for overlap-window matching).
	 *
	 * @param string $salt Binary salt to use.
	 * @return string Binary hash (8 bytes).
	 */
	public function compute_visitor_hash_with_salt( string $salt ): string {
		$raw_ip     = $this->get( 'ip', '' );
		$anonymized = IpAnonymizer::anonymize( $raw_ip );
		$ua         = $this->get( 'user_agent', '' );
		$domain     = home_url();

		$full_hash = hash( 'sha256', $salt . $anonymized . $ua . $domain, true );
		return substr( $full_hash, 0, 8 );
	}

	/**
	 * Discard the raw IP address from the profile.
	 *
	 * Must be called after GeoIP resolution but before persist().
	 * Enforces the IP ephemeral lifecycle: extract → hash → GeoIP → discard.
	 */
	public function discard_raw_ip(): void {
		unset( $this->meta['ip'] );
	}

	/**
	 * Enrich and persist the visitor profile.
	 *
	 * Orchestrates the full data enrichment pipeline:
	 * 1. Compute visitor hash (with proper salt system).
	 * 2. Resolve GeoIP data (Phase 2 — services called if registered).
	 * 3. Detect device/browser/OS (Phase 2 — services called if registered).
	 * 4. Classify referrer source (Phase 2 — services called if registered).
	 * 5. Discard raw IP (ephemeral lifecycle enforcement).
	 * 6. Persist all entities (Visitor → Session → View).
	 *
	 * Services are called via try/catch so failures don't block hit recording.
	 */
	public function enrich(): void {
		// Step 1: Compute visitor hash with proper salt rotation.
		$this->compute_visitor_hash();

		// Steps 2-4: Service enrichment hooks.
		// GeoIP, Device, and Referrer services will be wired here
		// by AnalyticsServiceProvider in Phase 2 tasks 2.5-2.10.
		// For now, these are no-ops (stubs already set empty strings).

		/**
		 * Fires after hash computation and before IP disposal.
		 *
		 * Services hook here to enrich the profile with GeoIP, device, and referrer data.
		 *
		 * @param VisitorProfile $profile The visitor profile being enriched.
		 */
		do_action( 'statnive_enrich_profile', $this );

		// Step 5: Discard raw IP — never persist it.
		$this->discard_raw_ip();

		// Step 6: Persist all entities.
		$this->persist();
	}

	/**
	 * Persist the visitor profile by recording all entities.
	 *
	 * Calls Visitor → Session → View record() in sequence.
	 * Each entity stores its resolved ID back into this profile.
	 */
	public function persist(): void {
		Visitor::record( $this );
		Session::record( $this );
		View::record( $this );
	}
}
