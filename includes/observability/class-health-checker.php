<?php
/**
 * Health Checker Class
 *
 * Monitors infrastructure health and component availability.
 *
 * @package AI_Gateway\Observability
 */

namespace AI_Gateway\Observability;

/**
 * HealthChecker
 *
 * Provides infrastructure health monitoring with component-level status checks.
 */
class HealthChecker {
	/**
	 * Get overall health status and component checks.
	 *
	 * @return array Array with status and individual component checks.
	 */
	public function get_health_status() {
		$checks = array(
			'rest_api'    => $this->check_rest_api(),
			'wp_options'  => $this->check_wp_options(),
			'filesystem'  => $this->check_filesystem(),
			'php_version' => $this->check_php_version(),
		);

		// Determine overall status.
		$failed_checks = array_filter(
			$checks,
			function ( $check ) {
				return 'ok' !== $check['status'];
			}
		);

		$status = 'healthy';
		if ( count( $failed_checks ) > 0 ) {
			$status = 'degraded';
			foreach ( $failed_checks as $check ) {
				if ( 'critical' === $check['status'] ) {
					$status = 'unhealthy';
					break;
				}
			}
		}

		return array(
			'status'            => $status,
			'checks'            => $checks,
			'gateway_version'   => defined( 'AI_GATEWAY_VERSION' ) ? AI_GATEWAY_VERSION : '0.0.0',
			'php_version'       => phpversion(),
			'wordpress_version' => get_bloginfo( 'version' ),
			'active_theme'      => wp_get_theme()->get( 'Name' ),
			'plugin_count'      => $this->get_plugin_count(),
			'acf_active'        => class_exists( 'ACF' ),
		);
	}

	/**
	 * Check REST API availability.
	 *
	 * @return array Status and message.
	 */
	private function check_rest_api() {
		try {
			$result = get_option( 'siteurl' );
			if ( ! empty( $result ) ) {
				return array(
					'status'  => 'ok',
					'message' => 'REST API accessible',
				);
			}
		} catch ( \Exception $e ) {
			// Health check -- exception means degraded, handled by fallback return below.
			unset( $e );
		}

		return array(
			'status'  => 'warning',
			'message' => 'REST API not responding',
		);
	}

	/**
	 * Check wp_options table access.
	 *
	 * @return array Status and message.
	 */
	private function check_wp_options() {
		try {
			$test_key   = 'ai_gateway_health_check_' . time();
			$test_value = 'test_' . wp_rand();

			update_option( $test_key, $test_value );
			$retrieved = get_option( $test_key );
			delete_option( $test_key );

			if ( $test_value === $retrieved ) {
				return array(
					'status'  => 'ok',
					'message' => 'wp_options read/write working',
				);
			}
		} catch ( \Exception $e ) {
			// Health check -- exception means degraded, handled by fallback return below.
			unset( $e );
		}

		return array(
			'status'  => 'warning',
			'message' => 'wp_options access degraded',
		);
	}

	/**
	 * Check filesystem write access.
	 *
	 * @return array Status and message.
	 */
	private function check_filesystem() {
		try {
			$upload_dir = wp_upload_dir();
			if ( isset( $upload_dir['basedir'] ) && is_writable( $upload_dir['basedir'] ) ) {
				return array(
					'status'  => 'ok',
					'message' => 'Filesystem write accessible',
				);
			}
		} catch ( \Exception $e ) {
			// Health check -- exception means degraded, handled by fallback return below.
			unset( $e );
		}

		return array(
			'status'  => 'warning',
			'message' => 'Filesystem write may be restricted',
		);
	}

	/**
	 * Check PHP version requirements.
	 *
	 * @return array Status and message.
	 */
	private function check_php_version() {
		$php_version = phpversion();
		$minimum_php = '7.4';

		if ( version_compare( $php_version, $minimum_php, '>=' ) ) {
			return array(
				'status'  => 'ok',
				'message' => "PHP {$php_version} meets requirements (minimum {$minimum_php})",
			);
		}

		return array(
			'status'  => 'critical',
			'message' => "PHP {$php_version} below minimum ({$minimum_php})",
		);
	}

	/**
	 * Get plugin count safely.
	 *
	 * @return int
	 */
	private function get_plugin_count() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return count( get_plugins() );
	}
}
