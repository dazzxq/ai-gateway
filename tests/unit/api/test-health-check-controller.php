<?php
/**
 * Unit Tests for HealthCheckController
 *
 * @package AI_Gateway\Tests\Unit\API
 */

namespace AI_Gateway\Tests\Unit\API;

use AI_Gateway\API\HealthCheckController;
use AI_Gateway\Observability\HealthChecker;
use WP_REST_Request;

/**
 * Test_HealthCheckController
 *
 * Tests for HealthCheckController class.
 */
class Test_HealthCheckController extends \WP_UnitTestCase {

	/**
	 * HealthCheckController instance.
	 *
	 * @var HealthCheckController
	 */
	private $controller;

	/**
	 * HealthChecker mock.
	 *
	 * @var HealthChecker|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $health_checker;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->health_checker = $this->createMock( HealthChecker::class );
		$this->controller     = new HealthCheckController( $this->health_checker );
	}

	/**
	 * Test controller instantiation.
	 */
	public function test_controller_exists() {
		$this->assertInstanceOf( HealthCheckController::class, $this->controller );
	}

	/**
	 * Test check_health_callback returns 200 for healthy status.
	 */
	public function test_check_health_returns_200_for_healthy() {
		$this->health_checker->method( 'get_health_status' )->willReturn( array(
			'status'            => 'healthy',
			'checks'            => array(
				'rest_api'    => array( 'status' => 'ok', 'message' => 'REST API accessible' ),
				'wp_options'  => array( 'status' => 'ok', 'message' => 'wp_options working' ),
				'filesystem'  => array( 'status' => 'ok', 'message' => 'Filesystem accessible' ),
				'php_version' => array( 'status' => 'ok', 'message' => 'PHP 8.4 meets requirements' ),
			),
			'gateway_version'   => '0.6.0',
			'php_version'       => '8.4.0',
			'wordpress_version' => '6.9.4',
			'active_theme'      => 'Twenty Twenty-Five',
			'plugin_count'      => 5,
			'acf_active'        => true,
		) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/health' );
		$response = $this->controller->check_health_callback( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'healthy', $data['status'] );
		$this->assertArrayHasKey( 'checks', $data );
		$this->assertArrayHasKey( 'gateway_version', $data );
	}

	/**
	 * Test check_health_callback returns 200 for degraded status.
	 */
	public function test_check_health_returns_200_for_degraded() {
		$this->health_checker->method( 'get_health_status' )->willReturn( array(
			'status'            => 'degraded',
			'checks'            => array(
				'rest_api'    => array( 'status' => 'ok', 'message' => 'REST API accessible' ),
				'wp_options'  => array( 'status' => 'ok', 'message' => 'wp_options working' ),
				'filesystem'  => array( 'status' => 'warning', 'message' => 'Filesystem write restricted' ),
				'php_version' => array( 'status' => 'ok', 'message' => 'PHP 8.4 meets requirements' ),
			),
			'gateway_version'   => '0.6.0',
			'php_version'       => '8.4.0',
			'wordpress_version' => '6.9.4',
			'active_theme'      => 'Twenty Twenty-Five',
			'plugin_count'      => 5,
			'acf_active'        => true,
		) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/health' );
		$response = $this->controller->check_health_callback( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'degraded', $data['status'] );
	}

	/**
	 * Test check_health_callback returns 503 for unhealthy status.
	 */
	public function test_check_health_returns_503_for_unhealthy() {
		$this->health_checker->method( 'get_health_status' )->willReturn( array(
			'status'            => 'unhealthy',
			'checks'            => array(
				'rest_api'    => array( 'status' => 'ok', 'message' => 'REST API accessible' ),
				'wp_options'  => array( 'status' => 'ok', 'message' => 'wp_options working' ),
				'filesystem'  => array( 'status' => 'ok', 'message' => 'Filesystem accessible' ),
				'php_version' => array( 'status' => 'critical', 'message' => 'PHP 7.0 below minimum' ),
			),
			'gateway_version'   => '0.6.0',
			'php_version'       => '7.0.0',
			'wordpress_version' => '6.9.4',
			'active_theme'      => 'Twenty Twenty-Five',
			'plugin_count'      => 5,
			'acf_active'        => false,
		) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/health' );
		$response = $this->controller->check_health_callback( $request );

		$this->assertEquals( 503, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'unhealthy', $data['status'] );
	}

	/**
	 * Test check_health_callback response includes expected fields.
	 */
	public function test_check_health_response_has_required_fields() {
		$this->health_checker->method( 'get_health_status' )->willReturn( array(
			'status'            => 'healthy',
			'checks'            => array(),
			'gateway_version'   => '0.6.0',
			'php_version'       => '8.4.0',
			'wordpress_version' => '6.9.4',
			'active_theme'      => 'Twenty Twenty-Five',
			'plugin_count'      => 5,
			'acf_active'        => true,
		) );

		$request  = new WP_REST_Request( 'GET', '/ai-gateway/v1/health' );
		$response = $this->controller->check_health_callback( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'checks', $data );
		$this->assertArrayHasKey( 'gateway_version', $data );
		$this->assertArrayHasKey( 'php_version', $data );
		$this->assertArrayHasKey( 'wordpress_version', $data );
		$this->assertArrayHasKey( 'active_theme', $data );
		$this->assertArrayHasKey( 'plugin_count', $data );
		$this->assertArrayHasKey( 'acf_active', $data );
	}
}
