<?php
/**
 * Unit Tests for SettingsController
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\SettingsController;
use AI_Gateway\ContentManagement\OptionsManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Security\PermissionChecker;
use WP_REST_Request;

/**
 * Test_SettingsController
 *
 * Tests for SettingsController class.
 */
class Test_SettingsController extends \WP_UnitTestCase {

	/**
	 * SettingsController instance.
	 *
	 * @var SettingsController
	 */
	private $controller;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		$options_manager    = new OptionsManager();
		$permission_checker = $this->createMock( PermissionChecker::class );
		$audit_logger       = $this->createMock( AuditLogger::class );
		$this->controller   = new SettingsController( $options_manager, $permission_checker, $audit_logger );
	}

	/**
	 * Test 1: read_settings_callback returns 200.
	 *
	 * @test
	 */
	public function test_read_settings_success() {
		$request = new WP_REST_Request( 'GET', '/settings' );
		$response = $this->controller->read_settings_callback( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test 2: read_settings returns safe options.
	 *
	 * @test
	 */
	public function test_read_settings_returns_safe_options() {
		$request = new WP_REST_Request( 'GET', '/settings' );
		$response = $this->controller->read_settings_callback( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'siteurl', $data['data'] );
	}
}
