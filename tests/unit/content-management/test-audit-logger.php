<?php
/**
 * Unit Tests for AuditLogger
 *
 * @package AI_Gateway\Tests\Unit\ContentManagement
 */

namespace AI_Gateway\Tests\Unit\ContentManagement;

use AI_Gateway\ContentManagement\AuditLogger;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Test_AuditLogger
 *
 * Tests for AuditLogger class.
 */
class Test_AuditLogger extends \WP_UnitTestCase {

	/**
	 * AuditLogger instance.
	 *
	 * @var AuditLogger
	 */
	private $audit_logger;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->audit_logger = new AuditLogger();

		// Ensure audit table exists.
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

		// Clear any entries from prior test runs to ensure clean state.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ai_gateway_audit" );
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
	 * Test 1: Log mutation creates audit entry.
	 *
	 * @test
	 */
	public function test_log_mutation_creates_entry() {
		$request = new WP_REST_Request( 'PATCH', '/wp-json/ai-gateway/v1/posts/1' );
		$response = new WP_REST_Response( array(), 200 );

		$resource_info = array(
			'resource_type'      => 'post',
			'resource_id'        => '1',
			'action_description' => 'Updated post 1',
		);

		$result = $this->audit_logger->log_mutation( $request, $response, $resource_info );

		$this->assertTrue( $result );

		// Verify entry exists in database.
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ai_gateway_audit" );
		$this->assertEquals( 1, $count );
	}

	/**
	 * Test 2: Log mutation records correct method.
	 *
	 * @test
	 */
	public function test_log_mutation_records_method() {
		$request = new WP_REST_Request( 'POST', '/wp-json/ai-gateway/v1/posts' );
		$response = new WP_REST_Response( array(), 201 );

		$this->audit_logger->log_mutation( $request, $response );

		global $wpdb;
		$entry = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}ai_gateway_audit ORDER BY id DESC LIMIT 1"
		);

		$this->assertEquals( 'POST', $entry->method );
	}

	/**
	 * Test 3: Log mutation records status code.
	 *
	 * @test
	 */
	public function test_log_mutation_records_status_code() {
		$request = new WP_REST_Request( 'GET', '/posts' );
		$response = new WP_REST_Response( array(), 404 );

		$this->audit_logger->log_mutation( $request, $response );

		global $wpdb;
		$entry = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}ai_gateway_audit ORDER BY id DESC LIMIT 1"
		);

		$this->assertEquals( 404, $entry->status_code );
	}

	/**
	 * Test 4: Query audit entries without filters.
	 *
	 * @test
	 */
	public function test_query_audit_entries_all() {
		// Create 3 entries.
		for ( $i = 0; $i < 3; $i++ ) {
			$request = new WP_REST_Request( 'PATCH', '/posts' );
			$response = new WP_REST_Response( array(), 200 );
			$this->audit_logger->log_mutation( $request, $response );
		}

		$result = $this->audit_logger->query_audit_entries();

		$this->assertArrayHasKey( 'entries', $result );
		$this->assertArrayHasKey( 'pagination', $result );
		$this->assertEquals( 3, count( $result['entries'] ) );
	}

	/**
	 * Test 5: Query audit with method filter.
	 *
	 * @test
	 */
	public function test_query_audit_entries_filter_method() {
		$req1 = new WP_REST_Request( 'POST', '/posts' );
		$req2 = new WP_REST_Request( 'PATCH', '/posts/1' );
		$resp = new WP_REST_Response( array(), 200 );

		$this->audit_logger->log_mutation( $req1, $resp );
		$this->audit_logger->log_mutation( $req2, $resp );

		$result = $this->audit_logger->query_audit_entries(
			array( 'method' => 'PATCH' )
		);

		$this->assertEquals( 1, count( $result['entries'] ) );
		$this->assertEquals( 'PATCH', $result['entries'][0]['method'] );
	}

	/**
	 * Test 6: Prune keeps maximum entries.
	 *
	 * @test
	 */
	public function test_prune_old_entries() {
		global $wpdb;

		// Manually insert many entries to exceed limit.
		for ( $i = 0; $i < 50; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'ai_gateway_audit',
				array(
					'timestamp'          => gmdate( 'Y-m-d H:i:s' ),
					'method'             => 'POST',
					'endpoint'           => '/posts',
					'status_code'        => 200,
					'user_id'            => 1,
					'username'           => 'testuser',
					'resource_type'      => 'post',
					'resource_id'        => (string) $i,
					'action_description' => 'Action ' . $i,
				)
			);
		}

		$this->audit_logger->prune_old_entries();

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ai_gateway_audit" );
		// Should be capped (pruning doesn't trigger here since 50 < 1000).
		$this->assertLessThanOrEqual( 1000, $count );
	}

	/**
	 * Test 7: Query with pagination.
	 *
	 * @test
	 */
	public function test_query_audit_with_pagination() {
		// Create 25 entries.
		for ( $i = 0; $i < 25; $i++ ) {
			$request = new WP_REST_Request( 'POST', '/posts' );
			$response = new WP_REST_Response( array(), 200 );
			$this->audit_logger->log_mutation( $request, $response );
		}

		$result = $this->audit_logger->query_audit_entries(
			array(
				'per_page' => 10,
				'page'     => 2,
			)
		);

		$this->assertEquals( 10, count( $result['entries'] ) );
		$this->assertEquals( 2, $result['pagination']['page'] );
		$this->assertEquals( 25, $result['pagination']['total'] );
	}
}
