<?php
/**
 * System Controller Class
 *
 * REST API endpoints for system operations (cache management).
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;

/**
 * SystemController
 *
 * Handles REST endpoints for system-level operations such as cache flushing.
 */
class SystemController {
	/**
	 * PermissionChecker instance.
	 *
	 * @var PermissionChecker
	 */
	private $permission_checker;

	/**
	 * AuditLogger instance.
	 *
	 * @var AuditLogger
	 */
	private $audit_logger;

	/**
	 * Constructor
	 *
	 * @param PermissionChecker $permission_checker PermissionChecker instance.
	 * @param AuditLogger       $audit_logger      AuditLogger instance.
	 */
	public function __construct(
		PermissionChecker $permission_checker,
		AuditLogger $audit_logger
	) {
		$this->permission_checker = $permission_checker;
		$this->audit_logger       = $audit_logger;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// POST /system/flush-cache - Flush all caches.
		register_rest_route(
			'ai-gateway/v1',
			'/system/flush-cache',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_flush_cache' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);
	}

	/**
	 * POST /system/flush-cache - Flush all caches.
	 *
	 * Clears SpeedyCache page cache (if active), WP object cache,
	 * and DB-stored transients.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response REST response with per-component results.
	 */
	public function handle_flush_cache( WP_REST_Request $request ) {
		try {
			$results = array();

			// 1. SpeedyCache flush.
			if ( class_exists( '\\SpeedyCache\\Delete' ) ) {
				try {
					\SpeedyCache\Delete::run( array() );
					$results['speedycache'] = array( 'flushed' => true );
				} catch ( \Throwable $e ) {
					$results['speedycache'] = array(
						'flushed' => false,
						'reason'  => $e->getMessage(),
					);
				}
			} else {
				$results['speedycache'] = array(
					'flushed' => false,
					'reason'  => 'SpeedyCache not active',
				);
			}

			// 2. WP object cache flush.
			wp_cache_flush();
			$results['object_cache'] = array( 'flushed' => true );

			// 3. Transient cleanup (DB-stored)
			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_' ) . '%',
					$wpdb->esc_like( '_site_transient_' ) . '%'
				)
			);
			$results['transients'] = array( 'deleted' => (int) $wpdb->rows_affected );

			// Log mutation.
			$this->audit_logger->log_mutation(
				$request,
				null,
				array(
					'resource_type'      => 'system',
					'resource_id'        => 'cache',
					'action_description' => 'Flushed all caches',
					'backend'            => 'system',
				)
			);

			return ResponseFormatter::success( $results, 200 );
		} catch ( \Throwable $e ) {
			return ResponseFormatter::from_exception( 'flush_cache_error', $e, 500 );
		}
	}
}
