<?php
/**
 * Unit Tests for LogReader
 *
 * @package AI_Gateway\Tests\Unit
 */

namespace AI_Gateway\Tests\Unit;

use AI_Gateway\Observability\LogReader;

/**
 * Test_LogReader
 *
 * Tests for the LogReader class.
 */
class Test_LogReader extends \WP_UnitTestCase {
	/**
	 * Temporary log file for testing.
	 *
	 * @var string
	 */
	private $temp_log_file;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->temp_log_file = sys_get_temp_dir() . '/debug_test_' . time() . '.log';
	}

	/**
	 * Clean up test files.
	 */
	public function tearDown(): void {
		parent::tearDown();
		if ( file_exists( $this->temp_log_file ) ) {
			wp_delete_file( $this->temp_log_file );
		}
	}

	/**
	 * Test 1: Read logs returns array structure.
	 *
	 * @test
	 */
	public function test_read_logs_returns_array_structure() {
		$limiter = new LogReader();
		$result = $limiter->read_logs( 50 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'entries', $result );
		$this->assertArrayHasKey( 'total_count', $result );
		$this->assertArrayHasKey( 'matched_count', $result );
		$this->assertArrayHasKey( 'filter_used', $result );
	}

	/**
	 * Test 2: Line count is capped to 500.
	 *
	 * @test
	 */
	public function test_line_count_capped_to_500() {
		$limiter = new LogReader();
		$result = $limiter->read_logs( 1000 );

		// Should not exceed 500 due to cap.
		$this->assertLessThanOrEqual( 500, $result['total_count'] );
	}

	/**
	 * Test 3: Is available returns boolean.
	 *
	 * @test
	 */
	public function test_is_available_returns_boolean() {
		$limiter = new LogReader();
		$available = $limiter->is_available();

		$this->assertIsBool( $available );
	}

	/**
	 * Test 4: Reset timestamp format is ISO-8601.
	 *
	 * @test
	 */
	public function test_entries_have_required_fields() {
		$limiter = new LogReader();
		$result = $limiter->read_logs( 1 );

		if ( ! empty( $result['entries'] ) ) {
			$entry = $result['entries'][0];
			$this->assertArrayHasKey( 'timestamp', $entry );
			$this->assertArrayHasKey( 'level', $entry );
			$this->assertArrayHasKey( 'message', $entry );
		}
	}
}
