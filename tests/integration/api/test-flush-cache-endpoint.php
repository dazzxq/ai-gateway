<?php
/**
 * Integration Tests for Flush Cache Endpoint
 *
 * Tests POST /system/flush-cache response structure, authentication,
 * and transient cleanup via REST dispatch.
 *
 * @package AI_Gateway\Tests\Integration\API
 */

namespace AI_Gateway\Tests\Integration\API;

/**
 * Test_FlushCacheEndpoint
 *
 * Integration test class for the flush-cache REST endpoint.
 */
class Test_FlushCacheEndpoint extends \WP_UnitTestCase {
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

		// Create admin user.
		$user_id = wp_insert_user( array(
			'user_login' => 'flush_cache_admin_' . uniqid(),
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
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tearDown(): void {
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
	 * Test: POST /system/flush-cache returns 200 with component results.
	 */
	public function test_flush_cache_returns_200_with_results() {
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/system/flush-cache' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'speedycache', $data['data'] );
		$this->assertArrayHasKey( 'object_cache', $data['data'] );
		$this->assertArrayHasKey( 'transients', $data['data'] );
	}

	/**
	 * Test: POST /system/flush-cache requires authentication.
	 */
	public function test_flush_cache_requires_authentication() {
		// Log out current user.
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/system/flush-cache' );
		// No auth header.

		$response = rest_get_server()->dispatch( $request );
		$status   = $response->get_status();

		$this->assertContains( $status, array( 401, 403 ) );
	}

	/**
	 * Test: POST /system/flush-cache clears WP transients.
	 */
	public function test_flush_cache_clears_wp_transients() {
		// Set some test transients.
		set_transient( 'flush_cache_integration_test_1', 'val1', 3600 );
		set_transient( 'flush_cache_integration_test_2', 'val2', 3600 );

		// Verify they exist.
		$this->assertSame( 'val1', get_transient( 'flush_cache_integration_test_1' ) );
		$this->assertSame( 'val2', get_transient( 'flush_cache_integration_test_2' ) );

		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/system/flush-cache' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertGreaterThanOrEqual( 2, $data['data']['transients']['deleted'] );

		// Verify transients are gone.
		$this->assertFalse( get_transient( 'flush_cache_integration_test_1' ) );
		$this->assertFalse( get_transient( 'flush_cache_integration_test_2' ) );
	}
}
