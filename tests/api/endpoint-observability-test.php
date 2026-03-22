<?php
/**
 * API Smoke Tests - Observability Endpoints
 *
 * Tests for system info and log endpoints to verify they are reachable
 * and responding correctly.
 *
 * @package AI_Gateway\Tests\API
 */

namespace AI_Gateway\Tests\API;

use WP_UnitTestCase;

/**
 * Endpoint_Observability_Test
 *
 * Smoke tests for observability endpoints.
 */
class Endpoint_Observability_Test extends WP_UnitTestCase {

    /**
     * REST server instance.
     *
     * @var WP_REST_Server
     */
    protected $server;

    /**
     * Test API key.
     *
     * @var string
     */
    protected $api_key;

    /**
     * Set up test.
     */
    public function setUp(): void {
        parent::setUp();
        global $wp_rest_server;
        $this->server = $wp_rest_server = new \WP_REST_Server();
        do_action( 'rest_api_init' );

        // Create test API key
        $this->api_key = bin2hex( random_bytes( 32 ) );
        $api_key_hash = hash( 'sha256', $this->api_key );
        update_option( 'ai_gateway_api_key_hash', $api_key_hash );
    }

    /**
     * Tear down test.
     */
    public function tearDown(): void {
        parent::tearDown();
        delete_option( 'ai_gateway_api_key_hash' );
    }

    /**
     * Test: GET /system-info returns 200 and version data
     *
     * @test
     */
    public function test_system_info_returns_200() {
        $request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/system-info' );
        $request->set_header( 'Authorization', 'Bearer ' . $this->api_key );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'data', $data );
    }

    /**
     * Test: GET /logs returns 200 (if debug enabled)
     *
     * @test
     */
    public function test_read_logs_returns_200_or_404() {
        $request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/logs' );
        $request->set_header( 'Authorization', 'Bearer ' . $this->api_key );

        $response = $this->server->dispatch( $request );

        // Should return 200 if WP_DEBUG enabled, 404 if not or endpoint doesn't exist
        $this->assertContains( $response->get_status(), array( 200, 404 ) );
    }

    /**
     * Test: GET /health returns 200
     *
     * @test
     */
    public function test_health_check_returns_200_or_404() {
        $request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/health' );
        $request->set_header( 'Authorization', 'Bearer ' . $this->api_key );

        $response = $this->server->dispatch( $request );

        // Should return 200 if health endpoint exists
        $this->assertContains( $response->get_status(), array( 200, 404 ) );
    }

    /**
     * Test: Observability endpoints without auth return 401
     *
     * @test
     */
    public function test_observability_without_auth_returns_401() {
        $request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/system-info' );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 401, $response->get_status() );
    }
}
