<?php
/**
 * Audit Logger Class
 *
 * Logs all mutations to audit trail table.
 *
 * @package AI_Gateway\ContentManagement
 */

namespace AI_Gateway\ContentManagement;

use WP_REST_Request;
use WP_REST_Response;

/**
 * AuditLogger
 *
 * Tracks and manages mutation audit trails.
 */
class AuditLogger {
	/**
	 * Maximum audit entries to keep.
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 1000;

	/**
	 * Log a mutation to audit table.
	 *
	 * @param WP_REST_Request  $request Request object.
	 * @param WP_REST_Response $response Response object (may be null for early logging).
	 * @param array            $resource_info Resource information containing fields.
	 *   - resource_type (string): Type of resource (post, snippet, etc.).
	 *   - resource_id (string): ID of resource affected.
	 *   - action_description (string): Human-readable action description.
	 *
	 * @return bool True if logged successfully.
	 */
	public function log_mutation( WP_REST_Request $request, ?WP_REST_Response $response, $resource_info = array() ) {
		global $wpdb;

		// Extract request/response data.
		$method      = $request->get_method();
		$endpoint    = $request->get_route();
		$status_code = $response ? $response->get_status() : 200;  // Default to 200 if response not yet created.

		// Get WP user identity from current authenticated user.
		$identity = $this->get_user_identity();

		// Build audit entry.
		$entry = array(
			'timestamp'          => current_time( 'mysql', true ),
			'method'             => $method,
			'endpoint'           => substr( $endpoint, 0, 255 ),
			'status_code'        => intval( $status_code ),
			'user_id'            => $identity['user_id'],
			'username'           => $identity['username'],
			'resource_type'      => isset( $resource_info['resource_type'] ) ? substr( $resource_info['resource_type'], 0, 50 ) : '',
			'resource_id'        => isset( $resource_info['resource_id'] ) ? substr( $resource_info['resource_id'], 0, 255 ) : '',
			'action_description' => isset( $resource_info['action_description'] ) ? substr( $resource_info['action_description'], 0, 500 ) : '',
		);

		// Insert into audit table.
		$table_name = $wpdb->prefix . 'ai_gateway_audit';
		$result     = $wpdb->insert( $table_name, $entry );

		if ( false === $result ) {
			return false;
		}

		// Prune old entries if needed.
		$this->prune_old_entries();

		return true;
	}

	/**
	 * Get the current WP user identity.
	 *
	 * Returns user_id and username from the authenticated WP user.
	 * Falls back to user_id=0 and username='unknown' if no user is logged in.
	 *
	 * @return array{user_id: int, username: string} User identity array.
	 */
	private function get_user_identity() {
		$user = wp_get_current_user();

		if ( $user->ID > 0 ) {
			return array(
				'user_id'  => $user->ID,
				'username' => $user->user_login,
			);
		}

		return array(
			'user_id'  => 0,
			'username' => 'unknown',
		);
	}

	/**
	 * Log a simple action (no REST request context needed).
	 *
	 * Used by admin dashboard and admin controller for non-API actions.
	 *
	 * @param string $action        Action name (e.g., 'generate_api_key').
	 * @param string $endpoint      Endpoint path.
	 * @param string $method        HTTP method (GET, POST, etc.).
	 * @param int    $status_code   HTTP status code.
	 *
	 * @return bool True if logged successfully.
	 */
	public function log_action( $action, $endpoint, $method, $status_code ) {
		global $wpdb;

		$identity = $this->get_user_identity();

		$entry = array(
			'timestamp'          => current_time( 'mysql', true ),
			'method'             => $method,
			'endpoint'           => substr( $endpoint, 0, 255 ),
			'status_code'        => intval( $status_code ),
			'user_id'            => $identity['user_id'],
			'username'           => $identity['username'],
			'resource_type'      => 'admin',
			'resource_id'        => '',
			'action_description' => substr( $action, 0, 500 ),
		);

		$table_name = $wpdb->prefix . 'ai_gateway_audit';
		$result     = $wpdb->insert( $table_name, $entry );

		return false !== $result;
	}

	/**
	 * Prune old audit entries keeping max 1000 most recent.
	 *
	 * @return bool True if pruned successfully or no pruning needed
	 */
	public function prune_old_entries() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_gateway_audit';

		// Count total entries.
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );

		if ( $count <= self::MAX_ENTRIES ) {
			return true;
		}

		// Delete oldest entries to bring count back to MAX_ENTRIES.
		$to_delete = $count - self::MAX_ENTRIES;

		// Get IDs of oldest entries.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} ORDER BY timestamp ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$to_delete
			)
		);

		if ( empty( $ids ) ) {
			return true;
		}

		// Delete them.
		$id_list = implode( ',', array_map( 'intval', $ids ) );
		$wpdb->query( "DELETE FROM {$table_name} WHERE id IN ({$id_list})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list from array_map('intval')

		return true;
	}

	/**
	 * Query audit entries with filters.
	 *
	 * @param array $filters Filter parameters.
	 *   - method (string): HTTP method to filter (POST, PATCH, DELETE).
	 *   - endpoint (string): Partial endpoint string to search.
	 *   - status_code (int): HTTP status code to filter.
	 *   - date_from (string): ISO date string to filter from.
	 *   - date_to (string): ISO date string to filter to.
	 *   - page (int): Page number (default 1).
	 *   - per_page (int): Items per page (default 20).
	 *
	 * @return array Entries and pagination metadata.
	 */
	public function query_audit_entries( $filters = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_gateway_audit';

		// Build WHERE clause.
		$where_parts = array();

		if ( ! empty( $filters['method'] ) ) {
			$where_parts[] = $wpdb->prepare( 'method = %s', strtoupper( $filters['method'] ) );
		}

		if ( ! empty( $filters['endpoint'] ) ) {
			$where_parts[] = $wpdb->prepare( 'endpoint LIKE %s', '%' . $wpdb->esc_like( $filters['endpoint'] ) . '%' );
		}

		if ( isset( $filters['status_code'] ) && '' !== $filters['status_code'] ) {
			$where_parts[] = $wpdb->prepare( 'status_code = %d', intval( $filters['status_code'] ) );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_parts[] = $wpdb->prepare( 'timestamp >= %s', $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_parts[] = $wpdb->prepare( 'timestamp <= %s', $filters['date_to'] );
		}

		$where_clause = ! empty( $where_parts ) ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

		// Get total count.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} {$where_clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause fragments are individually prepared

		// Pagination.
		$page = isset( $filters['page'] ) ? intval( $filters['page'] ) : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		$per_page = isset( $filters['per_page'] ) ? intval( $filters['per_page'] ) : 20;
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		$offset = ( $page - 1 ) * $per_page;

		// Get entries.
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} {$where_clause} ORDER BY timestamp DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		// Convert stdClass to arrays.
		$entries = array_map(
			function ( $entry ) {
				return (array) $entry;
			},
			$entries
		);

		$total_pages = ceil( $total / $per_page );

		return array(
			'entries'    => $entries,
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => intval( $total ),
				'total_pages' => intval( $total_pages ),
			),
		);
	}
}
