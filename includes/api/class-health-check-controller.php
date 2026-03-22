<?php
/**
 * Health Check Controller Class
 *
 * REST endpoints for infrastructure health monitoring.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\Observability\HealthChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * HealthCheckController
 *
 * Handles REST endpoint for health monitoring (no auth required).
 */
class HealthCheckController {
	/**
	 * Health Checker instance.
	 *
	 * @var HealthChecker
	 */
	private $health_checker;

	/**
	 * Constructor
	 *
	 * @param HealthChecker $health_checker Health Checker instance.
	 */
	public function __construct( HealthChecker $health_checker ) {
		$this->health_checker = $health_checker;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'ai-gateway/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_health_callback' ),
				'permission_callback' => '__return_true', // Health check requires NO auth.
			)
		);
	}

	/**
	 * GET /health - Get infrastructure health status.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function check_health_callback( WP_REST_Request $request ) {
		$status = $this->health_checker->get_health_status();

		// Determine HTTP status code based on health status.
		$http_status = 200;
		if ( 'degraded' === $status['status'] ) {
			$http_status = 200; // Still return 200 for degraded state.
		} elseif ( 'unhealthy' === $status['status'] ) {
			$http_status = 503; // Service Unavailable for unhealthy state.
		}

		return new WP_REST_Response( $status, $http_status );
	}
}
