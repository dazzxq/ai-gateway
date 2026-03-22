<?php
/**
 * Integration Tests for Observability Endpoints
 *
 * @package AI_Gateway\Tests\Integration
 */

namespace AI_Gateway\Tests\Integration;

/**
 * Test_ObservabilityEndpoints
 *
 * Tests system-info, health, and logs observability endpoints via REST API.
 */
class Test_ObservabilityEndpoints extends \WP_UnitTestCase {
	/**
	 * Test API key for testing.
	 *
	 * @var string
	 */
	private $test_api_key;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Generate a test API key.
		$this->test_api_key = bin2hex( random_bytes( 32 ) );
		$hash               = hash( 'sha256', $this->test_api_key );
		update_option( 'ai_gateway_api_key_hash', $hash );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'ai_gateway_api_key_hash' );
	}

	/**
	 * Test 1: GET /system-info endpoint with auth.
	 *
	 * @test
	 */
	public function test_get_system_info_endpoint_authenticated() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/system-info' );
		$request->set_header( 'Authorization', 'Bearer ' . $this->test_api_key );

		$response = rest_do_request( $request );

		$this->assertNotInstanceOf( '\WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'wordpress_version', $data['data'] );
	}

	/**
	 * Test 2: GET /health endpoint without auth.
	 *
	 * @test
	 */
	public function test_get_health_endpoint_no_auth_required() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/health' );

		$response = rest_do_request( $request );

		$this->assertNotInstanceOf( '\WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'checks', $data );
	}

	/**
	 * Test 3: GET /health returns valid status.
	 *
	 * @test
	 */
	public function test_health_endpoint_returns_valid_status() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/health' );

		$response = rest_do_request( $request );

		$data = $response->get_data();
		$this->assertContains( $data['status'], array( 'healthy', 'degraded', 'unhealthy' ) );
	}

	/**
	 * Test 4: GET /logs endpoint requires auth.
	 *
	 * @test
	 */
	public function test_get_logs_endpoint_requires_auth() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/logs' );

		$response = rest_do_request( $request );

		// Should return an error - either 401 (Unauthorized) or 403 (Forbidden) or WP_Error
		if ( is_wp_error( $response ) ) {
			$this->assertInstanceOf( '\WP_Error', $response );
		} else {
			$status = $response->get_status();
			$this->assertContains( $status, array( 401, 403 ), "Expected 401 or 403, got $status" );
		}
	}

	/**
	 * Test 5: GET /system-info requires auth.
	 *
	 * @test
	 */
	public function test_get_system_info_requires_auth() {
		$request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/system-info' );

		$response = rest_do_request( $request );

		// Should return an error - either 401 (Unauthorized) or 403 (Forbidden) or WP_Error
		if ( is_wp_error( $response ) ) {
			$this->assertInstanceOf( '\WP_Error', $response );
		} else {
			$status = $response->get_status();
			$this->assertContains( $status, array( 401, 403 ), "Expected 401 or 403, got $status" );
		}
	}
}
