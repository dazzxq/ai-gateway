<?php
/**
 * Admin Controller Class
 *
 * Handles admin-only REST API endpoints for dashboard settings.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * AdminController
 *
 * Admin-only REST endpoints for plugin settings.
 */
class Admin_Controller {

	/**
	 * Permission Checker instance.
	 *
	 * @var PermissionChecker
	 */
	private $permission_checker;

	/**
	 * Audit logger instance
	 *
	 * @var AuditLogger
	 */
	private $audit_logger;

	/**
	 * Constructor
	 *
	 * @param PermissionChecker $permission_checker Permission Checker instance.
	 * @param AuditLogger       $audit_logger       Audit logger instance.
	 */
	public function __construct( PermissionChecker $permission_checker, AuditLogger $audit_logger ) {
		$this->permission_checker = $permission_checker;
		$this->audit_logger       = $audit_logger;
	}

	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public function register_routes() {
		$namespace = 'ai-gateway/v1';

		// GET /admin/settings - Get settings.
		register_rest_route(
			$namespace,
			'/admin/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);

		// PATCH /admin/settings - Update settings.
		register_rest_route(
			$namespace,
			'/admin/settings',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_settings_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);
	}

	/**
	 * Get settings
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_settings_callback( WP_REST_Request $request ) {
		$settings = array(
			'enable_debug_logging' => (bool) get_option( 'ai_gateway_admin_setting_debug_logging', false ),
		);

		$this->audit_logger->log_action(
			'get_admin_settings',
			'/admin/settings',
			'GET',
			200
		);

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Update settings
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function update_settings_callback( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( isset( $params['enable_debug_logging'] ) ) {
			update_option( 'ai_gateway_admin_setting_debug_logging', (bool) $params['enable_debug_logging'] );
		}

		$this->audit_logger->log_action(
			'update_admin_settings',
			'/admin/settings',
			'PATCH',
			200
		);

		return new WP_REST_Response(
			array(
				'success'  => true,
				'settings' => array(
					'enable_debug_logging' => (bool) get_option( 'ai_gateway_admin_setting_debug_logging', false ),
				),
			),
			200
		);
	}
}
