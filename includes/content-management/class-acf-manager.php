<?php
/**
 * ACF Manager Class
 *
 * Handles ACF Pro field group and field value operations.
 *
 * @package AI_Gateway\ContentManagement
 */

namespace AI_Gateway\ContentManagement;

use AI_Gateway\Exceptions\GatewayException;

/**
 * ACFManager
 *
 * Manages ACF Pro field operations with availability checking.
 */
class ACFManager {
	/**
	 * Whether ACF Pro is active.
	 *
	 * @var bool
	 */
	private $acf_active = false;

	/**
	 * Constructor
	 *
	 * Checks ACF Pro availability.
	 */
	public function __construct() {
		// Check if ACF Pro functions are available.
		$this->acf_active = function_exists( 'acf_get_field_groups' );
	}

	/**
	 * Check if ACF Pro is active.
	 *
	 * @return bool True if ACF Pro is active.
	 */
	public function is_active() {
		return $this->acf_active;
	}

	/**
	 * Get all field groups with field definitions.
	 *
	 * @return array Array of field groups with their fields.
	 * @throws GatewayException If ACF Pro not active.
	 */
	public function get_field_groups() {
		if ( ! $this->acf_active ) {
			throw new GatewayException(
				'ACF Pro is not active',
				'acf_pro_inactive',
				null
			);
		}

		$groups = acf_get_field_groups();
		$result = array();

		foreach ( $groups as $group ) {
			$fields     = acf_get_fields( $group['ID'] );
			$field_data = array();

			if ( $fields ) {
				foreach ( $fields as $field ) {
					$field_data[] = array(
						'name'  => $field['name'],
						'label' => $field['label'],
						'type'  => $field['type'],
					);
				}
			}

			$result[] = array(
				'id'     => $group['ID'],
				'title'  => $group['title'],
				'fields' => $field_data,
			);
		}

		return $result;
	}

	/**
	 * Get single field value for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field_name Field name.
	 *
	 * @return mixed Field value (formatted).
	 * @throws GatewayException When ACF inactive or field not found.
	 */
	public function get_field_value( $post_id, $field_name ) {
		if ( ! $this->acf_active ) {
			throw new GatewayException(
				'ACF Pro is not active',
				'acf_pro_inactive'
			);
		}

		$post_id    = intval( $post_id );
		$field_name = sanitize_text_field( $field_name );

		// Get formatted field value (ACF handles all the processing).
		$value = get_field( $field_name, $post_id );

		if ( false === $value ) {
			throw new GatewayException(
				'Field not found: ' . esc_html( $field_name ),
				'field_not_found'
			);
		}

		return $value;
	}

	/**
	 * Get all ACF fields for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Key-value array of all ACF fields
	 */
	public function get_fields( $post_id ) {
		if ( ! $this->acf_active ) {
			return array();
		}

		$post_id = intval( $post_id );
		$fields  = array();

		// Get all field groups for this post type.
		$groups = acf_get_field_groups(
			array(
				'post_type' => get_post_type( $post_id ),
			)
		);

		if ( empty( $groups ) ) {
			return $fields;
		}

		// Collect all field values.
		foreach ( $groups as $group ) {
			$group_fields = acf_get_fields( $group['ID'] );
			if ( $group_fields ) {
				foreach ( $group_fields as $field ) {
					$value = get_field( $field['name'], $post_id );
					if ( false !== $value ) {
						$fields[ $field['name'] ] = $value;
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Update single ACF field value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field_name Field name.
	 * @param mixed  $value New value (can be string, array, object).
	 *
	 * @return mixed Updated field value.
	 * @throws GatewayException When ACF inactive, field not found, or update fails.
	 */
	public function update_field_value( $post_id, $field_name, $value ) {
		if ( ! $this->acf_active ) {
			throw new GatewayException(
				'ACF Pro is not active',
				'acf_pro_inactive'
			);
		}

		$post_id    = intval( $post_id );
		$field_name = sanitize_text_field( $field_name );

		// Validate field exists.
		$field = get_field_object( $field_name, $post_id );
		if ( ! $field ) {
			throw new GatewayException(
				'Field not found: ' . esc_html( $field_name ),
				'invalid_field'
			);
		}

		// Update field via ACF (handles all field type processing).
		$updated = update_field( $field_name, $value, $post_id );

		if ( ! $updated ) {
			throw new GatewayException(
				'Failed to update field: ' . esc_html( $field_name ),
				'field_update_failed'
			);
		}

		// Return updated value via get_field (formatted).
		return get_field( $field_name, $post_id );
	}
}
