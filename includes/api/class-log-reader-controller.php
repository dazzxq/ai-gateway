<?php
/**
 * Log Reader Controller Class
 *
 * REST endpoints for debug log reading and filtering.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Observability\LogReader;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * LogReaderController
 *
 * Handles REST endpoint for reading debug logs with filtering.
 */
class LogReaderController {
	/**
	 * Log Reader instance.
	 *
	 * @var LogReader
	 */
	private $log_reader;

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
	 * @param LogReader         $log_reader         Log Reader instance.
	 * @param PermissionChecker $permission_checker Permission Checker instance.
	 * @param AuditLogger       $audit_logger       Audit Logger instance.
	 */
	public function __construct( LogReader $log_reader, PermissionChecker $permission_checker, AuditLogger $audit_logger ) {
		$this->log_reader         = $log_reader;
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
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'read_logs_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'lines'  => array(
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'filter' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * GET /logs - Read debug log with filtering.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function read_logs_callback( WP_REST_Request $request ) {
		if ( ! $this->log_reader->is_available() ) {
			return ResponseFormatter::error(
				'debug_log_not_available',
				'Debug log not found. Enable WP_DEBUG in wp-config.php to enable logging.',
				array(),
				404
			);
		}

		$lines  = $request->get_param( 'lines' );
		$filter = $request->get_param( 'filter' );

		$logs = $this->log_reader->read_logs( $lines, $filter );

		return ResponseFormatter::success( $logs, 200 );
	}
}
