<?php
/**
 * ACF Controller Class
 *
 * REST endpoints for ACF field management.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use AI_Gateway\ContentManagement\ACFManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use AI_Gateway\Exceptions\GatewayException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ACFController
 *
 * Handles REST endpoints for ACF field groups and field value updates.
 */
class ACFController {
	/**
	 * ACF Manager instance.
	 *
	 * @var ACFManager
	 */
	private $acf_manager;

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
	 * @param ACFManager        $acf_manager        ACF Manager instance.
	 * @param PermissionChecker $permission_checker Permission Checker instance.
	 * @param AuditLogger       $audit_logger       Audit Logger instance.
	 */
	public function __construct( ACFManager $acf_manager, PermissionChecker $permission_checker, AuditLogger $audit_logger ) {
		$this->acf_manager        = $acf_manager;
		$this->permission_checker = $permission_checker;
		$this->audit_logger       = $audit_logger;
	}

	/**
	 * Register routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		$namespace = 'ai-gateway/v1';

		// GET /acf/field-groups - List ACF field groups.
		register_rest_route(
			$namespace,
			'/acf/field-groups',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_field_groups_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
			)
		);

		// PATCH /posts/{id}/acf/{field_name} - Update ACF field.
		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/acf/(?P<field_name>[^/]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_field_callback' ),
				'permission_callback' => array( $this->permission_checker, 'rest_callback' ),
				'args'                => array(
					'id'         => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'field_name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * GET /acf/field-groups - List ACF field groups.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function list_field_groups_callback( WP_REST_Request $request ) {
		try {
			$groups = $this->acf_manager->get_field_groups();

			return ResponseFormatter::success( $groups, 200 );
		} catch ( GatewayException $e ) {
			// ACF Pro not active returns 503.
			$status = ( $e->get_code() === 'acf_pro_inactive' ) ? 503 : 400;

			return ResponseFormatter::error(
				$e->get_code(),
				$e->getMessage(),
				array(),
				$status
			);
		}
	}

	/**
	 * PATCH /posts/{id}/acf/{field_name} - Update ACF field value.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @throws GatewayException When post not found, ACF inactive, or invalid field.
	 * @return WP_REST_Response
	 */
	public function update_field_callback( WP_REST_Request $request ) {
		try {
			$post_id    = intval( $request->get_param( 'id' ) );
			$field_name = sanitize_text_field( $request->get_param( 'field_name' ) );

			// Validate post exists.
			$post = get_post( $post_id );
			if ( ! $post ) {
				throw new GatewayException( 'Post not found', 'post_not_found' );
			}

			// Get value from JSON body.
			$data = json_decode( $request->get_body(), true );
			if ( ! is_array( $data ) || ! isset( $data['value'] ) ) {
				throw new GatewayException( 'Missing "value" field in request body', 'missing_value' );
			}

			$value = $data['value'];

			// Update field.
			$updated_value = $this->acf_manager->update_field_value( $post_id, $field_name, $value );

			// Log to audit trail.
			$resource_info = array(
				'resource_type'      => 'acf',
				'resource_id'        => $post_id . ':' . $field_name,
				'action_description' => 'Updated ACF field ' . $field_name . ' on post ' . $post_id,
			);

			$response = new WP_REST_Response( array(), 200 );
			$this->audit_logger->log_mutation( $request, $response, $resource_info );

			return ResponseFormatter::success(
				array( 'value' => $updated_value ),
				200
			);
		} catch ( GatewayException $e ) {
			$code   = $e->get_code();
			$status = 400;

			if ( 'post_not_found' === $code ) {
				$status = 404;
			} elseif ( 'acf_pro_inactive' === $code ) {
				$status = 503;
			} elseif ( 'invalid_field' === $code ) {
				$status = 400;
			}

			return ResponseFormatter::error(
				$code,
				$e->getMessage(),
				array(),
				$status
			);
		}
	}
}
