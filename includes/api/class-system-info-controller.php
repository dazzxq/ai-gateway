<?php
/**
 * System Info Controller Class
 *
 * REST endpoints for system information.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Observability\SystemInfoGetter;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * SystemInfoController
 *
 * Handles REST endpoint for system information.
 */
class SystemInfoController {
	/**
	 * System Info Getter instance.
	 *
	 * @var SystemInfoGetter
	 */
	private $system_info_getter;

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
	private $audit_logger;

	/**
	 * Constructor
	 *
	 * @param SystemInfoGetter  $system_info_getter System Info Getter instance.
	 * @param PermissionChecker $permission_checker Permission Checker instance.
	 * @param AuditLogger       $audit_logger       Audit Logger instance.
	 */
	public function __construct( SystemInfoGetter $system_info_getter, PermissionChecker $permission_checker, AuditLogger $audit_logger ) {
		$this->system_info_getter = $system_info_getter;
		$this->permission_checker = $permission_checker;
		$this->audit_logger       = $audit_logger;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'ai-gateway/v1',
			'/system-info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_system_info_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);
	}

	/**
	 * GET /system-info - Get system information.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_system_info_callback( WP_REST_Request $request ) {
		$info = $this->system_info_getter->get_system_info();

		return ResponseFormatter::success( $info, 200 );
	}
}
