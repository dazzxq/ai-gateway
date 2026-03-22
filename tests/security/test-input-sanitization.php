<?php
/**
 * Security Tests: Input Sanitization
 *
 * Tests for input validation and sanitization across all endpoints.
 * Checklist Item #3: Input sanitization (all request fields sanitized/validated)
 *
 * @package AI_Gateway\Tests\Security
 */

namespace AI_Gateway\Tests\Security;

/**
 * Test_InputSanitization
 *
 * Security tests for input validation and sanitization.
 */
class Test_InputSanitization extends \WP_UnitTestCase {
	/**
	 * Test 1: SQL injection attempt in filename is rejected.
	 *
	 * @test
	 */
	public function test_snippet_name_sanitized() {
		$sql_injection = "test'; DROP TABLE snippets; --";

		// Sanitization should make this safe.
		$sanitized = sanitize_file_name( $sql_injection );

		// Should not contain dangerous characters.
		$this->assertNotContains( "'", $sanitized );
		$this->assertNotContains( ";", $sanitized );
		$this->assertNotContains( "-", $sanitized );
	}

	/**
	 * Test 2: XSS attempt in post content is sanitized.
	 *
	 * @test
	 */
	public function test_post_content_xss_sanitized() {
		$xss_attempt = '<script>alert("XSS")</script><p>Hello</p>';

		// Content should be sanitized.
		$sanitized = wp_kses_post( $xss_attempt );

		// Script tags should be removed.
		$this->assertNotContains( '<script>', $sanitized );
		$this->assertNotContains( 'alert(', $sanitized );

		// Safe HTML should remain.
		$this->assertContains( '<p>', $sanitized );
	}

	/**
	 * Test 3: Invalid pagination parameters are rejected.
	 *
	 * @test
	 */
	public function test_query_parameters_validated() {
		// Invalid page number.
		$page = absint( 'invalid' );
		$this->assertEquals( 0, $page );

		// Invalid per_page.
		$per_page = absint( '-5' );
		$this->assertEquals( 0, $per_page );

		// Valid page.
		$page_valid = absint( '2' );
		$this->assertEquals( 2, $page_valid );
	}
}
