<?php
/**
 * Unit Tests for OptionsManager
 *
 * @package AI_Gateway\Tests\Unit\ContentManagement
 */

namespace AI_Gateway\Tests\Unit\ContentManagement;

use AI_Gateway\ContentManagement\OptionsManager;

/**
 * Test_OptionsManager
 *
 * Tests for OptionsManager class.
 */
class Test_OptionsManager extends \WP_UnitTestCase {

	/**
	 * OptionsManager instance.
	 *
	 * @var OptionsManager
	 */
	private $options_manager;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->options_manager = new OptionsManager();
	}

	/**
	 * Test 1: get_safe_options returns array.
	 *
	 * @test
	 */
	public function test_get_safe_options_returns_array() {
		$options = $this->options_manager->get_safe_options();
		$this->assertIsArray( $options );
	}

	/**
	 * Test 2: get_safe_options includes siteurl.
	 *
	 * @test
	 */
	public function test_get_safe_options_includes_site_url() {
		$options = $this->options_manager->get_safe_options();
		$this->assertArrayHasKey( 'siteurl', $options );
	}

	/**
	 * Test 3: get_safe_options includes all 8 keys.
	 *
	 * @test
	 */
	public function test_get_safe_options_includes_all_keys() {
		$options = $this->options_manager->get_safe_options();

		$expected_keys = array(
			'siteurl',
			'home',
			'blogname',
			'blogdescription',
			'timezone_string',
			'permalink_structure',
			'date_format',
			'time_format',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $options );
		}
	}

	/**
	 * Test 4: get_safe_options does not include sensitive keys.
	 *
	 * @test
	 */
	public function test_get_safe_options_excludes_sensitive_keys() {
		$options = $this->options_manager->get_safe_options();
		$this->assertArrayNotHasKey( 'admin_email', $options );
		// siteurl is NOT sensitive - it's public information, so it should be included
		// Only exclude truly sensitive keys
	}
}
