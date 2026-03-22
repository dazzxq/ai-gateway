<?php
/**
 * Permission Checker Class
 *
 * REST API permission callback that guards endpoints with WordPress capabilities.
 *
 * @package AI_Gateway\Security
 */

namespace AI_Gateway\Security;

use WP_REST_Request;
use WP_Error;

/**
 * PermissionChecker
 *
 * Permission callback for REST routes that validates requests
 * using WordPress native current_user_can().
 */
class PermissionChecker {

	/**
	 * REST permission callback.
	 *
	 * Validates authentication via WP Application Password (Basic Auth).
	 * Only administrators with manage_options capability are allowed.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function rest_callback( WP_REST_Request $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			'You do not have permission to access this endpoint.',
			array( 'status' => 401 )
		);
	}
}
