<?php
/**
 * Integration Tests for Global Styles JSON Endpoints
 *
 * Tests GET /global-styles/json and PATCH /global-styles/json endpoints
 * including CSS stripping, merge behavior, and CSS protection.
 *
 * @package AI_Gateway\Tests\Integration\API
 */

namespace AI_Gateway\Tests\Integration\API;

/**
 * Test_GlobalStylesJsonEndpoints
 *
 * Integration test class for Global Styles JSON REST endpoints.
 */
class Test_GlobalStylesJsonEndpoints extends \WP_UnitTestCase {
	/**
	 * API key for testing.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Test global styles post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Original CSS value for validation.
	 *
	 * @var string
	 */
	private $original_css = 'body { background: #282828; }';

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create admin user.
		$user_id = wp_insert_user( array(
			'user_login' => 'gs_json_admin_' . uniqid(),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		) );
		wp_set_current_user( $user_id );

		// Generate API key.
		$this->api_key = bin2hex( random_bytes( 32 ) );
		$hash          = hash( 'sha256', $this->api_key );
		update_option( 'ai_gateway_api_key_hash', $hash );

		// Register routes via rest_api_init hook then reset the server.
		add_action( 'rest_api_init', function() {
			$loader = new \AI_Gateway\PluginLoader();
			$loader->register_rest_routes();
		}, 99 );
		global $wp_rest_server;
		$wp_rest_server = null;

		// Clean up existing global styles posts.
		$this->cleanup_global_styles_posts();

		// Create test wp_global_styles post.
		$stylesheet = wp_get_theme()->get_stylesheet();
		$styles     = array(
			'version'  => 3,
			'styles'   => array(
				'css'        => $this->original_css,
				'typography' => array( 'fontFamily' => 'Inter' ),
				'color'      => array( 'background' => '#282828', 'text' => '#eee' ),
			),
			'settings' => array(
				'layout' => array( 'contentSize' => '640px', 'wideSize' => '960px' ),
			),
		);

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
		delete_option( 'ai_gateway_api_key_hash' );
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'ai_gateway_rate_limit_%'
			)
		);
		parent::tearDown();
	}

	/**
	 * Remove all wp_global_styles posts.
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
	 * Test: GET /global-styles/json returns styles without CSS field.
	 */
	public function test_get_json_returns_styles_without_css() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/json' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'styles', $data['data'] );

		// The styles data contains the parsed JSON (version, styles, settings).
		// styles.styles should NOT have css key.
		$styles_json = $data['data']['styles'];
		if ( isset( $styles_json['styles'] ) ) {
			$this->assertArrayNotHasKey( 'css', $styles_json['styles'] );
		}
	}

	/**
	 * Test: PATCH /global-styles/json merges partial payload.
	 */
	public function test_patch_json_merges_partial_payload() {
		$request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/global-styles/json' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array(
			'styles' => array(
				'typography' => array( 'fontSize' => '18px' ),
			),
		) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test: PATCH /global-styles/json blocks CSS field from overwriting.
	 */
	public function test_patch_json_blocks_css_field() {
		$request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/global-styles/json' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array(
			'styles' => array(
				'css'   => 'HACK_CSS_OVERRIDE',
				'color' => array( 'text' => '#000' ),
			),
		) ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// Read back full styles and verify CSS is unchanged.
		$manager = new \AI_Gateway\ContentManagement\GlobalStylesManager();
		$full    = $manager->get_full_styles();

		$this->assertSame( $this->original_css, $full['styles']['css'] );
	}

	/**
	 * Test: GET /global-styles/json returns settings field.
	 */
	public function test_get_json_returns_settings() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/json' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );

		$styles_json = $data['data']['styles'];
		$this->assertArrayHasKey( 'settings', $styles_json );
	}
}
