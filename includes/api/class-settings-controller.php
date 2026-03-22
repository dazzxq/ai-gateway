<?php
/**
 * Settings Controller Class
 *
 * REST endpoints for WordPress settings.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\OptionsManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * SettingsController
 *
 * Handles REST endpoint for reading safe WordPress settings.
 */
class SettingsController {
	/**
	 * Options Manager instance.
	 *
	 * @var OptionsManager
	 */
	private $options_manager;

	/**
	 * Permission Checker instance.
	 *
	 * @var PermissionChecker
	 */
	private $permission_checker;

	/**
	 * Audit Logger instance.
	 *
	 * @var AuditLogger
	 */
	private $audit_logger; // @phpstan-ignore property.onlyWritten

	/**
	 * Constructor
	 *
	 * @param OptionsManager    $options_manager    Options Manager instance.
	 * @param PermissionChecker $permission_checker Permission Checker instance.
	 * @param AuditLogger       $audit_logger       Audit Logger instance.
	 */
	public function __construct( OptionsManager $options_manager, PermissionChecker $permission_checker, AuditLogger $audit_logger ) {
		$this->options_manager    = $options_manager;
		$this->permission_checker = $permission_checker;
		$this->audit_logger       = $audit_logger;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /settings - Read safe WordPress settings.
		register_rest_route(
			'ai-gateway/v1',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'read_settings_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);
	}

	/**
	 * GET /settings - Read safe WordPress settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function read_settings_callback( WP_REST_Request $request ) {
		$settings = $this->options_manager->get_safe_options();

		return ResponseFormatter::success( $settings, 200 );
	}
}
