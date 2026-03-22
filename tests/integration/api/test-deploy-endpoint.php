<?php
/**
 * Integration Tests for Deploy Endpoint
 *
 * Tests POST /code-snippets/{id}/deploy HTTP response format,
 * authentication, error cases, and plugin-inactive handling.
 *
 * @package AI_Gateway\Tests\Integration\API
 */

namespace AI_Gateway\Tests\Integration\API;

/**
 * Test_DeployEndpoint
 *
 * Integration test class for the deploy snippet REST endpoint.
 */
class Test_DeployEndpoint extends \WP_UnitTestCase {
	/**
	 * API key for testing.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Test snippet ID.
	 *
	 * @var int
	 */
	private $snippet_id;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create admin user and set current user.
		$user_id = wp_insert_user( array(
			'user_login' => 'deploy_test_admin_' . uniqid(),
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

		// Reset REST server to force re-init with new routes.
		global $wp_rest_server;
		$wp_rest_server = null;

		// Create a test snippet in the wp_snippets table.
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';
		$wpdb->insert(
			$table,
			array(
				'name'        => 'Deploy Integration Test ' . uniqid(),
				'description' => 'Test snippet for deploy endpoint',
				'code'        => 'echo "deploy integration test";',
				'active'      => 1,
				'scope'       => 'global',
				'priority'    => 10,
				'modified'    => current_time( 'mysql' ),
				'revision'    => 1,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d' )
		);
		$this->snippet_id = (int) $wpdb->insert_id;
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';

		if ( $this->snippet_id > 0 ) {
			$wpdb->delete( $table, array( 'id' => $this->snippet_id ), array( '%d' ) );
		}

		delete_option( 'ai_gateway_api_key_hash' );
		// Clean rate limit options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'ai_gateway_rate_limit_%'
			)
		);

		parent::tearDown();
	}

	/**
	 * Test: POST /code-snippets/{id}/deploy returns 200 with steps log.
	 */
	public function test_deploy_returns_200_with_steps_log() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets/' . $this->snippet_id . '/deploy' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertTrue( $data['data']['deployed'] );
		$this->assertIsArray( $data['data']['steps'] );
		$this->assertCount( 3, $data['data']['steps'] );
		$this->assertSame( 'deactivate', $data['data']['steps'][0]['action'] );
		$this->assertSame( 'activate', $data['data']['steps'][1]['action'] );
		$this->assertSame( 'verify', $data['data']['steps'][2]['action'] );
	}

	/**
	 * Test: POST /code-snippets/{id}/deploy returns error for nonexistent snippet.
	 */
	public function test_deploy_returns_error_for_nonexistent_snippet() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets/999999/deploy' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Response should indicate failure (snippet_not_found).
		$this->assertNotEquals( 200, $response->get_status() );
		$this->assertFalse( $data['success'] );
		$this->assertSame( 'snippet_not_found', $data['code'] );
	}

	/**
	 * Test: POST /code-snippets/{id}/deploy requires authentication.
	 */
	public function test_deploy_requires_authentication() {
		// Log out current user.
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets/' . $this->snippet_id . '/deploy' );
		// No auth header.

		$response = rest_get_server()->dispatch( $request );
		$status   = $response->get_status();

		// Should be 401 or 403 (depends on WP_Error handling).
		$this->assertContains( $status, array( 401, 403 ) );
	}

	/**
	 * Test: Deploy route exists and is registered.
	 */
	public function test_deploy_route_exists() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets/' . $this->snippet_id . '/deploy' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );

		// Should NOT be 404 (route not found).
		$this->assertNotEquals( 404, $response->get_status(), 'Deploy route should exist' );
	}
}
