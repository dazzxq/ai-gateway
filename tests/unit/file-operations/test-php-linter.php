<?php
/**
 * Tests for PHPLinter class
 *
 * @package AI_Gateway
 * @subpackage Tests\Unit
 */

use AI_Gateway\FileOperations\PHPLinter;

/**
 * @covers \AI_Gateway\FileOperations\PHPLinter
 */
class Test_PHPLinter extends WP_UnitTestCase {

	/**
	 * PHP Linter instance.
	 *
	 * @var PHPLinter
	 */
	private $linter;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->linter = new PHPLinter();
	}

	/**
	 * Test linting valid PHP.
	 */
	public function test_lint_valid_php() {
		$php_code = '<?php function test() { echo "hello"; } ?>';

		$result = $this->linter->lint_content( $php_code );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test linting invalid PHP with syntax error returns WP_Error.
	 */
	public function test_lint_invalid_php_syntax_error() {
		$php_code_invalid = '<?php function test( { }';
		$result = $this->linter->lint_content( $php_code_invalid );

		// Since Plan 01, lint_content returns WP_Error on syntax failure.
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'php_syntax_error', $result->get_error_code() );

		// Error data contains the errors array.
		$errors = $result->get_error_data( 'php_syntax_error' );
		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
		$this->assertArrayHasKey( 'line', $errors[0] );
		$this->assertArrayHasKey( 'message', $errors[0] );
	}

	/**
	 * Test is_valid_php returns boolean.
	 */
	public function test_is_valid_php_returns_boolean() {
		$valid_php = '<?php echo "ok"; ?>';
		$invalid_php = '<?php echo "missing quote; ?>';

		$this->assertTrue( $this->linter->is_valid_php( $valid_php ) );
		$this->assertFalse( $this->linter->is_valid_php( $invalid_php ) );
	}

	/**
	 * Test get_lint_errors returns array.
	 */
	public function test_get_lint_errors_returns_array() {
		$php_code = '<?php undeclared function; ?>';

		$errors = $this->linter->get_lint_errors( $php_code );

		$this->assertIsArray( $errors );
	}

	/**
	 * Test lint_content with multiple errors.
	 */
	public function test_lint_multiple_errors() {
		$php_code = '<?php
		function broken( {
			$undefined_var;
		}
		?>';

		$result = $this->linter->lint_content( $php_code );

		// Returns WP_Error for syntax failures.
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test temp file cleanup.
	 */
	public function test_temp_file_cleanup() {
		$php_code = '<?php // test ?>';

		// Count temp files before.
		$before = count( glob( sys_get_temp_dir() . '/php-lint-*' ) );

		$this->linter->lint_content( $php_code );

		// Count temp files after (should be same or less).
		$after = count( glob( sys_get_temp_dir() . '/php-lint-*' ) );

		$this->assertLessThanOrEqual( $before + 1, $after );
	}

	/**
	 * Test linting empty PHP.
	 */
	public function test_lint_empty_php() {
		$php_code = '';

		$result = $this->linter->lint_content( $php_code );

		// Empty should be valid (returns array, not WP_Error).
		$this->assertIsArray( $result );
		$this->assertTrue( $result['valid'] );
	}
}
