<?php
/**
 * System Info Getter Class
 *
 * Gathers system and WordPress environment information.
 *
 * @package AI_Gateway\Observability
 */

namespace AI_Gateway\Observability;

/**
 * SystemInfoGetter
 *
 * Provides methods to retrieve system and WordPress version information.
 */
class SystemInfoGetter {
	/**
	 * Get system information including WordPress, PHP, and theme versions.
	 *
	 * @return array Array of system information.
	 */
	public function get_system_info() {
		global $wpdb;

		$active_theme = wp_get_theme();

		return array(
			'wordpress_version'    => get_bloginfo( 'version' ),
			'php_version'          => phpversion(),
			'server_software'      => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
			'active_theme'         => $active_theme->get( 'Name' ),
			'theme_version'        => $active_theme->get( 'Version' ) ? $active_theme->get( 'Version' ) : 'Unknown',
			'mysql_version'        => method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : 'Unknown',
			'active_plugins_count' => count( get_option( 'active_plugins', array() ) ),
			'debug_enabled'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'gateway_version'      => defined( 'AI_GATEWAY_VERSION' ) ? AI_GATEWAY_VERSION : '1.0.0',
		);
	}
}
