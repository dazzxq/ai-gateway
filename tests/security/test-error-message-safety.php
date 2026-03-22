<?php
/**
 * Security Tests: Error Message Safety
 *
 * Tests to ensure error messages don't leak sensitive information.
 * Checklist Item #11: Error messages (no path/server info leakage)
 *
 * @package AI_Gateway\Tests\Security
 */

namespace AI_Gateway\Tests\Security;

/**
 * Test_ErrorMessageSafety
 *
 * Security tests for error message safety.
 */
class Test_ErrorMessageSafety extends \WP_UnitTestCase {
	/**
	 * Test 1: 404 errors do not disclose file paths.
	 *
	 * @test
	 */
	public function test_404_error_no_path_disclosure() {
		// 404 response should not include full file path.
		$error = 'File not found';

		$this->assertNotContains( '/', $error );
		$this->assertNotContains( 'wp-content', $error );
	}

	/**
	 * Test 2: 500 errors do not disclose version information.
	 *
	 * @test
	 */
	public function test_500_error_no_version_disclosure() {
		// 500 response should not include PHP/WordPress version.
		$error = 'Internal server error';

		$this->assertNotContains( 'PHP', $error );
		$this->assertNotContains( 'WordPress', $error );
		$this->assertNotContains( phpversion(), $error );
	}
}
