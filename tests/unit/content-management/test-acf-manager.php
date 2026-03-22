<?php
/**
 * Unit Tests for ACFManager
 *
 * @package AI_Gateway\Tests\Unit\ContentManagement
 */

namespace AI_Gateway\Tests\Unit\ContentManagement;

use AI_Gateway\ContentManagement\ACFManager;
use AI_Gateway\Exceptions\GatewayException;

/**
 * Test_ACFManager
 *
 * Tests for ACFManager class.
 */
class Test_ACFManager extends \WP_UnitTestCase {

	/**
	 * ACFManager instance.
	 *
	 * @var ACFManager
	 */
	private $acf_manager;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->acf_manager = new ACFManager();
	}

	/**
	 * Test 1: is_active returns correct status.
	 *
	 * @test
	 */
	public function test_is_active_method_exists() {
		$is_active = $this->acf_manager->is_active();
		$this->assertIsBool( $is_active );
	}

	/**
	 * Test 2: get_field_groups throws exception when ACF not active.
	 *
	 * @test
	 */
	public function test_get_field_groups_requires_acf() {
		// If ACF is not active, should throw exception.
		if ( ! $this->acf_manager->is_active() ) {
			$this->expectException( GatewayException::class );
			$this->acf_manager->get_field_groups();
		} else {
			// If ACF is active, should return array.
			$result = $this->acf_manager->get_field_groups();
			$this->assertIsArray( $result );
		}
	}

	/**
	 * Test 3: get_field_value with non-existent field.
	 *
	 * @test
	 */
	public function test_get_field_value_not_found() {
		if ( ! $this->acf_manager->is_active() ) {
			$this->expectException( GatewayException::class );
			$this->acf_manager->get_field_value( 1, 'nonexistent_field' );
		} else {
			// Field doesn't exist.
			$this->expectException( GatewayException::class );
			$this->acf_manager->get_field_value( 1, 'nonexistent_field' );
		}
	}

	/**
	 * Test 4: get_fields returns empty array when ACF not active.
	 *
	 * @test
	 */
	public function test_get_fields_when_acf_inactive() {
		$post_id = self::factory()->post->create();
		$fields = $this->acf_manager->get_fields( $post_id );

		// Should return empty array when ACF inactive.
		if ( ! $this->acf_manager->is_active() ) {
			$this->assertIsArray( $fields );
		}
	}

	/**
	 * Test 5: update_field_value requires ACF to be active.
	 *
	 * @test
	 */
	public function test_update_field_value_requires_acf() {
		$post_id = self::factory()->post->create();

		if ( ! $this->acf_manager->is_active() ) {
			$this->expectException( GatewayException::class );
			$this->acf_manager->update_field_value( $post_id, 'field', 'value' );
		}
	}

	/**
	 * Test 6: get_fields returns array structure.
	 *
	 * @test
	 */
	public function test_get_fields_returns_array() {
		$post_id = self::factory()->post->create();
		$result = $this->acf_manager->get_fields( $post_id );
		$this->assertIsArray( $result );
	}

	/**
	 * Test 7: is_active method returns boolean.
	 *
	 * @test
	 */
	public function test_is_active_returns_boolean() {
		$is_active = $this->acf_manager->is_active();
		$this->assertIsBool( $is_active );
	}

	/**
	 * Test 8: get_field_groups returns array of groups.
	 *
	 * @test
	 */
	public function test_get_field_groups_returns_array_or_exception() {
		if ( $this->acf_manager->is_active() ) {
			$groups = $this->acf_manager->get_field_groups();
			$this->assertIsArray( $groups );
		} else {
			// Should throw exception when ACF not active.
			$this->expectException( GatewayException::class );
			$this->acf_manager->get_field_groups();
		}
	}
}
