<?php
/**
 * Unit Tests for GlobalStylesController
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\GlobalStylesController;
use AI_Gateway\ContentManagement\GlobalStylesManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;

/**
 * Test_GlobalStylesController
 *
 * Tests for GlobalStylesController class.
 */
class Test_GlobalStylesController extends \WP_UnitTestCase {

	/**
	 * GlobalStylesController instance.
	 *
	 * @var GlobalStylesController
	 */
	private $controller;

	/**
	 * GlobalStylesManager mock.
	 *
	 * @var GlobalStylesManager|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $manager;

	/**
	 * PermissionChecker mock.
	 *
	 * @var PermissionChecker|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $permission_checker;

	/**
	 * AuditLogger mock.
	 *
	 * @var AuditLogger|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $audit_logger;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->manager            = $this->createMock( GlobalStylesManager::class );
		$this->permission_checker = $this->createMock( PermissionChecker::class );
		$this->audit_logger       = $this->createMock( AuditLogger::class );

		$this->controller = new GlobalStylesController(
			$this->manager,
			$this->permission_checker,
			$this->audit_logger
		);
	}

	/**
	 * Test controller instantiation.
	 */
	public function test_controller_exists() {
		$this->assertInstanceOf( GlobalStylesController::class, $this->controller );
	}

	/**
	 * Test handle_get_css returns 200 with CSS content.
	 */
	public function test_handle_get_css_success() {
		$css = '.foo { color: red; }';
		$this->manager->method( 'get_css' )->willReturn( $css );
		$this->manager->method( 'get_metadata' )->willReturn( array(
			'last_modified' => '2026-03-22T10:00:00',
			'stylesheet'    => 'twentytwentyfive',
			'post_id'       => 9,
		) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/css' );
		$response = $this->controller->handle_get_css( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $css, $data['data']['content'] );
		$this->assertEquals( strlen( $css ), $data['data']['content_length'] );
		$this->assertEquals( 'twentytwentyfive', $data['data']['stylesheet'] );
	}

	/**
	 * Test handle_get_css returns error when manager returns WP_Error.
	 */
	public function test_handle_get_css_error() {
		$this->manager->method( 'get_css' )
			->willReturn( new \WP_Error( 'not_found', 'Global styles not found' ) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/css' );
		$response = $this->controller->handle_get_css( $request );

		$this->assertEquals( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'not_found', $data['code'] );
	}

	/**
	 * Test handle_patch_css returns 200 and logs mutation.
	 */
	public function test_handle_patch_css_success() {
		$new_css = '.bar { display: none; }';
		$this->manager->method( 'update_css' )->willReturn( array(
			'last_modified' => '2026-03-22T10:05:00',
			'stylesheet'    => 'twentytwentyfive',
			'post_id'       => 9,
		) );

		$this->audit_logger->expects( $this->once() )
			->method( 'log_mutation' );

		$request = new WP_REST_Request( 'PATCH', '/ai-gateway/v1/global-styles/css' );
		$request->set_body( json_encode( array( 'content' => $new_css ) ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->controller->handle_patch_css( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $new_css, $data['data']['content'] );
	}

	/**
	 * Test handle_patch_css returns 400 when content field is missing.
	 */
	public function test_handle_patch_css_missing_content() {
		$request = new WP_REST_Request( 'PATCH', '/ai-gateway/v1/global-styles/css' );
		$request->set_body( json_encode( array( 'wrong_field' => 'value' ) ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->controller->handle_patch_css( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'missing_field', $data['code'] );
	}

	/**
	 * Test handle_patch_css returns error when manager returns WP_Error.
	 */
	public function test_handle_patch_css_manager_error() {
		$this->manager->method( 'update_css' )
			->willReturn( new \WP_Error( 'update_failed', 'Could not update', array( 'status' => 500 ) ) );

		$request = new WP_REST_Request( 'PATCH', '/ai-gateway/v1/global-styles/css' );
		$request->set_body( json_encode( array( 'content' => 'test' ) ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->controller->handle_patch_css( $request );

		$this->assertEquals( 500, $response->get_status() );
	}

	/**
	 * Test handle_get_styles_json returns 200 with styles.
	 */
	public function test_handle_get_styles_json_success() {
		$styles = array(
			'typography' => array( 'fontFamily' => 'Inter' ),
			'color'      => array( 'text' => '#111' ),
		);
		$this->manager->method( 'get_styles_json' )->willReturn( $styles );
		$this->manager->method( 'get_metadata' )->willReturn( array(
			'last_modified' => '2026-03-22T10:00:00',
			'stylesheet'    => 'twentytwentyfive',
			'post_id'       => 9,
		) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/json' );
		$response = $this->controller->handle_get_styles_json( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $styles, $data['data']['styles'] );
		$this->assertEquals( 9, $data['data']['post_id'] );
	}

	/**
	 * Test handle_get_styles_json returns error when manager fails.
	 */
	public function test_handle_get_styles_json_error() {
		$this->manager->method( 'get_styles_json' )
			->willReturn( new \WP_Error( 'json_error', 'JSON parse failed' ) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/json' );
		$response = $this->controller->handle_get_styles_json( $request );

		$this->assertEquals( 500, $response->get_status() );
	}

	/**
	 * Test handle_patch_styles_json returns 200 and logs mutation.
	 */
	public function test_handle_patch_styles_json_success() {
		$this->manager->method( 'merge_styles_json' )->willReturn( array(
			'last_modified' => '2026-03-22T10:10:00',
			'stylesheet'    => 'twentytwentyfive',
			'post_id'       => 9,
		) );
		$this->manager->method( 'get_styles_json' )->willReturn( array(
			'typography' => array( 'fontFamily' => 'Inter' ),
		) );

		$this->audit_logger->expects( $this->once() )
			->method( 'log_mutation' );

		$request = new WP_REST_Request( 'PATCH', '/ai-gateway/v1/global-styles/json' );
		$request->set_body( json_encode( array( 'typography' => array( 'fontFamily' => 'Inter' ) ) ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->controller->handle_patch_styles_json( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test handle_patch_styles_json returns 400 for empty payload.
	 */
	public function test_handle_patch_styles_json_empty_payload() {
		$request = new WP_REST_Request( 'PATCH', '/ai-gateway/v1/global-styles/json' );
		$request->set_body( '{}' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->controller->handle_patch_styles_json( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'invalid_payload', $data['code'] );
	}

	/**
	 * Test handle_repair returns 200 on success and logs mutation.
	 */
	public function test_handle_repair_success() {
		$this->manager->method( 'repair' )->willReturn( array(
			'status' => 'repaired',
		) );

		$this->audit_logger->expects( $this->once() )
			->method( 'log_mutation' );

		$request  = new WP_REST_Request( 'POST', '/ai-gateway/v1/global-styles/repair' );
		$response = $this->controller->handle_repair( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test handle_repair returns error when manager returns WP_Error.
	 */
	public function test_handle_repair_error() {
		$this->manager->method( 'repair' )
			->willReturn( new \WP_Error( 'repair_failed', 'Cannot repair', array( 'status' => 500 ) ) );

		$request  = new WP_REST_Request( 'POST', '/ai-gateway/v1/global-styles/repair' );
		$response = $this->controller->handle_repair( $request );

		$this->assertEquals( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
	}

	/**
	 * Test handle_debug returns 200 with diagnostic data.
	 */
	public function test_handle_debug_success() {
		$post = (object) array(
			'ID'           => 9,
			'post_type'    => 'wp_global_styles',
			'post_status'  => 'publish',
			'post_content' => json_encode( array(
				'styles'   => array( 'css' => '.test { color: red; }' ),
				'settings' => array(),
			) ),
		);
		$this->manager->method( 'get_global_styles_post' )->willReturn( $post );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/debug' );
		$response = $this->controller->handle_debug( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertTrue( $data['data']['post_found'] );
		$this->assertTrue( $data['data']['json_valid'] );
		$this->assertTrue( $data['data']['has_styles_css'] );
	}

	/**
	 * Test handle_debug when no post found.
	 */
	public function test_handle_debug_no_post() {
		$this->manager->method( 'get_global_styles_post' )->willReturn( null );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/debug' );
		$response = $this->controller->handle_debug( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertFalse( $data['data']['post_found'] );
	}

	/**
	 * Test handle_get_css catches exceptions and returns safe error.
	 */
	public function test_handle_get_css_exception_handling() {
		$this->manager->method( 'get_css' )
			->willThrowException( new \RuntimeException( 'Database connection lost' ) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/global-styles/css' );
		$response = $this->controller->handle_get_css( $request );

		$this->assertEquals( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'global_styles_error', $data['code'] );
	}
}
