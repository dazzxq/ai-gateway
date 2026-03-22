<?php
/**
 * Test Helper Functions
 *
 * Shared utility functions for AI Gateway test suite.
 * Provides common patterns for request mocking, auth simulation,
 * and response validation.
 *
 * @package AI_Gateway\Tests\Helpers
 */

if ( defined( 'AI_GATEWAY_TEST_HELPERS_LOADED' ) ) {
	return;
}
define( 'AI_GATEWAY_TEST_HELPERS_LOADED', true );

/**
 * Create a mock WP_REST_Request with the given method and parameters.
 *
 * @param string $method HTTP method (GET, POST, PATCH, DELETE).
 * @param array  $params Key-value pairs to set as request parameters.
 * @param string $route  Optional route path.
 *
 * @return WP_REST_Request
 */
function create_mock_request( $method = 'GET', $params = array(), $route = '' ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return $request;
}

/**
 * Set up the current user as an administrator for App Password auth simulation.
 *
 * Creates a temporary admin user and calls wp_set_current_user() to simulate
 * authenticated access via WP Application Password (Basic Auth).
 *
 * @return int The user ID of the created admin user.
 */
function create_admin_user() {
	$user_id = wp_insert_user( array(
		'user_login' => 'test_admin_' . uniqid(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'administrator',
	) );
	wp_set_current_user( $user_id );
	return $user_id;
}

/**
 * Assert that a WP_REST_Response is a standard success response.
 *
 * Validates: HTTP status 200, success=true, data key exists.
 *
 * @param WP_REST_Response                          $response The REST response to validate.
 * @param PHPUnit\Framework\TestCase|\WP_UnitTestCase $test     The test instance for assertions.
 */
function assert_success_response( $response, $test ) {
	$data = $response->get_data();
	$test->assertEquals( 200, $response->get_status(), 'Expected HTTP 200 status' );
	$test->assertTrue( $data['success'], 'Expected success=true in response' );
	$test->assertArrayHasKey( 'data', $data, 'Expected data key in response' );
}

/**
 * Assert that a WP_REST_Response is an error response with the expected status code.
 *
 * Validates: HTTP status matches, success=false, code key exists.
 *
 * @param WP_REST_Response                          $response    The REST response to validate.
 * @param int                                       $status_code Expected HTTP status code.
 * @param PHPUnit\Framework\TestCase|\WP_UnitTestCase $test        The test instance for assertions.
 */
function assert_error_response( $response, $status_code, $test ) {
	$data = $response->get_data();
	$test->assertEquals( $status_code, $response->get_status(), "Expected HTTP {$status_code} status" );
	$test->assertFalse( $data['success'], 'Expected success=false in error response' );
	$test->assertArrayHasKey( 'code', $data, 'Expected code key in error response' );
}
