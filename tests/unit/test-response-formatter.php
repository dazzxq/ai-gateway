<?php
/**
 * Unit Tests for Response Formatter
 *
 * @package AI_Gateway\Tests\Unit
 */

namespace AI_Gateway\Tests\Unit;

use AI_Gateway\API\ResponseFormatter;

/**
 * Test_ResponseFormatter
 *
 * Tests for the Response Formatter class.
 */
class Test_ResponseFormatter extends \WP_UnitTestCase {
	/**
	 * Test 1: Success response structure.
	 *
	 * @test
	 */
	public function test_success_response_structure() {
		$response = ResponseFormatter::success( array( 'files' => array() ), 200 );

		$this->assertInstanceOf( '\WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'timestamp', $data );
	}

	/**
	 * Test 2: Error response structure.
	 *
	 * @test
	 */
	public function test_error_response_structure() {
		$response = ResponseFormatter::error( 'test_code', 'Test message', array(), 400 );

		$this->assertInstanceOf( '\WP_REST_Response', $response );
		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'test_code', $data['code'] );
		$this->assertEquals( 'Test message', $data['message'] );
		$this->assertArrayHasKey( 'timestamp', $data );
	}

	/**
	 * Test 3: Timestamp format is ISO-8601.
	 *
	 * @test
	 */
	public function test_timestamp_is_iso8601() {
		$response = ResponseFormatter::success( array(), 200 );
		$data = $response->get_data();

		// Check ISO-8601 format: YYYY-MM-DDTHH:MM:SSZ or similar.
		$this->assertMatchesRegularExpression(
			'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[Z\+\-]/',
			$data['timestamp']
		);
	}

	/**
	 * Test 4: Different HTTP codes work correctly.
	 *
	 * @test
	 */
	public function test_different_http_codes() {
		// Success codes.
		$response_200 = ResponseFormatter::success( array(), 200 );
		$this->assertEquals( 200, $response_200->get_status() );

		$response_201 = ResponseFormatter::success( array(), 201 );
		$this->assertEquals( 201, $response_201->get_status() );

		$response_204 = ResponseFormatter::success( array(), 204 );
		$this->assertEquals( 204, $response_204->get_status() );

		// Error codes.
		$response_401 = ResponseFormatter::error( 'code', 'msg', array(), 401 );
		$this->assertEquals( 401, $response_401->get_status() );

		$response_403 = ResponseFormatter::error( 'code', 'msg', array(), 403 );
		$this->assertEquals( 403, $response_403->get_status() );
	}

	/**
	 * Test 5: Data field can be empty or complex.
	 *
	 * @test
	 */
	public function test_data_field_complex_structures() {
		// Empty data.
		$response_empty = ResponseFormatter::success( array(), 200 );
		$data_empty = $response_empty->get_data();
		$this->assertEquals( array(), $data_empty['data'] );

		// Nested data.
		$nested_data = array( 'nested' => array( 'data' => 'here' ) );
		$response_nested = ResponseFormatter::success( $nested_data, 200 );
		$data_nested = $response_nested->get_data();
		$this->assertEquals( $nested_data, $data_nested['data'] );
	}

	/**
	 * Test 6: Error data field is optional.
	 *
	 * @test
	 */
	public function test_error_data_field_optional() {
		// Without extra data.
		$response_no_data = ResponseFormatter::error( 'code', 'msg', array(), 400 );
		$data_no_extra = $response_no_data->get_data();
		$this->assertEquals( array(), $data_no_extra['data'] );

		// With extra data.
		$extra_data = array( 'extra' => 'info' );
		$response_with_data = ResponseFormatter::error( 'code', 'msg', $extra_data, 400 );
		$data_with_extra = $response_with_data->get_data();
		$this->assertEquals( $extra_data, $data_with_extra['data'] );
	}

	/**
	 * Test 7: Default HTTP codes.
	 *
	 * @test
	 */
	public function test_default_http_codes() {
		$response_success = ResponseFormatter::success( array() );
		$this->assertEquals( 200, $response_success->get_status() );

		$response_error = ResponseFormatter::error( 'code', 'msg' );
		$this->assertEquals( 400, $response_error->get_status() );
	}
}
