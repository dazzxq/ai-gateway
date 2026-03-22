<?php
/**
 * Unit Tests for ACFController
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\ACFController;
use AI_Gateway\ContentManagement\ACFManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use AI_Gateway\Exceptions\GatewayException;
use WP_REST_Request;

/**
 * Test_ACFController
 *
 * Tests for ACFController class.
 */
class Test_ACFController extends \WP_UnitTestCase {

	/**
	 * ACFController instance.
	 *
	 * @var ACFController
	 */
	private $controller;

	/**
	 * ACFManager mock.
	 *
	 * @var ACFManager
	 */
	private $acf_manager;

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

		$this->acf_manager = $this->createMock( ACFManager::class );
		$this->permission_checker = $this->createMock( PermissionChecker::class );
		$this->audit_logger = $this->createMock( AuditLogger::class );

		$this->controller = new ACFController(
			$this->acf_manager,
			$this->permission_checker,
			$this->audit_logger
		);
	}

	/**
	 * Test 1: list_field_groups returns 200 when ACF active.
	 *
	 * @test
	 */
	public function test_list_field_groups_success() {
		$this->acf_manager->method( 'get_field_groups' )->willReturn(
			array(
				array( 'id' => 1, 'title' => 'Group 1' ),
			)
		);

		$request = new WP_REST_Request( 'GET', '/acf/field-groups' );
		$response = $this->controller->list_field_groups_callback( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test 2: list_field_groups returns 503 when ACF Pro inactive.
	 *
	 * @test
	 */
	public function test_list_field_groups_acf_inactive() {
		$this->acf_manager->method( 'get_field_groups' )
			->willThrowException(
				new GatewayException( 'ACF Pro inactive', 'acf_pro_inactive' )
			);

		$request = new WP_REST_Request( 'GET', '/acf/field-groups' );
		$response = $this->controller->list_field_groups_callback( $request );

		$this->assertEquals( 503, $response->get_status() );
	}

	/**
	 * Test 3: update_field returns 200.
	 *
	 * @test
	 */
	public function test_update_field_success() {
		$this->acf_manager->method( 'update_field_value' )->willReturn( 'new_value' );
		$this->audit_logger->expects( $this->once() )
			->method( 'log_mutation' );

		$post_id = self::factory()->post->create();

		$request = new WP_REST_Request( 'PATCH', '/posts/' . $post_id . '/acf/my_field' );
		$request->set_param( 'id', $post_id );
		$request->set_param( 'field_name', 'my_field' );
		$request->set_body( json_encode( array( 'value' => 'new_value' ) ) );

		$response = $this->controller->update_field_callback( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test 4: update_field returns 404 for invalid post.
	 *
	 * @test
	 */
	public function test_update_field_post_not_found() {
		$request = new WP_REST_Request( 'PATCH', '/posts/999/acf/field' );
		$request->set_param( 'id', 999 );
		$request->set_param( 'field_name', 'field' );
		$request->set_body( json_encode( array( 'value' => 'val' ) ) );

		$response = $this->controller->update_field_callback( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test 5: update_field returns 503 when ACF Pro inactive.
	 *
	 * @test
	 */
	public function test_update_field_acf_inactive() {
		$post_id = self::factory()->post->create();

		$this->acf_manager->method( 'update_field_value' )
			->willThrowException(
				new GatewayException( 'ACF Pro inactive', 'acf_pro_inactive' )
			);

		$request = new WP_REST_Request( 'PATCH', '/posts/' . $post_id . '/acf/field' );
		$request->set_param( 'id', $post_id );
		$request->set_param( 'field_name', 'field' );
		$request->set_body( json_encode( array( 'value' => 'val' ) ) );

		$response = $this->controller->update_field_callback( $request );

		$this->assertEquals( 503, $response->get_status() );
	}
}
