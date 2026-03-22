<?php
/**
 * Audit Controller Class
 *
 * REST endpoints for audit trail querying.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * AuditController
 *
 * Handles REST endpoint for querying mutation audit trail.
 */
class AuditController {
	/**
	 * Audit Logger instance.
	 *
	 * @var AuditLogger
	 */
	private $audit_logger;

	/**
	 * Permission Checker instance.
	 *
	 * @var PermissionChecker
	 */
	private $permission_checker;

	/**
	 * Constructor
	 *
	 * @param AuditLogger       $audit_logger       Audit Logger instance.
	 * @param PermissionChecker $permission_checker Permission Checker instance.
	 */
	public function __construct( AuditLogger $audit_logger, PermissionChecker $permission_checker ) {
		$this->audit_logger       = $audit_logger;
		$this->permission_checker = $permission_checker;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /audit - Query audit trail.
		register_rest_route(
			'ai-gateway/v1',
			'/audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'query_audit_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'method'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'endpoint'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status_code' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'date_from'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'        => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'    => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * GET /audit - Query audit trail entries.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function query_audit_callback( WP_REST_Request $request ) {
		// Build filters from query parameters.
		$filters = array(
			'method'      => $request->get_param( 'method' ),
			'endpoint'    => $request->get_param( 'endpoint' ),
			'status_code' => $request->get_param( 'status_code' ),
			'date_from'   => $request->get_param( 'date_from' ),
			'date_to'     => $request->get_param( 'date_to' ),
			'page'        => $request->get_param( 'page' ),
			'per_page'    => $request->get_param( 'per_page' ),
		);

		// Query audit entries.
		$result = $this->audit_logger->query_audit_entries( $filters );

		return ResponseFormatter::success( $result, 200 );
	}
}
