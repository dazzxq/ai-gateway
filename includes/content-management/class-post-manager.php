<?php
/**
 * Post Manager Class
 *
 * Handles post listing, reading, and updating via WP_Query.
 *
 * @package AI_Gateway\ContentManagement
 */

namespace AI_Gateway\ContentManagement;

use AI_Gateway\Exceptions\GatewayException;

/**
 * PostManager
 *
 * Manages WordPress post operations with pagination, filtering, and ACF integration.
 */
class PostManager {
	/**
	 * ACF Manager instance for field integration.
	 *
	 * @var ACFManager|null
	 */
	private $acf_manager;

	/**
	 * Constructor
	 *
	 * @param ACFManager $acf_manager Optional ACF Manager instance.
	 */
	public function __construct( ?ACFManager $acf_manager = null ) {
		$this->acf_manager = $acf_manager;
	}

	/**
	 * List posts with pagination and filters.
	 *
	 * @param array $args Query arguments containing fields.
	 *   - page (int): Page number (default 1).
	 *   - per_page (int): Items per page (default 20, max 100).
	 *   - status (string): Post status or comma-separated statuses.
	 *   - search (string): Search query in title/excerpt.
	 *   - sort (string): Sort option (date_desc, date_asc, title_asc, title_desc).
	 *
	 * @return array List of posts with pagination metadata.
	 * @throws GatewayException When invalid arguments provided.
	 */
	public function get_posts( $args = array() ) {
		// Validate and set defaults.
		$page = isset( $args['page'] ) ? intval( $args['page'] ) : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		$per_page = isset( $args['per_page'] ) ? intval( $args['per_page'] ) : 20;
		if ( $per_page > 100 ) {
			$per_page = 100;
		}
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		// Build WP_Query arguments.
		$query_args = array(
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_type'      => array( 'post', 'page' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Handle status filter.
		if ( ! empty( $args['status'] ) ) {
			$statuses = array_map( 'trim', explode( ',', $args['status'] ) );
			// Validate statuses are real WordPress statuses.
			$valid_statuses = array_keys( get_post_statuses() );
			$statuses       = array_intersect( $statuses, $valid_statuses );
			if ( ! empty( $statuses ) ) {
				$query_args['post_status'] = $statuses;
			}
		} else {
			// Default to published only.
			$query_args['post_status'] = 'publish';
		}

		// Handle search filter.
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		// Handle sorting.
		if ( ! empty( $args['sort'] ) ) {
			switch ( $args['sort'] ) {
				case 'date_asc':
					$query_args['order'] = 'ASC';
					break;
				case 'title_asc':
					$query_args['orderby'] = 'title';
					$query_args['order']   = 'ASC';
					break;
				case 'title_desc':
					$query_args['orderby'] = 'title';
					$query_args['order']   = 'DESC';
					break;
				case 'date_desc':
				default:
					// Already set above.
					break;
			}
		}

		// Execute query.
		$query = new \WP_Query( $query_args );

		// Build response with minimal post data.
		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'status'    => $post->post_status,
				'author_id' => intval( $post->post_author ),
				'created'   => $post->post_date_gmt,
				'modified'  => $post->post_modified_gmt,
			);
		}

		// Return with pagination metadata.
		return array(
			'posts'      => $posts,
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => intval( $query->found_posts ),
				'total_pages' => intval( $query->max_num_pages ),
			),
		);
	}

	/**
	 * Get single post with full details including content and ACF fields.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Post object with rendered content and metadata.
	 * @throws GatewayException When post not found or invalid ID.
	 */
	public function get_post( $post_id ) {
		$post_id = intval( $post_id );
		if ( $post_id < 1 ) {
			throw new GatewayException( 'Invalid post ID', 'invalid_post_id' );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			throw new GatewayException( 'Post not found', 'post_not_found' );
		}

		// Build full post response.
		$response = array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'status'    => $post->post_status,
			'author_id' => intval( $post->post_author ),
			'created'   => $post->post_date_gmt,
			'modified'  => $post->post_modified_gmt,
			'excerpt'   => $post->post_excerpt,
			// Render content HTML from block markup.
			'content'   => apply_filters( 'the_content', $post->post_content ),
			'meta'      => get_post_meta( $post->ID ),
		);

		// Include ACF fields if ACF Pro is active.
		if ( $this->acf_manager && $this->acf_manager->is_active() ) {
			$response['acf_fields'] = $this->acf_manager->get_fields( $post->ID );
		}

		return $response;
	}

	/**
	 * Search posts by exact substring match in post_content.
	 *
	 * Uses SQL LIKE query for exact substring matching, unlike WP_Query's 's' parameter
	 * which does fulltext-style search across title+content+excerpt.
	 *
	 * @param string $search_term The substring to search for in post_content.
	 * @param array  $args        Optional arguments.
	 *   - page (int): Page number (default 1).
	 *   - per_page (int): Items per page (default 100, max 500).
	 *
	 * @return array Array containing 'posts', 'total', 'page', 'per_page'.
	 * @throws GatewayException If search term is empty.
	 */
	public function search_posts_by_content( $search_term, $args = array() ) {
		global $wpdb;

		if ( empty( $search_term ) ) {
			throw new GatewayException( 'Search term cannot be empty', 'empty_search_term' );
		}

		// Validate and set defaults.
		$page = isset( $args['page'] ) ? intval( $args['page'] ) : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		$per_page = isset( $args['per_page'] ) ? intval( $args['per_page'] ) : 100;
		if ( $per_page > 500 ) {
			$per_page = 500;
		}
		if ( $per_page < 1 ) {
			$per_page = 100;
		}

		$offset = ( $page - 1 ) * $per_page;

		// Build the LIKE pattern using esc_like for safety.
		$like_pattern = '%' . $wpdb->esc_like( $search_term ) . '%';

		// Count total matching posts.
		$total = intval(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type IN ('post','page') AND post_status = 'publish'",
					$like_pattern
				)
			)
		);

		// Fetch matching posts with pagination.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_status, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type IN ('post','page') AND post_status = 'publish' ORDER BY ID ASC LIMIT %d OFFSET %d",
				$like_pattern,
				$per_page,
				$offset
			)
		);

		// Build response array.
		$posts = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$posts[] = array(
					'id'      => intval( $row->ID ),
					'title'   => $row->post_title,
					'type'    => $row->post_type,
					'status'  => $row->post_status,
					'content' => $row->post_content,
				);
			}
		}

		return array(
			'posts'    => $posts,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Update post with validation.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data Data to update.
	 *   - title (string).
	 *   - content (string).
	 *   - excerpt (string).
	 *   - status (string).
	 *
	 * @return array Updated post object.
	 * @throws GatewayException When post not found or update fails.
	 */
	public function update_post( $post_id, $data ) {
		$post_id = intval( $post_id );
		if ( $post_id < 1 ) {
			throw new GatewayException( 'Invalid post ID', 'invalid_post_id' );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			throw new GatewayException( 'Post not found', 'post_not_found' );
		}

		// Validate and prepare update data.
		$update_data = array(
			'ID' => $post_id,
		);

		// Only allow specific fields.
		$allowed_fields = array( 'title', 'content', 'excerpt', 'status' );
		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				switch ( $field ) {
					case 'title':
						$update_data['post_title'] = sanitize_text_field( $data['title'] );
						break;
					case 'content':
						$update_data['post_content'] = wp_kses_post( $data['content'] );
						break;
					case 'excerpt':
						$update_data['post_excerpt'] = sanitize_text_field( $data['excerpt'] );
						break;
					case 'status':
						// Validate status.
						$valid_statuses = array_keys( get_post_statuses() );
						if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
							throw new GatewayException(
								'Invalid post status: ' . esc_html( $data['status'] ),
								'invalid_status'
							);
						}
						$update_data['post_status'] = $data['status'];
						break;
				}
			}
		}

		// Update post.
		$result = wp_update_post( $update_data, true );
		if ( is_wp_error( $result ) ) {
			throw new GatewayException(
				'Failed to update post: ' . esc_html( $result->get_error_message() ),
				'post_update_failed'
			);
		}

		// Return updated post detail.
		return $this->get_post( $post_id );
	}
}
