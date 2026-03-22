<?php
/**
 * Integration Test: Post & ACF Workflow
 *
 * End-to-end test for complete post and ACF field management workflow.
 *
 * @package AI_Gateway\Tests\Integration
 */

namespace AI_Gateway\Tests\Integration;

use AI_Gateway\ContentManagement\PostManager;
use AI_Gateway\ContentManagement\ACFManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\API\PostController;
use AI_Gateway\API\ACFController;
use WP_REST_Request;

/**
 * Test_PostACFWorkflow
 *
 * Integration tests for post and ACF management workflows.
 */
class Test_PostACFWorkflow extends \WP_UnitTestCase {

	/**
	 * PostManager instance.
	 *
	 * @var PostManager
	 */
	private $post_manager;

	/**
	 * ACFManager instance.
	 *
	 * @var ACFManager
	 */
	private $acf_manager;

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

		$this->acf_manager = new ACFManager();
		$this->post_manager = new PostManager( $this->acf_manager );
		$this->audit_logger = new AuditLogger();

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
	 * Test 1: Complete post listing workflow.
	 *
	 * @test
	 */
	public function test_post_listing_workflow() {
		// Create test posts.
		$post1 = self::factory()->post->create(
			array(
				'post_title'   => 'First Post' . uniqid(),
				'post_content' => 'Content 1',
				'post_status'  => 'publish',
			)
		);
		$post2 = self::factory()->post->create(
			array(
				'post_title'   => 'Second Post' . uniqid(),
				'post_content' => 'Content 2',
				'post_status'  => 'publish',
			)
		);

		// List posts.
		$result = $this->post_manager->get_posts();

		// Verify response structure.
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'pagination', $result );
		// Should have at least our 2 posts
		$this->assertGreaterThanOrEqual( 2, count( $result['posts'] ) );
		$this->assertGreaterThanOrEqual( 2, $result['pagination']['total'] );

		// Verify our posts are in the results
		$found_post1 = false;
		$found_post2 = false;
		foreach ( $result['posts'] as $post ) {
			if ( $post['id'] === $post1 ) {
				$found_post1 = true;
			}
			if ( $post['id'] === $post2 ) {
				$found_post2 = true;
			}
		}
		$this->assertTrue( $found_post1 );
		$this->assertTrue( $found_post2 );
	}

	/**
	 * Test 2: Post detail with content rendering.
	 *
	 * @test
	 */
	public function test_post_detail_with_content() {
		// Create post with block content.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Block Post',
				'post_content' => '<!-- wp:paragraph --><p>Test paragraph</p><!-- /wp:paragraph -->',
			)
		);

		// Get post detail.
		$post = $this->post_manager->get_post( $post_id );

		// Verify detail structure.
		$this->assertEquals( $post_id, $post['id'] );
		$this->assertEquals( 'Block Post', $post['title'] );
		$this->assertArrayHasKey( 'content', $post );
		$this->assertArrayHasKey( 'meta', $post );

		// Verify content is processed (filters applied).
		$this->assertNotEmpty( $post['content'] );
	}

	/**
	 * Test 3: Update post and verify audit trail.
	 *
	 * @test
	 */
	public function test_update_post_creates_audit_entry() {
		$post_id = self::factory()->post->create(
			array( 'post_title' => 'Original' )
		);

		// Update post.
		$updated = $this->post_manager->update_post(
			$post_id,
			array( 'title' => 'Updated Title' )
		);

		// Verify update.
		$this->assertEquals( 'Updated Title', $updated['title'] );

		// Log to audit.
		$request = new WP_REST_Request( 'PATCH', '/posts/' . $post_id );
		$response = new \WP_REST_Response( array(), 200 );
		$this->audit_logger->log_mutation(
			$request,
			$response,
			array(
				'resource_type'      => 'post',
				'resource_id'        => (string) $post_id,
				'action_description' => 'Updated post ' . $post_id,
			)
		);

		// Verify audit entry.
		global $wpdb;
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ai_gateway_audit WHERE resource_id = '" . $post_id . "'"
		);
		$this->assertEquals( 1, $count );
	}

	/**
	 * Test 4: Search and filter workflow.
	 *
	 * @test
	 */
	public function test_search_and_filter_workflow() {
		// Create posts with different content.
		$post1_id = self::factory()->post->create(
			array(
				'post_title'   => 'WordPress Basics ' . uniqid(),
				'post_content' => 'Learn WordPress',
				'post_status'  => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'   => 'PHP Tutorial ' . uniqid(),
				'post_content' => 'Learn PHP',
				'post_status'  => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'  => 'Draft Post ' . uniqid(),
				'post_status' => 'draft',
			)
		);

		// Search for WordPress posts.
		$result = $this->post_manager->get_posts(
			array(
				'search' => 'WordPress Basics',
			)
		);

		// Find our post in results
		$found = false;
		foreach ( $result['posts'] as $post ) {
			if ( $post['id'] === $post1_id ) {
				$found = true;
				$this->assertEquals( 'WordPress Basics ' . substr( $post['title'], -13 ), $post['title'] );
				break;
			}
		}
		$this->assertTrue( $found );
	}

	/**
	 * Test 5: ACF field group operations.
	 *
	 * @test
	 */
	public function test_acf_field_group_operations() {
		$post_id = self::factory()->post->create();

		// Get ACF fields (will be empty if ACF not active).
		$fields = $this->acf_manager->get_fields( $post_id );

		// Should return array regardless.
		$this->assertIsArray( $fields );
	}

	/**
	 * Test 6: Multiple posts pagination workflow.
	 *
	 * @test
	 */
	public function test_pagination_workflow() {
		// Create 25 posts.
		for ( $i = 0; $i < 25; $i++ ) {
			self::factory()->post->create(
				array( 'post_title' => 'Post ' . $i )
			);
		}

		// Page 1.
		$page1 = $this->post_manager->get_posts(
			array(
				'page'     => 1,
				'per_page' => 10,
			)
		);

		$this->assertEquals( 1, $page1['pagination']['page'] );
		$this->assertEquals( 10, count( $page1['posts'] ) );

		// Page 2.
		$page2 = $this->post_manager->get_posts(
			array(
				'page'     => 2,
				'per_page' => 10,
			)
		);

		$this->assertEquals( 2, $page2['pagination']['page'] );
		$this->assertEquals( 10, count( $page2['posts'] ) );

		// Verify different posts on each page.
		$this->assertNotEquals( $page1['posts'][0]['id'], $page2['posts'][0]['id'] );
	}

	/**
	 * Test 7: Audit query with filters.
	 *
	 * @test
	 */
	public function test_audit_query_with_filters() {
		// Create audit entries.
		$req1 = new WP_REST_Request( 'PATCH', '/posts/1' );
		$req2 = new WP_REST_Request( 'POST', '/posts' );
		$resp = new \WP_REST_Response( array(), 200 );

		$this->audit_logger->log_mutation( $req1, $resp );
		$this->audit_logger->log_mutation( $req2, $resp );

		// Query PATCH only.
		$result = $this->audit_logger->query_audit_entries(
			array( 'method' => 'PATCH' )
		);

		$this->assertEquals( 1, count( $result['entries'] ) );
		$this->assertEquals( 'PATCH', $result['entries'][0]['method'] );
	}
}
