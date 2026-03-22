<?php
/**
 * Unit Tests for GlobalStylesManager
 *
 * Tests get_styles_json() CSS stripping and merge_styles_json() deep merge
 * with CSS protection for the v0.4.0 /global-styles/json endpoints.
 *
 * @package AI_Gateway\Tests\Unit\ContentManagement
 */

namespace AI_Gateway\Tests\Unit\ContentManagement;

use AI_Gateway\ContentManagement\GlobalStylesManager;
use WP_Error;

/**
 * Test_GlobalStylesManager
 *
 * Unit test class for GlobalStylesManager JSON methods.
 */
class Test_GlobalStylesManager extends \WP_UnitTestCase {
	/**
	 * GlobalStylesManager instance.
	 *
	 * @var GlobalStylesManager
	 */
	private $manager;

	/**
	 * Test global styles post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Original CSS content for tests.
	 *
	 * @var string
	 */
	private $original_css = 'body { background: red; }';

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->manager = new GlobalStylesManager();

		// Clean up any existing wp_global_styles posts for the test theme.
		$this->cleanup_global_styles_posts();

		// Create a wp_global_styles post with JSON content.
		$styles = array(
			'version'  => 3,
			'styles'   => array(
				'css'        => $this->original_css,
				'typography' => array( 'fontFamily' => 'Inter' ),
				'color'      => array( 'background' => '#282828' ),
			),
			'settings' => array(
				'layout' => array( 'contentSize' => '640px' ),
			),
		);

		$stylesheet = wp_get_theme()->get_stylesheet();

		$this->post_id = wp_insert_post( array(
			'post_type'    => 'wp_global_styles',
			'post_content' => wp_json_encode( $styles ),
			'post_status'  => 'publish',
			'post_name'    => 'wp-global-styles-' . sanitize_title( $stylesheet ),
			'post_title'   => 'Custom Styles',
		) );

		wp_set_object_terms( $this->post_id, $stylesheet, 'wp_theme' );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tearDown(): void {
		$this->cleanup_global_styles_posts();
		parent::tearDown();
	}

	/**
	 * Remove all wp_global_styles posts for the current theme.
	 */
	private function cleanup_global_styles_posts() {
		$posts = get_posts( array(
			'post_type'   => 'wp_global_styles',
			'post_status' => 'any',
			'numberposts' => -1,
		) );

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * Test: get_styles_json strips the styles.css field.
	 */
	public function test_get_styles_json_strips_css_field() {
		$result = $this->manager->get_styles_json();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'styles', $result );
		// CSS should be stripped.
		$this->assertArrayNotHasKey( 'css', $result['styles'] );
		// Typography should still be present.
		$this->assertArrayHasKey( 'typography', $result['styles'] );
		$this->assertSame( 'Inter', $result['styles']['typography']['fontFamily'] );
	}

	/**
	 * Test: get_styles_json preserves settings.
	 */
	public function test_get_styles_json_preserves_settings() {
		$result = $this->manager->get_styles_json();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'settings', $result );
		$this->assertSame( '640px', $result['settings']['layout']['contentSize'] );
	}

	/**
	 * Test: merge_styles_json deep merges partial payload.
	 */
	public function test_merge_styles_json_deep_merges() {
		$partial = array(
			'styles' => array(
				'typography' => array( 'fontSize' => '16px' ),
			),
		);

		$result = $this->manager->merge_styles_json( $partial );

		// Should succeed (returns metadata array).
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'last_modified', $result );

		// Read back full styles and verify merge.
		$full = $this->manager->get_full_styles();
		$this->assertIsArray( $full );

		// Original fontFamily should be preserved.
		$this->assertSame( 'Inter', $full['styles']['typography']['fontFamily'] );
		// New fontSize should be added.
		$this->assertSame( '16px', $full['styles']['typography']['fontSize'] );
	}

	/**
	 * Test: merge_styles_json strips incoming styles.css field.
	 */
	public function test_merge_styles_json_strips_incoming_css() {
		$partial = array(
			'styles' => array(
				'css'   => 'EVIL CSS OVERRIDE',
				'color' => array( 'text' => '#fff' ),
			),
		);

		$result = $this->manager->merge_styles_json( $partial );
		$this->assertIsArray( $result );

		// Read back and verify CSS was NOT overwritten.
		$full = $this->manager->get_full_styles();
		$this->assertSame( $this->original_css, $full['styles']['css'] );
		// But the color merge should have taken effect.
		$this->assertSame( '#fff', $full['styles']['color']['text'] );
	}

	/**
	 * Test: merge_styles_json preserves original CSS after merge.
	 */
	public function test_merge_styles_json_preserves_original_css() {
		$partial = array(
			'styles' => array(
				'typography' => array( 'lineHeight' => '1.8' ),
			),
		);

		$this->manager->merge_styles_json( $partial );

		// Read back and verify original CSS is untouched.
		$full = $this->manager->get_full_styles();
		$this->assertSame( $this->original_css, $full['styles']['css'] );
	}

	/**
	 * Test: get_styles_json returns empty-ish structure when no post exists.
	 */
	public function test_get_styles_json_returns_empty_when_no_post() {
		// Delete all global styles posts.
		$this->cleanup_global_styles_posts();

		$result = $this->manager->get_styles_json();

		// Should return array (not WP_Error), with styles key.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'styles', $result );
	}
}
