<?php
/**
 * Code Snippets Manager Class
 *
 * Handles CRUD operations for Code Snippets plugin database entries.
 * Provides plugin detection, pagination, filtering, and PHP linting validation.
 *
 * @package AI_Gateway\ContentManagement
 */

namespace AI_Gateway\ContentManagement;

use WP_Error;
use AI_Gateway\FileOperations\PHPLinter;

/**
 * CodeSnippetsManager
 *
 * Manages Code Snippets plugin database entries (wp_snippets table).
 * Provides plugin detection, pagination, filtering, validation, and linting.
 */
class CodeSnippetsManager {
	/**
	 * PHP Linter instance.
	 *
	 * @var PHPLinter
	 */
	private $linter;

	/**
	 * Constructor
	 *
	 * @param PHPLinter $linter PHP linting service for code validation.
	 */
	public function __construct( PHPLinter $linter ) {
		$this->linter = $linter;
	}

	/**
	 * Check if Code Snippets plugin is active
	 *
	 * @return bool True if Code Snippets plugin is active, false otherwise.
	 */
	public function is_code_snippets_active() {
		return is_plugin_active( 'code-snippets/code-snippets.php' );
	}

	/**
	 * Get paginated list of code snippets with optional filtering
	 *
	 * @param int  $page      Page number (1-indexed, default 1).
	 * @param int  $per_page  Items per page (clamped to 1-100, default 20).
	 * @param bool $active    Filter by active status (true/false/null for all).
	 *
	 * @return array|WP_Error Array with 'items', 'total', 'pages', 'current_page', 'per_page', or WP_Error.
	 */
	public function get_snippets( $page = 1, $per_page = 20, $active = null ) {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		// Validate and clamp pagination parameters.
		$page     = max( 1, (int) $page );
		$per_page = min( 100, max( 1, (int) $per_page ) );

		// Build WHERE clause for active filter.
		// Code Snippets uses active: 1=active, 0=inactive, -1=trashed.
		// Always exclude trashed snippets (active >= 0).
		if ( is_bool( $active ) ) {
			$where = $wpdb->prepare( 'WHERE active = %d', $active ? 1 : 0 );
		} else {
			$where = 'WHERE active >= 0';
		}

		// Get table name.
		$table = $wpdb->prefix . 'snippets';

		// Get total count.
		$total_query = "SELECT COUNT(*) FROM {$table} {$where}";
		$total       = (int) $wpdb->get_var( $total_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Calculate pagination.
		$total_pages = ceil( $total / $per_page );
		$offset      = ( $page - 1 ) * $per_page;

		// Get snippets for current page ($table from $wpdb->prefix, $where pre-prepared).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$items = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Return paginated results.
		return array(
			'items'        => $items ? $items : array(),
			'total'        => $total,
			'pages'        => (int) $total_pages,
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Get a single code snippet by ID
	 *
	 * @param int $id Snippet ID.
	 *
	 * @return object|WP_Error Snippet object or WP_Error.
	 */
	public function get_snippet( $id ) {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		// Validate ID.
		$id = (int) $id;
		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array( 'status' => 400 )
			);
		}

		// Get snippet from database.
		$table   = $wpdb->prefix . 'snippets';
		$query   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$snippet = $wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $snippet ) {
			return new WP_Error(
				'snippet_not_found',
				sprintf( 'Snippet with ID %d not found', $id ),
				array( 'status' => 404 )
			);
		}

		return $snippet;
	}

	/**
	 * Insert a new code snippet with PHP validation.
	 *
	 * @param array $data Snippet data containing fields.
	 *   - name (required): Snippet name
	 *   - code (required): PHP code
	 *   - desc (optional): Description
	 *   - tags (optional): Comma-separated tags
	 *   - priority (optional, default 10): Execution priority
	 *   - active (optional, default 1): Active status (0 or 1).
	 *
	 * @return int|WP_Error New snippet ID on success, WP_Error on failure.
	 */
	public function insert_snippet( array $data ) {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		// Validate required fields.
		if ( empty( $data['name'] ) ) {
			return new WP_Error(
				'empty_name',
				'Snippet name is required',
				array( 'status' => 400 )
			);
		}

		if ( empty( $data['code'] ) ) {
			return new WP_Error(
				'empty_code',
				'Snippet code is required',
				array( 'status' => 400 )
			);
		}

		// Validate PHP syntax.
		$lint_result = $this->linter->lint_content( $data['code'] );
		if ( is_wp_error( $lint_result ) ) {
			$error = new WP_Error(
				'invalid_php_syntax',
				'PHP syntax validation failed',
				array(
					'status' => 400,
					'errors' => $lint_result->get_error_data(),
				)
			);
			return $error;
		}

		// Prepare data with defaults.
		// Validate scope if provided.
		$valid_scopes = array( 'global', 'front-end', 'admin', 'content', 'single-use' );
		$scope        = 'global';
		if ( isset( $data['scope'] ) && in_array( $data['scope'], $valid_scopes, true ) ) {
			$scope = $data['scope'];
		}

		$insert_data = array(
			'name'         => $data['name'],
			'description'  => isset( $data['desc'] ) ? $data['desc'] : '',
			'code'         => $data['code'],
			'tags'         => isset( $data['tags'] ) ? $data['tags'] : '',
			'scope'        => $scope,
			'priority'     => isset( $data['priority'] ) ? (int) $data['priority'] : 10,
			'active'       => isset( $data['active'] ) ? (int) $data['active'] : 1,
			'modified'     => current_time( 'mysql' ),
			'revision'     => 1,
			'condition_id' => 0,
			'cloud_id'     => null,
		);

		// Insert into database.
		$table  = $wpdb->prefix . 'snippets';
		$result = $wpdb->insert(
			$table,
			$insert_data,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error(
				'database_error',
				'Failed to insert snippet into database',
				array( 'status' => 500 )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing code snippet with PHP validation.
	 *
	 * @param int   $id   Snippet ID.
	 * @param array $data Partial snippet data to update.
	 *   - name (optional): Snippet name.
	 *   - code (optional): PHP code.
	 *   - desc (optional): Description.
	 *   - tags (optional): Comma-separated tags.
	 *   - priority (optional): Execution priority.
	 *   - active (optional): Active status (0 or 1).
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_snippet( $id, array $data ) {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		// Validate ID.
		$id = (int) $id;
		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array( 'status' => 400 )
			);
		}

		// Check if snippet exists.
		$snippet = $this->get_snippet( $id );
		if ( is_wp_error( $snippet ) ) {
			return $snippet;
		}

		// Validate PHP syntax if code is being updated.
		if ( isset( $data['code'] ) && ! empty( $data['code'] ) ) {
			$lint_result = $this->linter->lint_content( $data['code'] );
			if ( is_wp_error( $lint_result ) ) {
				$error = new WP_Error(
					'invalid_php_syntax',
					'PHP syntax validation failed',
					array(
						'status' => 400,
						'errors' => $lint_result->get_error_data(),
					)
				);
				return $error;
			}
		}

		// Prepare update data with only provided fields.
		$update_data = array();
		$data_types  = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = $data['name'];
			$data_types[]        = '%s';
		}
		if ( isset( $data['code'] ) ) {
			$update_data['code'] = $data['code'];
			$data_types[]        = '%s';
		}
		if ( isset( $data['desc'] ) ) {
			$update_data['description'] = $data['desc'];
			$data_types[]               = '%s';
		}
		if ( isset( $data['tags'] ) ) {
			$update_data['tags'] = $data['tags'];
			$data_types[]        = '%s';
		}
		if ( isset( $data['priority'] ) ) {
			$update_data['priority'] = (int) $data['priority'];
			$data_types[]            = '%d';
		}
		if ( isset( $data['active'] ) ) {
			$update_data['active'] = (int) $data['active'];
			$data_types[]          = '%d';
		}
		if ( isset( $data['scope'] ) ) {
			$valid_scopes = array( 'global', 'front-end', 'admin', 'content', 'single-use' );
			if ( in_array( $data['scope'], $valid_scopes, true ) ) {
				$update_data['scope'] = $data['scope'];
				$data_types[]         = '%s';
			}
		}

		// Always update modified timestamp.
		$update_data['modified'] = current_time( 'mysql' );
		$data_types[]            = '%s';

		// Update in database.
		$table  = $wpdb->prefix . 'snippets';
		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			$data_types,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to update snippet in database',
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Toggle a snippet by deactivating then reactivating it.
	 *
	 * Forces the Code Snippets plugin to recompile cached code.
	 *
	 * @param int $id Snippet ID.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function toggle_snippet( $id ) {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		// Validate ID.
		$id = (int) $id;
		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array( 'status' => 400 )
			);
		}

		// Check if snippet exists and is active.
		$snippet = $this->get_snippet( $id );
		if ( is_wp_error( $snippet ) ) {
			return $snippet;
		}

		if ( empty( $snippet->active ) ) {
			return new WP_Error(
				'snippet_inactive',
				'Cannot toggle an inactive snippet. Activate it first.',
				array( 'status' => 400 )
			);
		}

		$table = $wpdb->prefix . 'snippets';

		// Use Code Snippets plugin's own functions if available (handles cache clearing).
		if ( function_exists( '\\Code_Snippets\\deactivate_snippet' ) && function_exists( '\\Code_Snippets\\activate_snippet' ) ) {
			\Code_Snippets\deactivate_snippet( $id, false );
			$result = \Code_Snippets\activate_snippet( $id, false );

			if ( is_string( $result ) ) {
				return new WP_Error(
					'toggle_failed',
					$result,
					array( 'status' => 500 )
				);
			}

			return true;
		}

		// Fallback: direct DB update + manual cache clear.
		$wpdb->update(
			$table,
			array( 'active' => 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		$result = $wpdb->update(
			$table,
			array(
				'active'   => 1,
				'modified' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to toggle snippet in database',
				array( 'status' => 500 )
			);
		}

		// Clear Code Snippets plugin cache.
		if ( function_exists( '\\Code_Snippets\\clean_snippets_cache' ) ) {
			\Code_Snippets\clean_snippets_cache( $table );
		}

		return true;
	}

	/**
	 * Deploy a snippet by performing a deactivate/activate/verify cycle.
	 *
	 * Forces recompilation of snippet code. Unlike toggle_snippet(), this method
	 * returns detailed step-by-step results and checks the PHP error log for
	 * new fatal errors after activation.
	 *
	 * @param int $id Snippet ID.
	 *
	 * @return array|WP_Error Deploy result with 'deployed', 'active', 'steps' keys, or WP_Error.
	 */
	public function deploy_snippet( int $id ): array|WP_Error {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		// Validate ID.
		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array( 'status' => 400 )
			);
		}

		// Check if snippet exists.
		$snippet = $this->get_snippet( $id );
		if ( is_wp_error( $snippet ) ) {
			return $snippet;
		}

		$steps           = array();
		$error_log_start = $this->get_error_log_position();
		$table           = $wpdb->prefix . 'snippets';

		// Step 1: Deactivate.
		if ( function_exists( '\\Code_Snippets\\deactivate_snippet' ) ) {
			$deactivate_result = \Code_Snippets\deactivate_snippet( $id, false );
			$deactivate_ok     = ( true === $deactivate_result );
		} else {
			// Direct DB fallback.
			$wpdb->update(
				$table,
				array( 'active' => 0 ),
				array( 'id' => $id ),
				array( '%d' ),
				array( '%d' )
			);
			$deactivate_ok = ( $wpdb->rows_affected > 0 );
		}
		$steps[] = array(
			'action' => 'deactivate',
			'ok'     => $deactivate_ok,
		);

		// Step 2: Activate.
		if ( function_exists( '\\Code_Snippets\\activate_snippet' ) ) {
			$activate_result = \Code_Snippets\activate_snippet( $id, false );
			$activate_ok     = ! is_string( $activate_result ); // string = error message.
		} else {
			// Direct DB fallback.
			$wpdb->update(
				$table,
				array(
					'active'   => 1,
					'modified' => current_time( 'mysql' ),
				),
				array( 'id' => $id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			$activate_ok = ( $wpdb->rows_affected > 0 );
		}
		$steps[] = array(
			'action' => 'activate',
			'ok'     => $activate_ok,
		);

		// Step 3: Verify -- check active flag + error log.
		$snippet_after = $this->get_snippet( $id );
		$is_active     = ! is_wp_error( $snippet_after ) && ! empty( $snippet_after->active );
		$new_errors    = $this->check_error_log_since( $error_log_start );

		$steps[] = array(
			'action' => 'verify',
			'ok'     => $is_active && empty( $new_errors ),
			'active' => $is_active,
			'errors' => $new_errors,
		);

		return array(
			'deployed' => true,
			'active'   => $is_active,
			'steps'    => $steps,
		);
	}

	/**
	 * Get current byte position of the PHP error log file.
	 *
	 * @return int Current file size in bytes, or 0 if file not found.
	 */
	private function get_error_log_position(): int {
		$log_file = ini_get( 'error_log' );

		if ( empty( $log_file ) || ! file_exists( $log_file ) ) {
			return 0;
		}

		$size = filesize( $log_file );
		return ( false !== $size ) ? $size : 0;
	}

	/**
	 * Check the PHP error log for new fatal errors since a given byte position.
	 *
	 * @param int $position Byte position to start reading from.
	 *
	 * @return array Array of error strings (max 10).
	 */
	private function check_error_log_since( int $position ): array {
		$log_file = ini_get( 'error_log' );

		if ( empty( $log_file ) || ! file_exists( $log_file ) ) {
			return array();
		}

		$current_size = filesize( $log_file );
		if ( false === $current_size || $current_size <= $position ) {
			return array();
		}

		// Read new content from the recorded position.
		$handle = fopen( $log_file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array();
		}

		fseek( $handle, $position );
		$new_content = fread( $handle, min( $current_size - $position, 65536 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( empty( $new_content ) ) {
			return array();
		}

		// Filter for fatal/error lines.
		$lines  = explode( "\n", $new_content );
		$errors = array();

		foreach ( $lines as $line ) {
			if ( preg_match( '/Fatal error|PHP Fatal/i', $line ) ) {
				$errors[] = trim( $line );
				if ( count( $errors ) >= 10 ) {
					break;
				}
			}
		}

		return $errors;
	}

	/**
	 * Get count of inactive duplicate snippets.
	 *
	 * Finds snippets that share the same name, keeping the lowest ID
	 * for each name and counting the rest that are inactive (active = 0).
	 *
	 * @return int|WP_Error Count of duplicate inactive snippets, or WP_Error.
	 */
	public function get_duplicate_count() {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		$table = $wpdb->prefix . 'snippets';

		// Find inactive snippets whose name appears more than once,.
		// excluding the lowest ID for each name.
		// No user input -- table name from $wpdb->prefix.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} s
			WHERE s.active = 0
			AND s.id NOT IN (
				SELECT MIN(id) FROM {$table} GROUP BY name
			)
			AND s.name IN (
				SELECT name FROM {$table} GROUP BY name HAVING COUNT(*) > 1
			)"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Clean up inactive duplicate snippets.
	 *
	 * For each snippet name that appears more than once, keeps the lowest ID
	 * and deletes the rest that are inactive (active = 0).
	 *
	 * @return array|WP_Error Array with 'deleted_count' and 'deleted_ids', or WP_Error.
	 */
	public function cleanup_duplicates() {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		$table = $wpdb->prefix . 'snippets';

		// Find inactive duplicate snippet IDs to delete.
		// Keep the lowest ID for each name, delete inactive duplicates.
		// No user input -- table name from $wpdb->prefix.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$duplicates = $wpdb->get_results(
			"SELECT s.id, s.name FROM {$table} s
			WHERE s.active = 0
			AND s.id NOT IN (
				SELECT MIN(id) FROM {$table} GROUP BY name
			)
			AND s.name IN (
				SELECT name FROM {$table} GROUP BY name HAVING COUNT(*) > 1
			)
			ORDER BY s.name, s.id"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $duplicates ) ) {
			return array(
				'deleted_count' => 0,
				'deleted_ids'   => array(),
				'details'       => array(),
			);
		}

		$deleted_ids = array();
		$details     = array();

		foreach ( $duplicates as $dup ) {
			$result = $wpdb->delete(
				$table,
				array( 'id' => (int) $dup->id ),
				array( '%d' )
			);

			if ( $result ) {
				$deleted_ids[] = (int) $dup->id;
				$details[]     = array(
					'id'   => (int) $dup->id,
					'name' => $dup->name,
				);
			}
		}

		return array(
			'deleted_count' => count( $deleted_ids ),
			'deleted_ids'   => $deleted_ids,
			'details'       => $details,
		);
	}

	/**
	 * Delete a code snippet (hard delete)
	 *
	 * @param int $id Snippet ID.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_snippet( $id ) {
		global $wpdb;

		// Check if plugin is active.
		if ( ! $this->is_code_snippets_active() ) {
			return new WP_Error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array( 'status' => 503 )
			);
		}

		// Validate ID.
		$id = (int) $id;
		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array( 'status' => 400 )
			);
		}

		// Check if snippet exists.
		$snippet = $this->get_snippet( $id );
		if ( is_wp_error( $snippet ) ) {
			return $snippet;
		}

		// Delete from database.
		$table  = $wpdb->prefix . 'snippets';
		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $result ) {
			return new WP_Error(
				'database_error',
				'Failed to delete snippet from database',
				array( 'status' => 500 )
			);
		}

		return true;
	}
}
