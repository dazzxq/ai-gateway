<?php
/**
 * Post Controller Class
 *
 * REST endpoint for post content search.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\PostManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use AI_Gateway\Exceptions\GatewayException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * PostController
 *
 * Handles REST endpoint for post content search.
 */
class PostController {
	/**
	 * Post Manager instance.
	 *
	 * @var PostManager
	 */
	private $post_manager;

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
	private $audit_logger; // @phpstan-ignore property.onlyWritten

	/**
	 * Constructor.
	 *
	 * @param PostManager       $post_manager       Post Manager instance.
	 * @param PermissionChecker $permission_checker Permission Checker instance.
	 * @param AuditLogger       $audit_logger       Audit Logger instance.
	 */
	public function __construct( PostManager $post_manager, PermissionChecker $permission_checker, AuditLogger $audit_logger ) {
		$this->post_manager       = $post_manager;
		$this->permission_checker = $permission_checker;
		$this->audit_logger       = $audit_logger;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /posts/search-content - Search posts by content substring.
		register_rest_route(
			'ai-gateway/v1',
			'/posts/search-content',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_content_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'q'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * GET /posts/search-content - Search posts by content substring.
	 *
	 * Searches for exact substring matches in post_content using SQL LIKE.
	 * Useful for finding posts containing specific shortcodes or strings.
	 *
	 * @param WP_REST_Request $request Request object with 'q' (required), 'page', 'per_page' params.
	 *
	 * @return WP_REST_Response
	 */
	public function search_content_callback( WP_REST_Request $request ) {
		try {
			$q = $request->get_param( 'q' );

			if ( empty( $q ) ) {
				return ResponseFormatter::error(
					'missing_search_term',
					'The q parameter is required and cannot be empty.',
					array(),
					400
				);
			}

			$args = array(
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			);

			$result = $this->post_manager->search_posts_by_content( $q, $args );

			return ResponseFormatter::success( $result, 200 );
		} catch ( GatewayException $e ) {
			return ResponseFormatter::error(
				$e->get_code(),
				$e->getMessage(),
				array(),
				400
			);
		}
	}
}
