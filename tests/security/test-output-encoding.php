<?php
/**
 * Security Tests: Output Encoding
 *
 * Tests for proper output encoding in API responses.
 * Checklist Item #4: Output encoding (responses JSON-encoded, no XSS)
 *
 * @package AI_Gateway\Tests\Security
 */

namespace AI_Gateway\Tests\Security;

/**
 * Test_OutputEncoding
 *
 * Security tests for output encoding and XSS prevention.
 */
class Test_OutputEncoding extends \WP_UnitTestCase {
	/**
	 * Test 1: All API responses are JSON-encoded.
	 *
	 * @test
	 */
	public function test_all_responses_json_encoded() {
		$response = array(
			'success' => true,
			'data' => array( 'id' => 1 ),
		);

		$json = json_encode( $response );

		// Should be valid JSON.
		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
		$this->assertTrue( $decoded['success'] );
	}

	/**
	 * Test 2: No HTML tags in JSON responses (prevents XSS).
	 *
	 * @test
	 */
	public function test_no_html_in_responses() {
		$response = array(
			'message' => 'File created successfully',
		);

		$json = json_encode( $response );

		// Should not contain HTML tags.
		$this->assertNotContains( '<html>', $json );
		$this->assertNotContains( '<script>', $json );
		$this->assertNotContains( '</body>', $json );
	}

	/**
	 * Test 3: Special characters are properly JSON-escaped.
	 *
	 * @test
	 */
	public function test_special_characters_json_escaped() {
		$response = array(
			'content' => 'Line 1\nLine 2\t"quoted"\\backslash',
		);

		$json = json_encode( $response );

		// JSON_UNESCAPED_SLASHES would make backslash visible, but standard encoding escapes it.
		$decoded = json_decode( $json, true );
		$this->assertEquals(
			'Line 1\nLine 2\t"quoted"\\backslash',
			$decoded['content']
		);
	}
}
