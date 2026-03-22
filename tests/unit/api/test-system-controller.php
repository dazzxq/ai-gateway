<?php
/**
 * Unit Tests for SystemController
 *
 * Tests flush-cache endpoint responses: success, SpeedyCache not active,
 * and transient cleanup.
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\SystemController;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;

/**
 * Test_SystemController
 *
 * Unit test class for SystemController flush-cache endpoint.
 */
class Test_SystemController extends \WP_UnitTestCase {
	/**
	 * SystemController instance.
	 *
	 * @var SystemController
	 */
	private $controller;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up an admin user so permission checks pass.
		$user_id = wp_insert_user( array(
			'user_login' => 'test_admin_' . uniqid(),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		) );
		wp_set_current_user( $user_id );

		// Instantiate controller with real dependencies.
		$permission_checker = new PermissionChecker();
		$audit_logger       = new AuditLogger();

		$this->controller = new SystemController(
			$permission_checker,
			$audit_logger
		);
	}

	/**
	 * Test: flush-cache returns 200 with speedycache, object_cache, transients keys.
	 */
	public function test_flush_cache_returns_success_response() {
		$request = new WP_REST_Request( 'POST', '/ai-gateway/v1/system/flush-cache' );
		// Auth handled by wp_set_current_user in setUp

		$response = $this->controller->handle_flush_cache( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'speedycache', $data['data'] );
		$this->assertArrayHasKey( 'object_cache', $data['data'] );
		$this->assertArrayHasKey( 'transients', $data['data'] );
	}

	/**
	 * Test: SpeedyCache not active is reported as flushed=false with reason.
	 */
	public function test_flush_cache_handles_speedycache_not_active() {
		$request = new WP_REST_Request( 'POST', '/ai-gateway/v1/system/flush-cache' );
		// Auth handled by wp_set_current_user in setUp

		$response = $this->controller->handle_flush_cache( $request );
		$data     = $response->get_data();

		// SpeedyCache is NOT installed in test environment.
		$this->assertFalse( $data['data']['speedycache']['flushed'] );
		$this->assertArrayHasKey( 'reason', $data['data']['speedycache'] );
		$this->assertStringContainsString( 'not active', $data['data']['speedycache']['reason'] );
	}

	/**
	 * Test: flush-cache clears transients.
	 */
	public function test_flush_cache_clears_transients() {
		// Set some test transients.
		set_transient( 'test_flush_cache_1', 'value1', 3600 );
		set_transient( 'test_flush_cache_2', 'value2', 3600 );

		// Verify transients exist.
		$this->assertSame( 'value1', get_transient( 'test_flush_cache_1' ) );

		$request = new WP_REST_Request( 'POST', '/ai-gateway/v1/system/flush-cache' );
		// Auth handled by wp_set_current_user in setUp

		$response = $this->controller->handle_flush_cache( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'deleted', $data['data']['transients'] );
		// At least our 2 transients should have been deleted (plus their timeouts).
		$this->assertGreaterThanOrEqual( 2, $data['data']['transients']['deleted'] );

		// Verify transients are gone.
		$this->assertFalse( get_transient( 'test_flush_cache_1' ) );
		$this->assertFalse( get_transient( 'test_flush_cache_2' ) );
	}
}
