<?php
/**
 * Integration Tests for Snippet Deploy Workflow (TEST-02)
 *
 * Real deploy cycle test: create snippet, PATCH code, deploy, verify,
 * read back. Covers the full PATCH + deploy + verify workflow.
 *
 * @package AI_Gateway\Tests\Integration
 */

namespace AI_Gateway\Tests\Integration;

/**
 * Test_SnippetDeployWorkflow
 *
 * Integration test class for the full snippet deploy lifecycle.
 */
class Test_SnippetDeployWorkflow extends \WP_UnitTestCase {
	/**
	 * API key for testing.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * IDs of snippets created during tests (for cleanup).
	 *
	 * @var array
	 */
	private $created_snippet_ids = array();

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create admin user.
		$user_id = wp_insert_user( array(
			'user_login' => 'deploy_workflow_admin_' . uniqid(),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		) );
		wp_set_current_user( $user_id );

		// Generate API key.
		$this->api_key = bin2hex( random_bytes( 32 ) );
		$hash          = hash( 'sha256', $this->api_key );
		update_option( 'ai_gateway_api_key_hash', $hash );

		// Register routes via rest_api_init hook then reset the server.
		add_action( 'rest_api_init', function() {
			$loader = new \AI_Gateway\PluginLoader();
			$loader->register_rest_routes();
		}, 99 );
		global $wp_rest_server;
		$wp_rest_server = null;
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';

		// Clean up created snippets.
		foreach ( $this->created_snippet_ids as $id ) {
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		}

		delete_option( 'ai_gateway_api_key_hash' );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'ai_gateway_rate_limit_%'
			)
		);

		parent::tearDown();
	}

	/**
	 * Test: Full deploy cycle -- create, PATCH code, deploy, verify, read back.
	 */
	public function test_full_deploy_cycle() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';

		// Step 1: Create test snippet via direct DB insert.
		$snippet_name = 'Full Deploy Cycle Test ' . uniqid();
		$wpdb->insert(
			$table,
			array(
				'name'        => $snippet_name,
				'description' => 'Deploy cycle test snippet',
				'code'        => 'echo "original code";',
				'active'      => 1,
				'scope'       => 'global',
				'priority'    => 10,
				'modified'    => current_time( 'mysql' ),
				'revision'    => 1,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d' )
		);
		$snippet_id = (int) $wpdb->insert_id;
		$this->assertGreaterThan( 0, $snippet_id, 'Test snippet should be created' );
		$this->created_snippet_ids[] = $snippet_id;

		// Step 2: PATCH snippet code via REST dispatch.
		$patch_request = new \WP_REST_Request( 'PATCH', '/ai-gateway/v1/code-snippets/' . $snippet_id );
		$patch_request->add_header( 'X-API-Key', $this->api_key );
		$patch_request->set_header( 'Content-Type', 'application/json' );
		$patch_request->set_body( wp_json_encode( array(
			'code' => 'echo "updated via PATCH";',
		) ) );

		$patch_response = rest_get_server()->dispatch( $patch_request );
		$this->assertEquals( 200, $patch_response->get_status(), 'PATCH should succeed' );

		// Step 3: Deploy snippet via REST dispatch.
		$deploy_request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets/' . $snippet_id . '/deploy' );
		$deploy_request->add_header( 'X-API-Key', $this->api_key );

		$deploy_response = rest_get_server()->dispatch( $deploy_request );
		$this->assertEquals( 200, $deploy_response->get_status(), 'Deploy should succeed' );

		$deploy_data = $deploy_response->get_data();

		// Step 4: Verify deploy response structure.
		$this->assertTrue( $deploy_data['success'] );
		$this->assertTrue( $deploy_data['data']['deployed'] );

		// Active can be true or false depending on CS functions availability.
		$this->assertIsBool( $deploy_data['data']['active'] );

		// Step 5: Verify steps log.
		$this->assertIsArray( $deploy_data['data']['steps'] );
		$this->assertCount( 3, $deploy_data['data']['steps'] );
		$this->assertSame( 'deactivate', $deploy_data['data']['steps'][0]['action'] );
		$this->assertSame( 'activate', $deploy_data['data']['steps'][1]['action'] );
		$this->assertSame( 'verify', $deploy_data['data']['steps'][2]['action'] );

		// Step 6: Read snippet back and verify code was updated.
		$get_request = new \WP_REST_Request( 'GET', '/ai-gateway/v1/code-snippets/' . $snippet_id );
		$get_request->add_header( 'X-API-Key', $this->api_key );

		$get_response = rest_get_server()->dispatch( $get_request );
		$this->assertEquals( 200, $get_response->get_status() );

		$get_data = $get_response->get_data();
		$this->assertTrue( $get_data['success'] );
		$this->assertStringContainsString( 'updated via PATCH', $get_data['data']->code );
	}

	/**
	 * Test: Deploy an inactive snippet.
	 */
	public function test_deploy_inactive_snippet() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';

		// Create snippet with active = 0.
		$wpdb->insert(
			$table,
			array(
				'name'        => 'Inactive Deploy Test ' . uniqid(),
				'description' => 'Inactive snippet for deploy test',
				'code'        => 'echo "inactive snippet";',
				'active'      => 0,
				'scope'       => 'global',
				'priority'    => 10,
				'modified'    => current_time( 'mysql' ),
				'revision'    => 1,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d' )
		);
		$snippet_id = (int) $wpdb->insert_id;
		$this->assertGreaterThan( 0, $snippet_id );
		$this->created_snippet_ids[] = $snippet_id;

		// Deploy the inactive snippet.
		$request = new \WP_REST_Request( 'POST', '/ai-gateway/v1/code-snippets/' . $snippet_id . '/deploy' );
		$request->add_header( 'X-API-Key', $this->api_key );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertTrue( $data['data']['deployed'] );
		$this->assertIsArray( $data['data']['steps'] );
		$this->assertCount( 3, $data['data']['steps'] );
	}
}
