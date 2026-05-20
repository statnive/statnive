<?php

declare(strict_types=1);

namespace Statnive\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database schema definition for all Statnive tables.
 *
 * Star schema design with sessions as the central hub.
 * All definitions follow strict dbDelta formatting rules:
 * - Lowercase type keywords.
 * - Two leading spaces before the primary-key declaration.
 * - One column per line.
 * - No IF NOT EXISTS.
 * - No foreign key constraints (D-07: improves INSERT performance).
 */
final class SchemaDefinition {

	/**
	 * Get all table definitions as dbDelta-compatible SQL.
	 *
	 * @return string Complete SQL for all 26 tables.
	 */
	public static function get_sql(): string {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'statnive_';

		$sql = '';

		// 1. Visitors — root entity with privacy-safe hash.
		$sql .= "CREATE TABLE {$prefix}visitors (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  hash binary(8) NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (ID),
  KEY idx_hash (hash)
) {$charset_collate};\n\n";

		// 2. Sessions — central hub linking all dimensions.
		$sql .= "CREATE TABLE {$prefix}sessions (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  visitor_id bigint(20) unsigned DEFAULT NULL,
  ip_hash binary(8) DEFAULT NULL,
  referrer_id bigint(20) unsigned DEFAULT NULL,
  country_id bigint(20) unsigned DEFAULT NULL,
  city_id bigint(20) unsigned DEFAULT NULL,
  initial_view_id bigint(20) unsigned DEFAULT NULL,
  last_view_id bigint(20) unsigned DEFAULT NULL,
  total_views int(11) unsigned NOT NULL DEFAULT 0,
  device_type_id bigint(20) unsigned DEFAULT NULL,
  device_os_id bigint(20) unsigned DEFAULT NULL,
  device_browser_id bigint(20) unsigned DEFAULT NULL,
  device_browser_version_id bigint(20) unsigned DEFAULT NULL,
  timezone_id bigint(20) unsigned DEFAULT NULL,
  language_id bigint(20) unsigned DEFAULT NULL,
  resolution_id bigint(20) unsigned DEFAULT NULL,
  user_id bigint(20) unsigned DEFAULT NULL,
  started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at datetime DEFAULT NULL,
  duration int(11) unsigned DEFAULT 0,
  PRIMARY KEY  (ID),
  KEY idx_visitor_id (visitor_id),
  KEY idx_started_at (started_at),
  KEY idx_started_visitor (started_at, visitor_id),
  KEY idx_started_referrer (started_at, referrer_id),
  KEY idx_started_country (started_at, country_id),
  KEY idx_started_city (started_at, city_id),
  KEY idx_started_device_type (started_at, device_type_id),
  KEY idx_started_device_browser (started_at, device_browser_id),
  KEY idx_started_device_os (started_at, device_os_id),
  KEY idx_started_resolution (started_at, resolution_id),
  KEY idx_started_language (started_at, language_id),
  KEY idx_started_timezone (started_at, timezone_id),
  KEY idx_started_user (started_at, user_id),
  KEY idx_analytics_visitor_date (visitor_id, started_at),
  KEY idx_ip_hash (ip_hash)
) {$charset_collate};\n\n";

		// 3. Views — individual page views within a session.
		$sql .= "CREATE TABLE {$prefix}views (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL,
  resource_uri_id bigint(20) unsigned DEFAULT NULL,
  resource_id bigint(20) unsigned DEFAULT NULL,
  viewed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  next_view_id bigint(20) unsigned DEFAULT NULL,
  duration int(11) unsigned DEFAULT 0,
  scroll_depth tinyint(3) unsigned DEFAULT 0,
  pvid char(16) DEFAULT NULL,
  PRIMARY KEY  (ID),
  KEY idx_session_id (session_id),
  KEY idx_resource_uri_id (resource_uri_id),
  KEY idx_viewed_at (viewed_at),
  KEY idx_viewed_session (viewed_at, session_id),
  KEY idx_viewed_resource (viewed_at, resource_uri_id),
  KEY idx_pvid (pvid)
) {$charset_collate};\n\n";

		// 4. Resources — content metadata cache.
		$sql .= "CREATE TABLE {$prefix}resources (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  resource_type varchar(20) NOT NULL DEFAULT 'post',
  resource_id bigint(20) unsigned NOT NULL DEFAULT 0,
  cached_title varchar(255) DEFAULT NULL,
  cached_terms text DEFAULT NULL,
  cached_author_id bigint(20) unsigned DEFAULT NULL,
  cached_date datetime DEFAULT NULL,
  resource_meta text DEFAULT NULL,
  language varchar(10) DEFAULT NULL,
  is_deleted tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  KEY idx_resource_type_id (resource_type, resource_id),
  KEY idx_is_deleted (is_deleted)
) {$charset_collate};\n\n";

		// 5. Resource URIs — URI-to-resource mapping with CRC32 dedup.
		$sql .= "CREATE TABLE {$prefix}resource_uris (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  resource_id bigint(20) unsigned DEFAULT NULL,
  uri varchar(255) NOT NULL,
  uri_hash int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  KEY idx_resource_id (resource_id),
  KEY idx_uri_hash (uri_hash)
) {$charset_collate};\n\n";

		// 6. Parameters — UTM and query string parameters per view.
		$sql .= "CREATE TABLE {$prefix}parameters (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL,
  resource_uri_id bigint(20) unsigned DEFAULT NULL,
  view_id bigint(20) unsigned DEFAULT NULL,
  param_key varchar(100) NOT NULL,
  param_value varchar(255) DEFAULT NULL,
  PRIMARY KEY  (ID),
  KEY idx_session_id (session_id),
  KEY idx_view_id (view_id),
  KEY idx_param_key (param_key)
) {$charset_collate};\n\n";

		// 7. Countries — ISO code dimension.
		$sql .= "CREATE TABLE {$prefix}countries (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code char(2) NOT NULL,
  name varchar(100) NOT NULL,
  continent_code char(2) DEFAULT NULL,
  continent varchar(50) DEFAULT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_code (code)
) {$charset_collate};\n\n";

		// 8. Cities — hierarchical geography dimension.
		$sql .= "CREATE TABLE {$prefix}cities (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  country_id bigint(20) unsigned NOT NULL,
  region_code varchar(10) DEFAULT NULL,
  region_name varchar(100) DEFAULT NULL,
  city_name varchar(100) NOT NULL,
  PRIMARY KEY  (ID),
  KEY idx_country_id (country_id),
  KEY idx_city_name (city_name)
) {$charset_collate};\n\n";

		// 9. Device types — Desktop/Mobile/Tablet dimension.
		$sql .= "CREATE TABLE {$prefix}device_types (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(50) NOT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_name (name)
) {$charset_collate};\n\n";

		// 10. Device browsers — browser name dimension.
		$sql .= "CREATE TABLE {$prefix}device_browsers (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_name (name)
) {$charset_collate};\n\n";

		// 11. Device browser versions — version per browser.
		$sql .= "CREATE TABLE {$prefix}device_browser_versions (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  browser_id bigint(20) unsigned NOT NULL,
  version varchar(50) NOT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_browser_version (browser_id, version)
) {$charset_collate};\n\n";

		// 12. Device operating systems — OS name dimension.
		$sql .= "CREATE TABLE {$prefix}device_oss (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_name (name)
) {$charset_collate};\n\n";

		// 13. Screen resolutions — width/height dimension.
		$sql .= "CREATE TABLE {$prefix}resolutions (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  width smallint(5) unsigned NOT NULL DEFAULT 0,
  height smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_dimensions (width, height)
) {$charset_collate};\n\n";

		// 14. Languages — locale dimension.
		$sql .= "CREATE TABLE {$prefix}languages (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(10) NOT NULL,
  name varchar(100) DEFAULT NULL,
  region varchar(50) DEFAULT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_code (code)
) {$charset_collate};\n\n";

		// 15. Timezones — timezone dimension.
		$sql .= "CREATE TABLE {$prefix}timezones (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(50) NOT NULL,
  utc_offset varchar(10) DEFAULT NULL,
  is_dst tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_name (name)
) {$charset_collate};\n\n";

		// 16. Referrers — channel-grouped traffic source dimension.
		$sql .= "CREATE TABLE {$prefix}referrers (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  channel varchar(50) NOT NULL DEFAULT 'Direct',
  name varchar(255) DEFAULT NULL,
  domain varchar(255) DEFAULT NULL,
  domain_hash int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  KEY idx_channel (channel),
  KEY idx_domain_hash (domain_hash)
) {$charset_collate};\n\n";

		// 17. Summary — pre-aggregated daily metrics per resource URI.
		$sql .= "CREATE TABLE {$prefix}summary (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  date date NOT NULL,
  resource_uri_id bigint(20) unsigned NOT NULL,
  visitors int(11) unsigned NOT NULL DEFAULT 0,
  sessions int(11) unsigned NOT NULL DEFAULT 0,
  views int(11) unsigned NOT NULL DEFAULT 0,
  total_duration int(11) unsigned NOT NULL DEFAULT 0,
  bounces int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_date_uri (date, resource_uri_id),
  KEY idx_date (date),
  KEY idx_resource_uri_id (resource_uri_id)
) {$charset_collate};\n\n";

		// 18. Summary totals — site-wide daily aggregates.
		$sql .= "CREATE TABLE {$prefix}summary_totals (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  date date NOT NULL,
  visitors int(11) unsigned NOT NULL DEFAULT 0,
  sessions int(11) unsigned NOT NULL DEFAULT 0,
  views int(11) unsigned NOT NULL DEFAULT 0,
  total_duration int(11) unsigned NOT NULL DEFAULT 0,
  bounces int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_date (date)
) {$charset_collate};\n\n";

		// 19. Events — custom event tracking.
		$sql .= "CREATE TABLE {$prefix}events (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned DEFAULT NULL,
  resource_uri_id bigint(20) unsigned DEFAULT NULL,
  user_id bigint(20) unsigned DEFAULT NULL,
  event_name varchar(100) NOT NULL,
  event_data text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (ID),
  KEY idx_session_event (session_id, event_name),
  KEY idx_event_name (event_name),
  KEY idx_created_at (created_at)
) {$charset_collate};\n\n";

		// 20. Exclusions — exclusion logging for bots, roles, etc.
		$sql .= "CREATE TABLE {$prefix}exclusions (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  date date NOT NULL,
  reason varchar(50) NOT NULL,
  count int(11) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY  (ID),
  KEY idx_date (date),
  KEY idx_reason (reason)
) {$charset_collate};\n\n";

		// 21. Reports — saved report/dashboard configurations.
		$sql .= "CREATE TABLE {$prefix}reports (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_by bigint(20) unsigned DEFAULT NULL,
  title varchar(255) NOT NULL,
  description text DEFAULT NULL,
  filters text DEFAULT NULL,
  widgets text DEFAULT NULL,
  access_level varchar(50) NOT NULL DEFAULT 'private',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (ID),
  KEY idx_created_by (created_by)
) {$charset_collate};\n\n";

		// 22. Orders — one row per WooCommerce order. Idempotency on wc_order_id.
		$sql .= "CREATE TABLE {$prefix}orders (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  wc_order_id bigint(20) unsigned NOT NULL,
  wc_order_key varchar(64) DEFAULT NULL,
  parent_order_id bigint(20) unsigned DEFAULT NULL,
  status varchar(32) NOT NULL DEFAULT 'pending',
  currency char(10) NOT NULL DEFAULT 'USD',
  gross_total decimal(19,4) NOT NULL DEFAULT 0,
  net_total decimal(19,4) NOT NULL DEFAULT 0,
  tax_total decimal(19,4) NOT NULL DEFAULT 0,
  shipping_total decimal(19,4) NOT NULL DEFAULT 0,
  discount_total decimal(19,4) NOT NULL DEFAULT 0,
  refund_total decimal(19,4) NOT NULL DEFAULT 0,
  item_count smallint(6) NOT NULL DEFAULT 0,
  customer_email_hash char(64) DEFAULT NULL,
  customer_user_id bigint(20) unsigned DEFAULT NULL,
  is_first_purchase tinyint(1) NOT NULL DEFAULT 0,
  date_created_gmt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_paid_gmt datetime DEFAULT NULL,
  date_updated_gmt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_via varchar(32) DEFAULT NULL,
  payment_method varchar(64) DEFAULT NULL,
  deleted_at datetime DEFAULT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_wc_order_id (wc_order_id),
  KEY idx_status_created (status, date_created_gmt),
  KEY idx_status (status),
  KEY idx_customer_email_hash (customer_email_hash)
) {$charset_collate};\n\n";

		// 23. Order attribution — 1:1 with orders.
		$sql .= "CREATE TABLE {$prefix}order_attribution (
  order_id bigint(20) unsigned NOT NULL,
  source_type varchar(32) DEFAULT NULL,
  channel varchar(32) DEFAULT NULL,
  utm_source varchar(100) DEFAULT NULL,
  utm_medium varchar(100) DEFAULT NULL,
  utm_campaign varchar(100) DEFAULT NULL,
  utm_term varchar(100) DEFAULT NULL,
  utm_content varchar(100) DEFAULT NULL,
  utm_source_lower varchar(100) DEFAULT NULL,
  utm_medium_lower varchar(100) DEFAULT NULL,
  utm_campaign_lower varchar(100) DEFAULT NULL,
  referrer_host varchar(255) DEFAULT NULL,
  first_landing_path varchar(255) DEFAULT NULL,
  last_landing_path varchar(255) DEFAULT NULL,
  session_pages int(11) unsigned DEFAULT NULL,
  session_count int(11) unsigned DEFAULT NULL,
  device_type varchar(16) DEFAULT NULL,
  captured_at_gmt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (order_id),
  KEY idx_channel (channel),
  KEY idx_utm_lower (utm_source_lower, utm_medium_lower, utm_campaign_lower),
  KEY idx_first_landing (first_landing_path(64))
) {$charset_collate};\n\n";

		// 24. Order items — line items, snapshot product name + sku.
		$sql .= "CREATE TABLE {$prefix}order_items (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  wc_order_item_id bigint(20) unsigned NOT NULL,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  variation_id bigint(20) unsigned DEFAULT NULL,
  parent_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sku varchar(100) DEFAULT NULL,
  product_name varchar(255) NOT NULL DEFAULT '',
  quantity int(11) NOT NULL DEFAULT 0,
  subtotal decimal(19,4) NOT NULL DEFAULT 0,
  total decimal(19,4) NOT NULL DEFAULT 0,
  tax_total decimal(19,4) NOT NULL DEFAULT 0,
  refund_quantity int(11) NOT NULL DEFAULT 0,
  refund_amount decimal(19,4) NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_order_item (order_id, wc_order_item_id),
  KEY idx_product_id (product_id),
  KEY idx_parent_product (parent_product_id, total)
) {$charset_collate};\n\n";

		// 25. Order refunds — one row per refund (full or partial).
		$sql .= "CREATE TABLE {$prefix}order_refunds (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  wc_refund_id bigint(20) unsigned NOT NULL,
  amount decimal(19,4) NOT NULL DEFAULT 0,
  reason varchar(255) DEFAULT NULL,
  date_gmt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  refunded_by_user bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_wc_refund_id (wc_refund_id),
  KEY idx_order_date (order_id, date_gmt)
) {$charset_collate};\n\n";

		// 26. Order coupons — one row per coupon per order.
		$sql .= "CREATE TABLE {$prefix}order_coupons (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  coupon_id bigint(20) unsigned DEFAULT NULL,
  code varchar(100) NOT NULL DEFAULT '',
  code_lower varchar(100) NOT NULL DEFAULT '',
  discount_amount decimal(19,4) NOT NULL DEFAULT 0,
  discount_tax decimal(19,4) NOT NULL DEFAULT 0,
  PRIMARY KEY  (ID),
  UNIQUE KEY uk_order_code (order_id, code_lower),
  KEY idx_code_lower (code_lower)
) {$charset_collate};\n\n";

		return $sql;
	}
}
