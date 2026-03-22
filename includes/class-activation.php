<?php
/**
 * Activation Hook Class
 *
 * Handles plugin activation, including audit table creation
 * and versioned database migrations.
 *
 * @package AI_Gateway
 */

namespace AI_Gateway;

/**
 * Activation
 *
 * Executes on plugin activation to create tables
 * and run versioned migrations.
 */
class Activation {
	/**
	 * Activate the plugin.
	 *
	 * Runs versioned migrations and ensures audit table exists.
	 *
	 * @return void
	 */
	public static function activate() {
		$db_version = get_option( 'ai_gateway_db_version', '0' );

		// Always ensure audit table exists with current schema.
		self::create_audit_table();

		// v2.0 migration: audit schema change + legacy cleanup.
		if ( version_compare( $db_version, '2.0', '<' ) ) {
			self::migrate_v2();
			update_option( 'ai_gateway_db_version', '2.0' );
		}

		// v3.0 migration: remove Bearer auth options + dead admin settings.
		if ( version_compare( $db_version, '3.0', '<' ) ) {
			self::migrate_v3();
			update_option( 'ai_gateway_db_version', '3.0' );
		}
	}

	/**
	 * Create audit table for mutation logging.
	 *
	 * Uses WordPress dbDelta() for safe schema creation.
	 * Schema v2: user_id + username instead of api_key_hash.
	 * Idempotent: safe to call multiple times.
	 *
	 * @return void
	 */
	public static function create_audit_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'ai_gateway_audit';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			method VARCHAR(10) NOT NULL,
			endpoint VARCHAR(255) NOT NULL,
			status_code INT NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			username VARCHAR(60) NOT NULL DEFAULT '',
			resource_type VARCHAR(50) NOT NULL,
			resource_id VARCHAR(255),
			action_description VARCHAR(500),
			INDEX idx_timestamp (timestamp),
			INDEX idx_endpoint (endpoint),
			INDEX idx_method (method),
			INDEX idx_status_code (status_code),
			INDEX idx_user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * V2.0 migration: audit schema change + legacy wp_options cleanup.
	 *
	 * - Drops old audit table (user approved clearing all entries).
	 * - Recreates with new schema (user_id + username instead of api_key_hash).
	 * - Cleans up legacy wp_options (rate limit transients, api_keys metadata).
	 *
	 * @return void
	 */
	private static function migrate_v2() {
		global $wpdb;

		// 1. Drop old audit table (user approved clearing all entries).
		$table_name = $wpdb->prefix . 'ai_gateway_audit';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.

		// 2. Recreate with new schema (user_id + username instead of api_key_hash).
		self::create_audit_table();

		// 3. Clean up legacy wp_options.
		delete_option( 'ai_gateway_api_keys' );

		// 4. Clean up rate limit options (both patterns).
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ai\_gateway\_rate\_limit\_%'" // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ai\_gateway\_rate\_global\_%'" // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	/**
	 * V3.0 migration: remove Bearer auth options and dead admin settings.
	 *
	 * Bearer token auth is replaced by WP Application Password.
	 * API key hash and metadata are no longer needed.
	 * Dead admin settings (max_backups, clean_backups) are cleaned up.
	 *
	 * @return void
	 */
	private static function migrate_v3() {
		delete_option( 'ai_gateway_api_key_hash' );
		delete_option( 'ai_gateway_api_keys' );
		delete_option( 'ai_gateway_admin_setting_max_backups' );
		delete_option( 'ai_gateway_admin_setting_clean_backups' );
	}
}
