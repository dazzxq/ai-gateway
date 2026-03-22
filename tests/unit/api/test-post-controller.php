<?php
/**
 * Unit Tests for PostController
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\PostController;
use AI_Gateway\API\ResponseFormatter;
use AI_Gateway\ContentManagement\PostManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;

/**
 * Test_PostController
 *
 * Tests for PostController class (search_content_callback).
 */
class Test_PostController extends \WP_UnitTestCase {

	/**
	 * PostController instance.
	 *
	 * @var PostController
	 */
	private $controller;

	/**
	 * PostManager mock.
	 *
	 * @var PostManager
	 */
	private $post_manager;

	/**
	 * PermissionChecker mock.
	 *
	 * @var PermissionChecker
	 */
	private $permission_checker;

	/**
	 * AuditLogger mock.
	 *
	 * @var AuditLogger
	 */
	private $audit_logger;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->post_manager = $this->createMock( PostManager::class );
		$this->permission_checker = $this->createMock( PermissionChecker::class );
		$this->audit_logger = $this->createMock( AuditLogger::class );

		$this->controller = new PostController(
			$this->post_manager,
			$this->permission_checker,
			$this->audit_logger
		);
	}

	/**
	 * Test controller instantiation with new constructor signature.
	 */
	public function test_controller_exists() {
		$this->assertInstanceOf( PostController::class, $this->controller );
	}

	/**
	 * Test search_content_callback returns 200 with results.
	 */
	public function test_search_content_returns_success() {
		$this->post_manager->method( 'search_posts_by_content' )->willReturn(
			array(
				'posts' => array( array( 'ID' => 1, 'post_title' => 'Test' ) ),
				'total' => 1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/ai-gateway/v1/posts/search-content' );
		$request->set_param( 'q', 'test-search' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );

		$response = $this->controller->search_content_callback( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test search_content_callback returns 400 for empty query.
	 */
	public function test_search_content_empty_query_returns_400() {
		$request = new WP_REST_Request( 'GET', '/ai-gateway/v1/posts/search-content' );
		$request->set_param( 'q', '' );

		$response = $this->controller->search_content_callback( $request );

		$this->assertEquals( 400, $response->get_status() );
	}
}
