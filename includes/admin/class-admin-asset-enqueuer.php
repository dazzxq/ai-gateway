<?php
/**
 * Admin Asset Enqueuer Class
 *
 * Handles enqueuing of admin dashboard scripts and styles.
 *
 * @package AI_Gateway\Admin
 */

namespace AI_Gateway\Admin;

/**
 * AdminAssetEnqueuer class
 *
 * Registers and enqueues admin dashboard assets.
 */
class Admin_Asset_Enqueuer {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only enqueue on AI Gateway admin page.
		if ( ! $this->is_ai_gateway_page() ) {
			return;
		}

		$plugin_url = plugins_url( '', __DIR__ . '/../../ai-gateway-plugin.php' );

		// Enqueue jQuery (already included in WordPress).
		wp_enqueue_script( 'jquery' );

		// Enqueue admin dashboard script.
		wp_enqueue_script(
			'ai-gateway-admin-dashboard',
			$plugin_url . '/assets/admin-dashboard.js',
			array( 'jquery' ),
			'0.3.0',
			true
		);

		// Enqueue WordPress dashicons.
		wp_enqueue_style( 'dashicons' );

		// Localize script with data for JavaScript.
		wp_localize_script(
			'ai-gateway-admin-dashboard',
			'aiGatewayConfig',
			array(
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'plugin_url'     => $plugin_url,
				'api_root'       => esc_url_raw( rest_url( 'ai-gateway/v1' ) ),
				'admin_page_url' => esc_url_raw( admin_url( 'admin.php?page=ai-gateway' ) ),
			)
		);
	}

	/**
	 * Enqueue admin styles
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		// Only enqueue on AI Gateway admin page.
		if ( ! $this->is_ai_gateway_page() ) {
			return;
		}

		$plugin_url = plugins_url( '', __DIR__ . '/../../ai-gateway-plugin.php' );

		// Enqueue admin dashboard styles.
		wp_enqueue_style(
			'ai-gateway-admin-dashboard',
			$plugin_url . '/assets/admin-dashboard.css',
			array(),
			'0.3.0'
		);
	}

	/**
	 * Check if current page is AI Gateway admin page
	 *
	 * @return bool
	 */
	private function is_ai_gateway_page() {
		global $pagenow;

		if ( ! is_admin() ) {
			return false;
		}

		if ( 'admin.php' !== $pagenow ) {
			return false;
		}

		if ( empty( $_GET['page'] ) || 'ai-gateway' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		return true;
	}
}
