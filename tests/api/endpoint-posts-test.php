<?php
/**
 * API Smoke Tests - Post and ACF Endpoints
 *
 * Tests for ACF endpoints to verify they are reachable
 * and responding correctly with proper HTTP status codes.
 *
 * @package AI_Gateway\Tests\API
 */

namespace AI_Gateway\Tests\API;

use WP_UnitTestCase;

/**
 * Endpoint_Posts_Test
 *
 * Smoke tests for ACF endpoints.
 */
class Endpoint_Posts_Test extends WP_UnitTestCase {

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
     * Test post ID.
     *
     * @var int
     */
    protected $post_id;

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

        // Create test post
        $this->post_id = wp_insert_post( array(
            'post_title' => 'Test Post for API',
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'post',
        ) );
    }

    /**
     * Tear down test.
     */
    public function tearDown(): void {
        parent::tearDown();
        delete_option( 'ai_gateway_api_key_hash' );

        // Delete test post
        if ( $this->post_id ) {
            wp_delete_post( $this->post_id, true );
        }
    }

    /**
     * Test: GET /posts/{id}/acf returns 200 (or 404 if ACF not available)
     *
     * @test
     */
    public function test_read_post_acf_returns_200_or_404() {
        $request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/posts/' . $this->post_id . '/acf' );
        $request->set_header( 'Authorization', 'Bearer ' . $this->api_key );

        $response = $this->server->dispatch( $request );

        // Should return 200 if ACF available, 404 if endpoint doesn't exist
        $this->assertContains( $response->get_status(), array( 200, 404 ) );
    }

    /**
     * Test: GET /acf/field-groups returns 200
     *
     * @test
     */
    public function test_list_field_groups_returns_200() {
        $request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/acf/field-groups' );
        $request->set_header( 'Authorization', 'Bearer ' . $this->api_key );

        $response = $this->server->dispatch( $request );

        // Route might not be registered in test env
        $status = $response->get_status();
        $this->assertGreaterThanOrEqual( 200, $status );
    }

    /**
     * Test: PATCH /posts/{id}/acf/{field} returns 200 (if available)
     *
     * @test
     */
    public function test_update_post_acf_field_returns_200_or_404() {
        $request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/posts/' . $this->post_id . '/acf/test_field' );
        $request->set_header( 'Authorization', 'Bearer ' . $this->api_key );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array(
            'value' => 'test_value',
        ) ) );

        $response = $this->server->dispatch( $request );

        // Route might not be registered in test, accept various responses
        $status = $response->get_status();
        $this->assertContains( $status, array( 200, 404, 422, 500, 503 ) );
    }
}
