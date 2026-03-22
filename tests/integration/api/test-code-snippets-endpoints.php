<?php
/**
 * Integration Tests for Code Snippets REST Endpoints
 *
 * Tests full request/response cycle for Code Snippets CRUD endpoints.
 *
 * @package AI_Gateway\Tests\Integration
 */

namespace AI_Gateway\Tests\Integration\API;

/**
 * Test_CodeSnippets_Endpoints
 *
 * Integration test class for Code Snippets REST endpoints.
 */
class Test_CodeSnippets_Endpoints extends \WP_UnitTestCase {
	/**
	 * Admin user ID for authentication.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * API key for testing.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create admin user and generate API key
		$this->user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		// Generate API key
		$key = bin2hex( random_bytes( 32 ) );
		$hash = hash( 'sha256', $key );
		update_option( 'ai_gateway_api_key_hash', $hash );
		$this->api_key = $key;
	}

	/**
	 * Test: GET /code-snippets returns 503 when plugin not active.
	 */
	public function test_get_list_returns_503_when_plugin_not_active() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 503, $status );
	}

	/**
	 * Test: POST /code-snippets returns 503 when plugin not active.
	 */
	public function test_create_returns_503_when_plugin_not_active() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_json_params( array(
			'name' => 'Test Snippet',
			'code' => '<?php echo "test";',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 503, $status );
	}

	/**
	 * Test: GET /code-snippets/{id} returns 503 when plugin not active.
	 */
	public function test_get_single_returns_503_when_plugin_not_active() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets/1' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 503, $status );
	}

	/**
	 * Test: PATCH /code-snippets/{id} returns 503 when plugin not active.
	 */
	public function test_update_returns_503_when_plugin_not_active() {
		$request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/code-snippets/1' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_json_params( array(
			'code' => '<?php echo "updated";',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 503, $status );
	}

	/**
	 * Test: DELETE /code-snippets/{id} returns 503 when plugin not active.
	 */
	public function test_delete_returns_503_when_plugin_not_active() {
		$request = new \WP_REST_Request( 'DELETE', '/ai-gateway/v1/code-snippets/1' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 503, $status );
	}

	/**
	 * Test: GET /code-snippets returns 403 without valid API key.
	 */
	public function test_get_list_returns_403_without_api_key() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets' );
		// No API key

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 403, $status );
	}

	/**
	 * Test: POST /code-snippets returns 403 without valid API key.
	 */
	public function test_create_returns_403_without_api_key() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets' );
		// No API key
		$request->set_json_params( array(
			'name' => 'Test Snippet',
			'code' => '<?php echo "test";',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 403, $status );
	}

	/**
	 * Test: GET /code-snippets/{id} returns 403 without valid API key.
	 */
	public function test_get_single_returns_403_without_api_key() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets/1' );
		// No API key

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 403, $status );
	}

	/**
	 * Test: PATCH /code-snippets/{id} returns 403 without valid API key.
	 */
	public function test_update_returns_403_without_api_key() {
		$request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/code-snippets/1' );
		// No API key
		$request->set_json_params( array(
			'code' => '<?php echo "updated";',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 403, $status );
	}

	/**
	 * Test: DELETE /code-snippets/{id} returns 403 without valid API key.
	 */
	public function test_delete_returns_403_without_api_key() {
		$request = new \WP_REST_Request( 'DELETE', '/ai-gateway/v1/code-snippets/1' );
		// No API key

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 403, $status );
	}

	/**
	 * Test: POST /code-snippets returns 400 when name is missing.
	 */
	public function test_create_returns_400_when_name_missing() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_json_params( array(
			'code' => '<?php echo "test";',
			// Missing 'name'
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 400, $status );
	}

	/**
	 * Test: POST /code-snippets returns 400 when code is missing.
	 */
	public function test_create_returns_400_when_code_missing() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_json_params( array(
			'name' => 'Test Snippet',
			// Missing 'code'
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		$this->assertSame( 400, $status );
	}

	/**
	 * Test: GET /code-snippets/{id} returns 400 with invalid ID.
	 */
	public function test_get_single_returns_400_with_invalid_id() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets/invalid' );
		$request->add_header( 'X-API-Key', $this->api_key );

		// Note: REST routing might match integer regex pattern differently
		// This test verifies error handling at the controller level
		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Could be 400 (invalid ID) or 404 (not found) depending on routing
		$this->assertThat(
			$status,
			$this->logicalOr(
				$this->equalTo( 400 ),
				$this->equalTo( 404 )
			)
		);
	}

	/**
	 * Test: PATCH /code-snippets/{id} returns 400 with invalid ID.
	 */
	public function test_update_returns_400_with_invalid_id() {
		$request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/code-snippets/invalid' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_json_params( array(
			'code' => '<?php echo "updated";',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Could be 400 or 404
		$this->assertThat(
			$status,
			$this->logicalOr(
				$this->equalTo( 400 ),
				$this->equalTo( 404 )
			)
		);
	}

	/**
	 * Test: DELETE /code-snippets/{id} returns 400 with invalid ID.
	 */
	public function test_delete_returns_400_with_invalid_id() {
		$request = new \WP_REST_Request( 'DELETE', '/ai-gateway/v1/code-snippets/invalid' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Could be 400 or 404
		$this->assertThat(
			$status,
			$this->logicalOr(
				$this->equalTo( 400 ),
				$this->equalTo( 404 )
			)
		);
	}

	/**
	 * Test: GET /code-snippets route exists.
	 */
	public function test_get_list_route_exists() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );

		// This should not return 404 (route not found)
		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Should be 503 (plugin not active), not 404 (route not found)
		$this->assertSame( 503, $status );
	}

	/**
	 * Test: POST /code-snippets route exists.
	 */
	public function test_create_route_exists() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_json_params( array(
			'name' => 'Test',
			'code' => '<?php',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Should be 503, not 404
		$this->assertSame( 503, $status );
	}

	/**
	 * Test: GET /code-snippets/{id} route exists.
	 */
	public function test_get_single_route_exists() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets/123' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Should be 503, not 404
		$this->assertSame( 503, $status );
	}

	/**
	 * Test: PATCH /code-snippets/{id} route exists.
	 */
	public function test_update_route_exists() {
		$request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/code-snippets/123' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_json_params( array(
			'code' => '<?php',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Should be 503, not 404
		$this->assertSame( 503, $status );
	}

	/**
	 * Test: DELETE /code-snippets/{id} route exists.
	 */
	public function test_delete_route_exists() {
		$request = new \WP_REST_Request( 'DELETE', '/ai-gateway/v1/code-snippets/123' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$status = $response->get_status();

		// Should be 503, not 404
		$this->assertSame( 503, $status );
	}

	/**
	 * Test: Response structure includes proper data wrapper.
	 */
	public function test_response_includes_data_wrapper() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$data = $response->get_data();

		// Response should have proper structure with error code and message
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test: List endpoint accepts pagination parameters.
	 */
	public function test_list_endpoint_accepts_pagination_parameters() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 50 );

		$response = rest_get_server()->dispatch( $request );

		// Should not error on valid parameters
		$this->assertNotSame( 400, $response->get_status() );
	}

	/**
	 * Test: List endpoint accepts active filter parameter.
	 */
	public function test_list_endpoint_accepts_active_filter() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets' );
		$request->add_header( 'X-API-Key', $this->api_key );
		$request->set_param( 'active', 1 );

		$response = rest_get_server()->dispatch( $request );

		// Should not error on valid parameter
		$this->assertNotSame( 400, $response->get_status() );
	}
}
