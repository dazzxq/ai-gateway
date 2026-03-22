<?php
/**
 * Global Styles Manager Class
 *
 * Handles read and write operations for WordPress Global Styles CSS
 * stored in the wp_global_styles post type (Site Editor > Styles > Additional CSS).
 *
 * @package AI_Gateway\ContentManagement
 */

namespace AI_Gateway\ContentManagement;

use WP_Error;

/**
 * GlobalStylesManager
 *
 * Manages Global Styles CSS content via the wp_global_styles post type.
 * This is the CSS managed through Site Editor > Styles > Additional CSS,
 * NOT the Customizer Additional CSS (which uses the custom_css post type).
 */
class GlobalStylesManager {
	/**
	 * Maximum CSS content size in bytes (256 KB).
	 *
	 * @var int
	 */
	private const MAX_CONTENT_SIZE = 256 * 1024;

	/**
	 * Constructor
	 *
	 * Initializes without dependencies.
	 */
	public function __construct() {
		// No external dependencies required.
	}

	/**
	 * Get the wp_global_styles post for the current theme.
	 *
	 * WordPress stores global styles per-theme in the wp_global_styles post type.
	 * The post_name is derived from the active theme's stylesheet.
	 *
	 * @return \WP_Post|null The global styles post or null if not found.
	 */
	public function get_global_styles_post() {
		$stylesheet = wp_get_theme()->get_stylesheet();

		// WordPress stores global styles with a specific naming convention.
		$posts = get_posts(
			array(
				'post_type'   => 'wp_global_styles',
				'post_status' => 'publish',
				'numberposts' => 1,
				'tax_query'   => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $stylesheet,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		return $posts[0];
	}

	/**
	 * Get the full styles JSON object from global styles.
	 *
	 * Returns the parsed JSON content of the wp_global_styles post,
	 * which contains all style settings including Additional CSS.
	 *
	 * @return array|WP_Error Parsed styles array or WP_Error on failure.
	 */
	public function get_full_styles() {
		$post = $this->get_global_styles_post();

		if ( ! $post ) {
			return array(
				'version'  => 3,
				'styles'   => array(),
				'settings' => array(),
			);
		}

		$content = $post->post_content;

		if ( empty( $content ) ) {
			return array(
				'version'  => 3,
				'styles'   => array(),
				'settings' => array(),
			);
		}

		$decoded = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Auto-repair: try to extract CSS from corrupted JSON and rebuild.
			$repaired = $this->attempt_repair( $content );
			if ( false !== $repaired ) {
				return $repaired;
			}

			return new WP_Error(
				'invalid_json',
				'Global styles content contains invalid JSON: ' . json_last_error_msg(),
				array( 'status' => 500 )
			);
		}

		return $decoded;
	}

	/**
	 * Get CSS from global styles.
	 *
	 * Reads the `styles.css` field from the wp_global_styles post JSON content.
	 * This is the "Additional CSS" field in Site Editor > Styles.
	 *
	 * @return string|WP_Error CSS content string or WP_Error on failure.
	 */
	public function get_css() {
		$styles = $this->get_full_styles();

		if ( is_wp_error( $styles ) ) {
			return $styles;
		}

		// The CSS is stored under styles.css in the JSON.
		if ( isset( $styles['styles']['css'] ) ) {
			return $styles['styles']['css'];
		}

		return '';
	}

	/**
	 * Update CSS in global styles.
	 *
	 * Updates the `styles.css` field in the wp_global_styles post JSON content.
	 *
	 * @param string $content The new CSS content.
	 *
	 * @return array|WP_Error Updated metadata on success, WP_Error on failure.
	 */
	public function update_css( $content ) {
		// Validate content.
		$validation_error = $this->validate_css_content( $content );
		if ( is_wp_error( $validation_error ) ) {
			return $validation_error;
		}

		// Get current full styles.
		$styles = $this->get_full_styles();
		if ( is_wp_error( $styles ) ) {
			return $styles;
		}

		// Ensure styles structure exists.
		if ( ! isset( $styles['styles'] ) ) {
			$styles['styles'] = array();
		}

		// Update the CSS field.
		$styles['styles']['css'] = $content;

		// Save back to post.
		return $this->save_styles( $styles );
	}

	/**
	 * Update the full styles JSON object.
	 *
	 * Replaces the entire content of the wp_global_styles post.
	 * Use with caution - this overwrites all style settings.
	 *
	 * @param mixed $styles The full styles array to save.
	 *
	 * @return array|WP_Error Updated metadata on success, WP_Error on failure.
	 */
	public function update_styles( $styles ) {
		if ( ! is_array( $styles ) ) {
			return new WP_Error(
				'invalid_styles',
				'Styles must be an array/object',
				array( 'status' => 400 )
			);
		}

		return $this->save_styles( $styles );
	}

	/**
	 * Get Global Styles JSON without the CSS field.
	 *
	 * Returns the parsed JSON content of the wp_global_styles post,
	 * with the large styles.css field stripped out for lightweight access.
	 *
	 * @return array|WP_Error Parsed styles array (without styles.css) or WP_Error on failure.
	 */
	public function get_styles_json() {
		$styles = $this->get_full_styles();

		if ( is_wp_error( $styles ) ) {
			return $styles;
		}

		// Strip the CSS field to avoid returning the 169KB+ Additional CSS.
		if ( isset( $styles['styles']['css'] ) ) {
			unset( $styles['styles']['css'] );
		}

		return $styles;
	}

	/**
	 * Deep-merge a partial JSON payload into existing Global Styles.
	 *
	 * Strips styles.css from the incoming payload to prevent accidental CSS
	 * overwrites. Preserves the original CSS field after merge.
	 *
	 * @param array $partial Partial styles array to merge.
	 *
	 * @return array|WP_Error Updated metadata on success, WP_Error on failure.
	 */
	public function merge_styles_json( array $partial ) {
		// Strip styles.css from incoming payload (locked decision).
		if ( isset( $partial['styles']['css'] ) ) {
			unset( $partial['styles']['css'] );
		}

		// Get current full styles.
		$current = $this->get_full_styles();
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		// Preserve the original CSS before merge.
		$original_css = isset( $current['styles']['css'] ) ? $current['styles']['css'] : null;

		// Deep merge: incoming values override current.
		$merged = array_replace_recursive( $current, $partial );

		// Restore original CSS after merge.
		if ( null !== $original_css ) {
			$merged['styles']['css'] = $original_css;
		}

		// Save via save_styles (uses $wpdb->update directly).
		return $this->save_styles( $merged );
	}

	/**
	 * Save styles JSON to the wp_global_styles post.
	 *
	 * @param array $styles The styles array to encode and save.
	 *
	 * @return array|WP_Error Metadata array on success, WP_Error on failure.
	 */
	protected function save_styles( $styles ) {
		$post = $this->get_global_styles_post();

		$json_content = wp_json_encode( $styles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		if ( false === $json_content ) {
			return new WP_Error(
				'json_encode_failed',
				'Failed to encode styles to JSON',
				array( 'status' => 500 )
			);
		}

		if ( $post ) {
			// Use $wpdb->update() directly to avoid wp_update_post() side effects:
			// - wp_unslash() strips backslashes from JSON \n, \t, \" escapes.
			// - wp_kses_post() encodes > as &gt; breaking CSS child selectors.
			global $wpdb;
			$updated = $wpdb->update(
				$wpdb->posts,
				array(
					'post_content'      => $json_content,
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql', true ),
				),
				array( 'ID' => $post->ID ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new WP_Error( 'db_error', 'Failed to update global styles post', array( 'status' => 500 ) );
			}

			// Clear caches.
			clean_post_cache( $post->ID );
			wp_cache_delete( $post->ID, 'posts' );
		} else {
			// Create new global styles post.
			$stylesheet = wp_get_theme()->get_stylesheet();

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'wp_global_styles',
					'post_title'   => 'Custom Styles',
					'post_name'    => 'wp-global-styles-' . sanitize_title( $stylesheet ),
					'post_content' => wp_slash( $json_content ),
					'post_status'  => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			// Set theme taxonomy.
			wp_set_object_terms( $post_id, $stylesheet, 'wp_theme' );
		}

		return $this->get_metadata();
	}

	/**
	 * Get metadata about the global styles.
	 *
	 * @return array Metadata array.
	 */
	public function get_metadata() {
		$post = $this->get_global_styles_post();

		if ( ! $post ) {
			return array(
				'last_modified'  => null,
				'content_length' => 0,
				'stylesheet'     => wp_get_theme()->get_stylesheet(),
				'post_id'        => null,
			);
		}

		return array(
			'last_modified'  => $post->post_modified_gmt,
			'content_length' => strlen( $post->post_content ),
			'stylesheet'     => wp_get_theme()->get_stylesheet(),
			'post_id'        => $post->ID,
		);
	}

	/**
	 * Validate CSS content.
	 *
	 * @param mixed $content The content to validate.
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_css_content( $content ) {
		if ( null === $content ) {
			return new WP_Error(
				'invalid_content',
				'CSS content cannot be null',
				array( 'status' => 400 )
			);
		}

		if ( ! is_string( $content ) ) {
			return new WP_Error(
				'invalid_content',
				'CSS content must be a string',
				array( 'status' => 400 )
			);
		}

		if ( strlen( $content ) > self::MAX_CONTENT_SIZE ) {
			return new WP_Error(
				'content_too_large',
				sprintf(
					'CSS content exceeds maximum size of %d KB',
					self::MAX_CONTENT_SIZE / 1024
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Attempt to repair corrupted Global Styles JSON.
	 *
	 * Extracts CSS from a corrupted JSON string where the CSS value
	 * contains unescaped characters (newlines, quotes, etc.).
	 *
	 * @param string $content The raw post_content that failed JSON decode.
	 *
	 * @return array|false Repaired styles array, or false if repair failed.
	 */
	private function attempt_repair( $content ) {
		// Pattern: {"styles":{"css":"...CSS..."}} or {"styles":{"css":"...CSS..."},...}.
		// The CSS part has unescaped chars. Extract it as raw string.
		$prefix = '{"styles":{"css":"';
		if ( strpos( $content, $prefix ) !== 0 ) {
			return false;
		}

		// Find where CSS ends — look for "}} or "}, pattern from the end.
		// The CSS is everything between the opening " after css: and the closing.
		$css_start = strlen( $prefix );

		// Try to find closing pattern from end of string.
		// Could be: ..."}} or ...","somekey":... or ..."},"settings":{...}}.
		$raw_css = null;

		// Strategy: find last occurrence of "}} which closes styles.css + styles + root.
		$pos = strrpos( $content, '"}}' );
		if ( false !== $pos && $pos > $css_start ) {
			$raw_css = substr( $content, $css_start, $pos - $css_start );
		}

		// Try "},"settings" pattern.
		if ( null === $raw_css ) {
			$pos = strrpos( $content, '"},"settings"' );
			if ( false !== $pos && $pos > $css_start ) {
				$raw_css = substr( $content, $css_start, $pos - $css_start );
			}
		}

		// Fallback: everything after prefix minus last 3 chars.
		if ( null === $raw_css ) {
			$raw_css = substr( $content, $css_start, -3 );
		}

		if ( empty( $raw_css ) ) {
			return false;
		}

		// The extracted CSS may have literal newlines (not \n escapes)
		// This is valid CSS, just wasn't valid inside JSON.
		return array(
			'version' => 3,
			'styles'  => array(
				'css' => $raw_css,
			),
		);
	}

	/**
	 * Repair corrupted Global Styles JSON and save.
	 *
	 * Reads the raw post_content, extracts CSS, re-encodes as valid JSON, saves.
	 *
	 * @return array|WP_Error Repair result with css_length, or WP_Error.
	 */
	public function repair() {
		$post = $this->get_global_styles_post();

		if ( ! $post ) {
			return new WP_Error( 'not_found', 'No global styles post found', array( 'status' => 404 ) );
		}

		$content = $post->post_content;
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_content', 'Global styles post is empty', array( 'status' => 400 ) );
		}

		// Check if already valid.
		$decoded = json_decode( $content, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return array(
				'status'     => 'already_valid',
				'css_length' => strlen( isset( $decoded['styles']['css'] ) ? $decoded['styles']['css'] : '' ),
				'post_id'    => $post->ID,
			);
		}

		// Attempt repair.
		$repaired = $this->attempt_repair( $content );
		if ( false === $repaired ) {
			return new WP_Error( 'repair_failed', 'Could not extract CSS from corrupted JSON', array( 'status' => 500 ) );
		}

		$css = isset( $repaired['styles']['css'] ) ? $repaired['styles']['css'] : '';

		// Save repaired content using $wpdb directly (avoids wp_update_post side effects).
		$json = wp_json_encode( $repaired, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->posts,
			array(
				'post_content'      => $json,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			),
			array( 'ID' => $post->ID ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_error', 'Failed to update global styles post', array( 'status' => 500 ) );
		}

		clean_post_cache( $post->ID );
		wp_cache_delete( $post->ID, 'posts' );

		return array(
			'status'     => 'repaired',
			'css_length' => strlen( $css ),
			'post_id'    => $post->ID,
		);
	}
}
