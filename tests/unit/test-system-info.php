<?php
/**
 * Unit Tests for SystemInfoGetter
 *
 * @package AI_Gateway\Tests\Unit
 */

namespace AI_Gateway\Tests\Unit;

use AI_Gateway\Observability\SystemInfoGetter;

/**
 * Test_SystemInfoGetter
 *
 * Tests for the SystemInfoGetter class.
 */
class Test_SystemInfoGetter extends \WP_UnitTestCase {
	/**
	 * Test 1: Get system info returns all required fields.
	 *
	 * @test
	 */
	public function test_get_system_info_returns_all_fields() {
		$getter = new SystemInfoGetter();
		$info = $getter->get_system_info();

		$this->assertArrayHasKey( 'wordpress_version', $info );
		$this->assertArrayHasKey( 'php_version', $info );
		$this->assertArrayHasKey( 'server_software', $info );
		$this->assertArrayHasKey( 'active_theme', $info );
		$this->assertArrayHasKey( 'theme_version', $info );
		$this->assertArrayHasKey( 'mysql_version', $info );
		$this->assertArrayHasKey( 'active_plugins_count', $info );
		$this->assertArrayHasKey( 'debug_enabled', $info );
		$this->assertArrayHasKey( 'gateway_version', $info );
	}

	/**
	 * Test 2: Versions are non-empty strings.
	 *
	 * @test
	 */
	public function test_versions_are_non_empty_strings() {
		$getter = new SystemInfoGetter();
		$info = $getter->get_system_info();

		$this->assertNotEmpty( $info['wordpress_version'] );
		$this->assertNotEmpty( $info['php_version'] );
		$this->assertNotEmpty( $info['active_theme'] );
		$this->assertNotEmpty( $info['gateway_version'] );
	}

	/**
	 * Test 3: Active plugins count is integer.
	 *
	 * @test
	 */
	public function test_active_plugins_count_is_integer() {
		$getter = new SystemInfoGetter();
		$info = $getter->get_system_info();

		$this->assertIsInt( $info['active_plugins_count'] );
		$this->assertGreaterThanOrEqual( 0, $info['active_plugins_count'] );
	}

	/**
	 * Test 4: Debug enabled is boolean.
	 *
	 * @test
	 */
	public function test_debug_enabled_is_boolean() {
		$getter = new SystemInfoGetter();
		$info = $getter->get_system_info();

		$this->assertIsBool( $info['debug_enabled'] );
	}
}
