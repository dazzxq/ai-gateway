<?php
/**
 * Global Styles Controller Class
 *
 * REST API endpoints for Global Styles CSS management.
 * Manages CSS stored in Site Editor > Styles > Additional CSS
 * (wp_global_styles post type, NOT the Customizer custom_css).
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\GlobalStylesManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GlobalStylesController
 *
 * Handles REST endpoints for Global Styles CSS, JSON, debug, and repair.
 */
class GlobalStylesController {
	/**
	 * GlobalStylesManager instance.
	 *
	 * @var GlobalStylesManager
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
	 * @param GlobalStylesManager $manager           GlobalStylesManager instance.
	 * @param PermissionChecker   $permission_checker PermissionChecker instance.
	 * @param AuditLogger         $audit_logger      AuditLogger instance.
	 */
	public function __construct(
		GlobalStylesManager $manager,
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
		// GET /global-styles/css - Read Global Styles CSS.
		register_rest_route(
			'ai-gateway/v1',
			'/global-styles/css',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_css' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);

		// PATCH /global-styles/css - Update Global Styles CSS.
		register_rest_route(
			'ai-gateway/v1',
			'/global-styles/css',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'handle_patch_css' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'content' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( 'AI_Gateway\API\ResponseFormatter', 'sanitize_passthrough' ),
					),
				),
			)
		);

		// GET /global-styles/json - Read Global Styles JSON (without CSS).
		register_rest_route(
			'ai-gateway/v1',
			'/global-styles/json',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_styles_json' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);

		// PATCH /global-styles/json - Update Global Styles JSON with merge.
		register_rest_route(
			'ai-gateway/v1',
			'/global-styles/json',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'handle_patch_styles_json' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);

		// POST /global-styles/repair - Fix corrupted JSON.
		register_rest_route(
			'ai-gateway/v1',
			'/global-styles/repair',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_repair' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);

		// GET /global-styles/debug - Diagnostic info.
		register_rest_route(
			'ai-gateway/v1',
			'/global-styles/debug',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_debug' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);
	}

	/**
	 * GET /global-styles/debug - Diagnostic information for troubleshooting.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Diagnostic data.
	 */
	public function handle_debug( WP_REST_Request $request ) {
		try {
			$stylesheet = wp_get_theme()->get_stylesheet();
			$post       = $this->manager->get_global_styles_post();
			$debug      = array(
				'stylesheet'        => $stylesheet,
				'post_found'        => null !== $post,
				'post_id'           => $post ? $post->ID : null,
				'post_type'         => $post ? $post->post_type : null,
				'post_status'       => $post ? $post->post_status : null,
				'content_length'    => $post ? strlen( $post->post_content ) : 0,
				'content_first_100' => $post ? substr( $post->post_content, 0, 100 ) : null,
				'json_valid'        => false,
				'has_styles_css'    => false,
				'css_length'        => 0,
			);

			if ( $post && ! empty( $post->post_content ) ) {
				$decoded                 = json_decode( $post->post_content, true );
				$debug['json_valid']     = json_last_error() === JSON_ERROR_NONE;
				$debug['json_error']     = json_last_error_msg();
				$debug['has_styles_css'] = isset( $decoded['styles']['css'] );
				$debug['css_length']     = isset( $decoded['styles']['css'] ) ? strlen( $decoded['styles']['css'] ) : 0;
				$debug['top_level_keys'] = $decoded ? array_keys( $decoded ) : array();
			}

			return ResponseFormatter::success( $debug, 200 );
		} catch ( \Throwable $e ) {
			return ResponseFormatter::from_exception( 'debug_error', $e, 500 );
		}
	}

	/**
	 * POST /global-styles/repair - Repair corrupted Global Styles JSON.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Repair result.
	 */
	public function handle_repair( WP_REST_Request $request ) {
		try {
			$result = $this->manager->repair();

			if ( is_wp_error( $result ) ) {
				$status     = 500;
				$error_data = $result->get_error_data();
				if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
					$status = (int) $error_data['status'];
				}
				return ResponseFormatter::error( $result->get_error_code(), $result->get_error_message(), array(), $status );
			}

			$this->audit_logger->log_mutation(
				$request,
				null,
				array(
					'resource_type'      => 'global_styles',
					'resource_id'        => 'global',
					'action_description' => 'Repaired corrupted Global Styles JSON (status: ' . $result['status'] . ')',
					'backend'            => 'database',
				)
			);

			return ResponseFormatter::success( $result, 200 );
		} catch ( \Throwable $e ) {
			return ResponseFormatter::from_exception( 'repair_error', $e, 500 );
		}
	}

	/**
	 * GET /global-styles/json - Retrieve Global Styles JSON without CSS.
	 *
	 * Returns the styles and settings JSON fields without the large
	 * styles.css Additional CSS field (169KB+).
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with styles JSON and metadata.
	 */
	public function handle_get_styles_json( WP_REST_Request $request ) {
		try {
			$styles = $this->manager->get_styles_json();

			if ( is_wp_error( $styles ) ) {
				return ResponseFormatter::error( $styles->get_error_code(), $styles->get_error_message(), array(), 500 );
			}

			$metadata = $this->manager->get_metadata();

			return ResponseFormatter::success(
				array(
					'styles'        => $styles,
					'last_modified' => $metadata['last_modified'],
					'stylesheet'    => $metadata['stylesheet'],
					'post_id'       => $metadata['post_id'],
				),
				200
			);
		} catch ( \Throwable $e ) {
			return ResponseFormatter::from_exception( 'global_styles_error', $e, 500 );
		}
	}

	/**
	 * PATCH /global-styles/json - Update Global Styles JSON with deep merge.
	 *
	 * Accepts a partial JSON payload and deep-merges it into the existing
	 * Global Styles. The styles.css field is stripped from input and the
	 * original CSS is preserved.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with merged result.
	 */
	public function handle_patch_styles_json( WP_REST_Request $request ) {
		try {
			$params = $request->get_json_params();

			if ( empty( $params ) ) {
				return ResponseFormatter::error( 'invalid_payload', 'Request body must be a non-empty JSON object', array(), 400 );
			}

			$result = $this->manager->merge_styles_json( $params );

			if ( is_wp_error( $result ) ) {
				$status     = 400;
				$error_data = $result->get_error_data();
				if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
					$status = (int) $error_data['status'];
				}
				return ResponseFormatter::error( $result->get_error_code(), $result->get_error_message(), array(), $status );
			}

			$this->audit_logger->log_mutation(
				$request,
				null,
				array(
					'resource_type'      => 'global_styles_json',
					'resource_id'        => 'global',
					'action_description' => 'Updated Global Styles JSON (merge)',
					'backend'            => 'database',
				)
			);

			// Return updated styles JSON (without CSS) for response.
			$updated_styles = $this->manager->get_styles_json();

			return ResponseFormatter::success(
				array(
					'styles'        => is_wp_error( $updated_styles ) ? null : $updated_styles,
					'last_modified' => $result['last_modified'],
					'stylesheet'    => $result['stylesheet'],
					'post_id'       => $result['post_id'],
				),
				200
			);
		} catch ( \Throwable $e ) {
			return ResponseFormatter::from_exception( 'global_styles_error', $e, 500 );
		}
	}

	/**
	 * GET /global-styles/css - Retrieve Global Styles CSS content.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with CSS content and metadata.
	 */
	public function handle_get_css( WP_REST_Request $request ) {
		try {
			// Get CSS content.
			$content = $this->manager->get_css();

			if ( is_wp_error( $content ) ) {
				return ResponseFormatter::error(
					$content->get_error_code(),
					$content->get_error_message(),
					array(),
					500
				);
			}

			// Get metadata.
			$metadata = $this->manager->get_metadata();

			// Build response.
			$response_data = array(
				'content'        => $content,
				'last_modified'  => $metadata['last_modified'],
				'content_length' => strlen( $content ),
				'stylesheet'     => $metadata['stylesheet'],
				'post_id'        => $metadata['post_id'],
			);

			return ResponseFormatter::success( $response_data, 200 );
		} catch ( \Throwable $e ) {
			return ResponseFormatter::from_exception( 'global_styles_error', $e, 500 );
		}
	}

	/**
	 * PATCH /global-styles/css - Update Global Styles CSS content.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response REST response with updated CSS.
	 */
	public function handle_patch_css( WP_REST_Request $request ) {
		try {
			$params = $request->get_json_params();
			if ( ! isset( $params['content'] ) ) {
				return ResponseFormatter::error( 'missing_field', 'Missing required field: "content"', array(), 400 );
			}

			$result = $this->manager->update_css( $params['content'] );
			if ( is_wp_error( $result ) ) {
				$status     = 400;
				$error_data = $result->get_error_data();
				if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
					$status = (int) $error_data['status'];
				}
				return ResponseFormatter::error( $result->get_error_code(), $result->get_error_message(), array(), $status );
			}

			$this->audit_logger->log_mutation(
				$request,
				null,
				array(
					'resource_type'      => 'global_styles_css',
					'resource_id'        => 'global',
					'action_description' => 'Updated Global Styles CSS',
					'backend'            => 'database',
				)
			);

			return ResponseFormatter::success(
				array(
					'content'        => $params['content'],
					'last_modified'  => $result['last_modified'],
					'content_length' => strlen( $params['content'] ),
					'stylesheet'     => $result['stylesheet'],
					'post_id'        => $result['post_id'],
				),
				200
			);
		} catch ( \Throwable $e ) {
			return ResponseFormatter::from_exception( 'global_styles_error', $e, 500 );
		}
	}
}
