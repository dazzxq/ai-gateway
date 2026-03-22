<?php
/**
 * Code Snippets Controller Class
 *
 * REST API endpoints for Code Snippets plugin database management.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\CodeSnippetsManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * CodeSnippetsController
 *
 * Handles REST endpoints for Code Snippets plugin database CRUD operations.
 */
class CodeSnippetsController {
	/**
	 * CodeSnippetsManager instance.
	 *
	 * @var CodeSnippetsManager
	 */
	private $manager;

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
	 * @param CodeSnippetsManager $manager           CodeSnippetsManager instance.
	 * @param PermissionChecker   $permission_checker PermissionChecker instance.
	 * @param AuditLogger         $audit_logger      AuditLogger instance.
	 */
	public function __construct(
		CodeSnippetsManager $manager,
		PermissionChecker $permission_checker,
		AuditLogger $audit_logger
	) {
		$this->manager            = $manager;
		$this->permission_checker = $permission_checker;
		$this->audit_logger       = $audit_logger;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /code-snippets - List code snippets.
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_list_snippets' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'active'   => array(
						'type'              => 'integer',
						'enum'              => array( 0, 1 ),
						'default'           => null,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// POST /code-snippets - Create code snippet.
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_create_snippet' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'name'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'code'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( 'AI_Gateway\API\ResponseFormatter', 'sanitize_passthrough' ),
					),
					'desc'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'tags'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'priority' => array(
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'active'   => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'scope'    => array(
						'type'              => 'string',
						'default'           => 'global',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /code-snippets/{id} - Read single code snippet.
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_snippet' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// PATCH /code-snippets/{id} - Update code snippet.
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'handle_update_snippet' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'id'       => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'name'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'code'     => array(
						'type'              => 'string',
						'sanitize_callback' => array( 'AI_Gateway\API\ResponseFormatter', 'sanitize_passthrough' ),
					),
					'desc'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'tags'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'priority' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'active'   => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'scope'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// DELETE /code-snippets/{id} - Delete code snippet.
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_delete_snippet' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// POST /code-snippets/{id}/toggle - Deactivate then reactivate a snippet (forces recompile).
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets/(?P<id>\d+)/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_toggle_snippet' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// POST /code-snippets/{id}/deploy - Atomic deploy (deactivate + activate + verify).
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets/(?P<id>\d+)/deploy',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_deploy_snippet' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// DELETE /code-snippets/cleanup-duplicates - Clean up inactive duplicate snippets.
		register_rest_route(
			'ai-gateway/v1',
			'/code-snippets/cleanup-duplicates',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_cleanup_duplicates' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);
	}

	/**
	 * GET /code-snippets - List code snippets with filtering and pagination.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with paginated snippets list.
	 */
	public function handle_list_snippets( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get parameters.
		$active   = $request->get_param( 'active' );
		$page     = $request->get_param( 'page' ) ?? 1;
		$per_page = $request->get_param( 'per_page' ) ?? 20;

		// Convert active parameter to boolean or null.
		$active_filter = null;
		if ( null !== $active ) {
			$active_filter = (bool) (int) $active;
		}

		// Clamp per_page to 1-100 range.
		$per_page = min( 100, max( 1, (int) $per_page ) );

		// Get snippets from manager.
		$result = $this->manager->get_snippets( $page, $per_page, $active_filter );

		if ( is_wp_error( $result ) ) {
			return ResponseFormatter::error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(),
				ResponseFormatter::get_wp_error_status( $result, 500 )
			);
		}

		// Build response.
		$response_data = array(
			'items'        => $result['items'],
			'total'        => $result['total'],
			'pages'        => $result['pages'],
			'current_page' => $result['current_page'],
			'per_page'     => $result['per_page'],
		);

		return ResponseFormatter::success( $response_data, 200 );
	}

	/**
	 * POST /code-snippets - Create a new code snippet with PHP validation.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with created snippet.
	 */
	public function handle_create_snippet( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get parameters.
		$params   = $request->get_json_params();
		$name     = $params['name'] ?? null;
		$code     = $params['code'] ?? null;
		$desc     = $params['desc'] ?? '';
		$tags     = $params['tags'] ?? '';
		$priority = $params['priority'] ?? 10;
		$active   = $params['active'] ?? 1;
		$scope    = $params['scope'] ?? 'global';

		// Validate required fields.
		if ( ! $name ) {
			return ResponseFormatter::error(
				'missing_field',
				'Missing required field: "name"',
				array(),
				400
			);
		}

		if ( ! $code ) {
			return ResponseFormatter::error(
				'missing_field',
				'Missing required field: "code"',
				array(),
				400
			);
		}

		// Create snippet via manager.
		$data = array(
			'name'     => $name,
			'code'     => $code,
			'desc'     => $desc,
			'tags'     => $tags,
			'priority' => $priority,
			'active'   => $active,
			'scope'    => $scope,
		);

		$result = $this->manager->insert_snippet( $data );

		if ( is_wp_error( $result ) ) {
			$status = ResponseFormatter::get_wp_error_status( $result, 400 );

			// Handle PHP syntax errors.
			if ( 'invalid_php_syntax' === $result->get_error_code() ) {
				$error_data    = $result->get_error_data();
				$syntax_errors = is_array( $error_data ) && isset( $error_data['errors'] ) ? $error_data['errors'] : array();
				return ResponseFormatter::error(
					$result->get_error_code(),
					$result->get_error_message(),
					array( 'errors' => $syntax_errors ),
					$status
				);
			}

			return ResponseFormatter::error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(),
				$status
			);
		}

		// Retrieve created snippet.
		$snippet = $this->manager->get_snippet( $result );

		if ( is_wp_error( $snippet ) ) {
			return ResponseFormatter::error(
				$snippet->get_error_code(),
				$snippet->get_error_message(),
				array(),
				500
			);
		}

		// Log to audit trail.
		$this->audit_logger->log_mutation(
			$request,
			null,
			array(
				'resource_type'      => 'code_snippet',
				'resource_id'        => $result,
				'action_description' => 'Created code snippet',
				'backend'            => 'database',
			)
		);

		return ResponseFormatter::success( $snippet, 201 );
	}

	/**
	 * GET /code-snippets/{id} - Get a single code snippet.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with snippet data.
	 */
	public function handle_get_snippet( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get snippet ID.
		$id = $request->get_param( 'id' );

		if ( ! is_numeric( $id ) || $id <= 0 ) {
			return ResponseFormatter::error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array(),
				400
			);
		}

		// Get snippet from manager.
		$snippet = $this->manager->get_snippet( (int) $id );

		if ( is_wp_error( $snippet ) ) {
			$status = ResponseFormatter::get_wp_error_status( $snippet, 500 );
			return ResponseFormatter::error(
				$snippet->get_error_code(),
				$snippet->get_error_message(),
				array(),
				$status
			);
		}

		return ResponseFormatter::success( $snippet, 200 );
	}

	/**
	 * PATCH /code-snippets/{id} - Update a code snippet with partial update support.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with updated snippet.
	 */
	public function handle_update_snippet( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get snippet ID.
		$id = $request->get_param( 'id' );

		if ( ! is_numeric( $id ) || $id <= 0 ) {
			return ResponseFormatter::error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array(),
				400
			);
		}

		// Get parameters.
		$params = $request->get_json_params();

		// Build update data with only provided fields.
		$data = array();
		foreach ( array( 'name', 'code', 'desc', 'tags', 'priority', 'active', 'scope' ) as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$data[ $field ] = $params[ $field ];
			}
		}

		// Update snippet via manager.
		$result = $this->manager->update_snippet( (int) $id, $data );

		if ( is_wp_error( $result ) ) {
			$status = ResponseFormatter::get_wp_error_status( $result, 500 );

			// Handle PHP syntax errors.
			if ( 'invalid_php_syntax' === $result->get_error_code() ) {
				$error_data    = $result->get_error_data();
				$syntax_errors = is_array( $error_data ) && isset( $error_data['errors'] ) ? $error_data['errors'] : array();
				return ResponseFormatter::error(
					$result->get_error_code(),
					$result->get_error_message(),
					array( 'errors' => $syntax_errors ),
					$status
				);
			}

			return ResponseFormatter::error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(),
				$status
			);
		}

		// Retrieve updated snippet.
		$snippet = $this->manager->get_snippet( (int) $id );

		if ( is_wp_error( $snippet ) ) {
			return ResponseFormatter::error(
				$snippet->get_error_code(),
				$snippet->get_error_message(),
				array(),
				500
			);
		}

		// Log to audit trail.
		$this->audit_logger->log_mutation(
			$request,
			null,
			array(
				'resource_type'      => 'code_snippet',
				'resource_id'        => (int) $id,
				'action_description' => 'Updated code snippet',
				'backend'            => 'database',
			)
		);

		return ResponseFormatter::success( $snippet, 200 );
	}

	/**
	 * POST /code-snippets/{id}/toggle - Deactivate then reactivate a snippet.
	 *
	 * Forces the Code Snippets plugin to recompile cached code.
	 * Useful after updating snippet code via PATCH.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with toggled snippet.
	 */
	public function handle_toggle_snippet( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get snippet ID.
		$id = $request->get_param( 'id' );

		if ( ! is_numeric( $id ) || $id <= 0 ) {
			return ResponseFormatter::error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array(),
				400
			);
		}

		// Toggle snippet via manager (deactivate then reactivate).
		$result = $this->manager->toggle_snippet( (int) $id );

		if ( is_wp_error( $result ) ) {
			$status = ResponseFormatter::get_wp_error_status( $result, 500 );
			return ResponseFormatter::error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(),
				$status
			);
		}

		// Retrieve toggled snippet.
		$snippet = $this->manager->get_snippet( (int) $id );

		if ( is_wp_error( $snippet ) ) {
			return ResponseFormatter::error(
				$snippet->get_error_code(),
				$snippet->get_error_message(),
				array(),
				500
			);
		}

		// Log to audit trail.
		$this->audit_logger->log_mutation(
			$request,
			null,
			array(
				'resource_type'      => 'code_snippet',
				'resource_id'        => (int) $id,
				'action_description' => 'Toggled code snippet (deactivate/reactivate)',
				'backend'            => 'database',
			)
		);

		return ResponseFormatter::success( $snippet, 200 );
	}

	/**
	 * POST /code-snippets/{id}/deploy - Deploy a snippet (deactivate + activate + verify).
	 *
	 * Performs an atomic deploy cycle: deactivate, activate, then verify the snippet
	 * is active and no PHP fatal errors occurred. Returns detailed step-by-step log.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with deploy results.
	 */
	public function handle_deploy_snippet( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get snippet ID.
		$id = $request->get_param( 'id' );

		if ( ! is_numeric( $id ) || $id <= 0 ) {
			return ResponseFormatter::error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array(),
				400
			);
		}

		// Deploy snippet via manager (deactivate + activate + verify).
		$result = $this->manager->deploy_snippet( (int) $id );

		if ( is_wp_error( $result ) ) {
			$status = ResponseFormatter::get_wp_error_status( $result, 500 );
			return ResponseFormatter::error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(),
				$status
			);
		}

		// Log to audit trail.
		$this->audit_logger->log_mutation(
			$request,
			null,
			array(
				'resource_type'      => 'code_snippet',
				'resource_id'        => (int) $id,
				'action_description' => 'Deployed code snippet (deactivate/activate/verify cycle)',
				'backend'            => 'database',
			)
		);

		return ResponseFormatter::success( $result, 200 );
	}

	/**
	 * DELETE /code-snippets/{id} - Delete a code snippet.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with deletion confirmation.
	 */
	public function handle_delete_snippet( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get snippet ID.
		$id = $request->get_param( 'id' );

		if ( ! is_numeric( $id ) || $id <= 0 ) {
			return ResponseFormatter::error(
				'invalid_snippet_id',
				'Snippet ID must be a positive integer',
				array(),
				400
			);
		}

		// Delete snippet via manager.
		$result = $this->manager->delete_snippet( (int) $id );

		if ( is_wp_error( $result ) ) {
			$status = ResponseFormatter::get_wp_error_status( $result, 500 );
			return ResponseFormatter::error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(),
				$status
			);
		}

		// Log to audit trail.
		$this->audit_logger->log_mutation(
			$request,
			null,
			array(
				'resource_type'      => 'code_snippet',
				'resource_id'        => (int) $id,
				'action_description' => 'Deleted code snippet',
				'backend'            => 'database',
			)
		);

		// Return confirmation.
		$response_data = array(
			'id'      => (int) $id,
			'status'  => 'deleted',
			'message' => 'Snippet deleted successfully',
		);

		return ResponseFormatter::success( $response_data, 200 );
	}

	/**
	 * DELETE /code-snippets/cleanup-duplicates - Find and delete inactive duplicate snippets.
	 *
	 * For each snippet name that appears more than once, keeps the lowest ID
	 * and deletes the rest that are inactive (active = 0).
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with cleanup results.
	 */
	public function handle_cleanup_duplicates( WP_REST_Request $request ) {
		// Check if plugin is active.
		if ( ! $this->manager->is_code_snippets_active() ) {
			return ResponseFormatter::error(
				'plugin_inactive',
				'Code Snippets plugin is not active',
				array(),
				503
			);
		}

		// Get duplicate count first for reporting.
		$count = $this->manager->get_duplicate_count();

		if ( is_wp_error( $count ) ) {
			$status = ResponseFormatter::get_wp_error_status( $count, 500 );
			return ResponseFormatter::error(
				$count->get_error_code(),
				$count->get_error_message(),
				array(),
				$status
			);
		}

		if ( 0 === $count ) {
			return ResponseFormatter::success(
				array(
					'deleted_count' => 0,
					'deleted_ids'   => array(),
					'details'       => array(),
					'message'       => 'No inactive duplicate snippets found',
				),
				200
			);
		}

		// Perform cleanup.
		$result = $this->manager->cleanup_duplicates();

		if ( is_wp_error( $result ) ) {
			$status = ResponseFormatter::get_wp_error_status( $result, 500 );
			return ResponseFormatter::error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(),
				$status
			);
		}

		// Log to audit trail.
		$this->audit_logger->log_mutation(
			$request,
			null,
			array(
				'resource_type'      => 'code_snippet',
				'resource_id'        => 'cleanup',
				'action_description' => sprintf( 'Cleaned up %d inactive duplicate snippets', $result['deleted_count'] ),
				'backend'            => 'database',
			)
		);

		$result['message'] = sprintf(
			'Successfully deleted %d inactive duplicate snippet(s)',
			$result['deleted_count']
		);

		return ResponseFormatter::success( $result, 200 );
	}
}
