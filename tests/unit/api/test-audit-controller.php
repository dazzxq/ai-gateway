<?php
/**
 * Unit Tests for AuditController
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\AuditController;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;

/**
 * Test_AuditController
 *
 * Tests for AuditController class.
 */
class Test_AuditController extends \WP_UnitTestCase {

	/**
	 * AuditController instance.
	 *
	 * @var AuditController
	 */
	private $controller;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize audit table.
		global $wpdb;
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gateway_audit (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				method VARCHAR(10) NOT NULL,
				endpoint VARCHAR(255) NOT NULL,
				status_code INT NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				username VARCHAR(60) NOT NULL DEFAULT '',
				resource_type VARCHAR(50) NOT NULL,
				resource_id VARCHAR(255),
				action_description VARCHAR(500),
				INDEX idx_timestamp (timestamp),
				INDEX idx_endpoint (endpoint),
				INDEX idx_method (method),
				INDEX idx_status_code (status_code),
				INDEX idx_user_id (user_id)
			)"
		);

		$audit_logger       = new AuditLogger();
		$permission_checker = $this->createMock( PermissionChecker::class );
		$this->controller   = new AuditController( $audit_logger, $permission_checker );
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		parent::tearDown();
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ai_gateway_audit" );
	}

	/**
	 * Test 1: query_audit_callback returns 200.
	 *
	 * @test
	 */
	public function test_query_audit_success() {
		$request = new WP_REST_Request( 'GET', '/audit' );
		$response = $this->controller->query_audit_callback( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test 2: query_audit returns entries and pagination.
	 *
	 * @test
	 */
	public function test_query_audit_returns_structure() {
		$request = new WP_REST_Request( 'GET', '/audit' );
		$response = $this->controller->query_audit_callback( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'entries', $data['data'] );
		$this->assertArrayHasKey( 'pagination', $data['data'] );
	}
}
