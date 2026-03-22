<?php
/**
 * Admin Dashboard Main Class
 *
 * Registers WordPress admin menu and manages dashboard lifecycle.
 *
 * @package AI_Gateway\Admin
 */

namespace AI_Gateway\Admin;

use AI_Gateway\ContentManagement\AuditLogger;

/**
 * AdminDashboard class
 *
 * Handles menu registration, page rendering, and nonce management.
 */
class Admin_Dashboard {

	/**
	 * Audit logger instance
	 *
	 * @var AuditLogger
	 */
	private $audit_logger;

	/**
	 * Constructor
	 *
	 * @param AuditLogger $audit_logger Audit logger instance.
	 */
	public function __construct( AuditLogger $audit_logger ) {
		$this->audit_logger = $audit_logger;
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register admin menu
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'AI Gateway Dashboard', 'ai-gateway' ),
			__( 'AI Gateway', 'ai-gateway' ),
			'manage_options',
			'ai-gateway',
			array( $this, 'render_page' ),
			'dashicons-api',
			90
		);
	}

	/**
	 * Render dashboard page
	 *
	 * @return void
	 */
	public function render_page() {
		// Security check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-gateway' ) );
		}

		// Load the main dashboard template.
		$plugin_url     = plugins_url( '', __DIR__ . '/../../ai-gateway-plugin.php' );
		$plugin_version = defined( 'AI_GATEWAY_VERSION' ) ? AI_GATEWAY_VERSION : '0.0.0';

		// Include template with variables in scope.
		$template_path = dirname( __DIR__, 2 ) . '/templates/admin/dashboard.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="wrap"><h1>AI Gateway Dashboard</h1><p>Template not found: ' . esc_html( $template_path ) . '</p></div>';
		}

		// Log dashboard access.
		$this->audit_logger->log_action(
			'dashboard_access',
			'/admin/dashboard',
			'GET',
			200
		);
	}
}
