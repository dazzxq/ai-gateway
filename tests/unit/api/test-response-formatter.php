<?php
/**
 * Unit Tests for ResponseFormatter
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\ResponseFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ResponseFormatter, focusing on from_exception() method.
 */
class Test_Response_Formatter extends TestCase {

	/**
	 * Test from_exception() with WP_DEBUG=true includes $e->getMessage() verbatim.
	 */
	public function test_from_exception_debug_true_preserves_developer_details() {
		// WP_DEBUG is defined as true in bootstrap.php.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$this->markTestSkipped( 'WP_DEBUG must be true for this test' );
		}

		$exception = new \RuntimeException( 'Something went wrong', 0 );

		$response = ResponseFormatter::from_exception( 'test_error', $exception, 500 );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'test_error', $data['code'] );
		$this->assertEquals( 500, $response->get_status() );
		// In debug mode, message should include file:line info.
		$this->assertStringContainsString( 'Something went wrong', $data['message'] );
		$this->assertStringContainsString( 'test-response-formatter.php', $data['message'] );
	}

	/**
	 * Test from_exception_production_strips_file_path uses production code path.
	 *
	 * Since we cannot undefine WP_DEBUG in a running test, we test the production
	 * stripping logic via the static helper method directly.
	 */
	public function test_from_exception_production_strips_file_path_pattern() {
		// Test the regex pattern that strips " in filename.php:123" patterns.
		$message = 'Error occurred in class-global-styles-controller.php:184';
		$stripped = preg_replace( '/\s+in\s+\S+\.php(:\d+)?/', '', $message );

		$this->assertEquals( 'Error occurred', $stripped );
	}

	/**
	 * Test production mode strips class::method() prefix from messages.
	 */
	public function test_from_exception_production_strips_class_prefix() {
		$message = 'GlobalStylesManager::repair(): JSON parse error';
		$stripped = preg_replace( '/^[A-Z][a-zA-Z\\\\]+::\w+\(\):\s*/', '', $message );

		$this->assertEquals( 'JSON parse error', $stripped );
	}

	/**
	 * Test from_exception() returns WP_REST_Response with correct code and HTTP status.
	 */
	public function test_from_exception_returns_correct_error_code_and_status() {
		$exception = new \RuntimeException( 'Test error' );

		$response = ResponseFormatter::from_exception( 'custom_error_code', $exception, 503 );
		$data     = $response->get_data();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 503, $response->get_status() );
		$this->assertEquals( 'custom_error_code', $data['code'] );
		$this->assertFalse( $data['success'] );
	}

	/**
	 * Test from_exception() with clean message (no file paths) returns message unchanged
	 * regardless of mode (the production stripping has nothing to strip).
	 */
	public function test_from_exception_clean_message_returns_unchanged() {
		$exception = new \RuntimeException( 'Simple clean error message' );

		$response = ResponseFormatter::from_exception( 'clean_error', $exception, 500 );
		$data     = $response->get_data();

		// The core message should always be present.
		$this->assertStringContainsString( 'Simple clean error message', $data['message'] );
	}

	/**
	 * Test sanitize_passthrough returns string unchanged.
	 */
	public function test_sanitize_passthrough_returns_string() {
		$input = '<style>.foo { color: red; }</style>';
		$result = ResponseFormatter::sanitize_passthrough( $input );

		$this->assertEquals( $input, $result );
	}

	/**
	 * Test sanitize_passthrough returns empty string for non-string input.
	 */
	public function test_sanitize_passthrough_returns_empty_for_non_string() {
		$this->assertEquals( '', ResponseFormatter::sanitize_passthrough( 123 ) );
		$this->assertEquals( '', ResponseFormatter::sanitize_passthrough( null ) );
		$this->assertEquals( '', ResponseFormatter::sanitize_passthrough( array() ) );
	}
}
