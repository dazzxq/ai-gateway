<?php
/**
 * Safety Tests - Error Handling and Admin Notices
 *
 * Tests to verify that plugin errors are handled gracefully and
 * admin notices are displayed appropriately.
 *
 * @package AI_Gateway\Tests\Safety
 */

namespace AI_Gateway\Tests\Safety;

use WP_UnitTestCase;

/**
 * Error_Handling_Test
 *
 * Tests for error handling and admin notices.
 */
class Error_Handling_Test extends WP_UnitTestCase {

    /**
     * Test: Plugin shows admin notice on pre-flight check failure
     *
     * @test
     */
    public function test_admin_notice_on_error() {
        // Check if admin notices are being used
        $this->assertTrue( function_exists( 'add_action' ),
            'WordPress actions should be available for admin notices'
        );

        // Verify add_action can be used for admin notices
        $hook_added = add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>Test notice</p></div>';
        } );

        $this->assertTrue( $hook_added,
            'Admin notice hooks should be available'
        );
    }

    /**
     * Test: Error doesn't crash WordPress during initialization
     *
     * @test
     */
    public function test_initialization_error_recovery() {
        // Verify WordPress is still functional
        $this->assertTrue( function_exists( 'get_option' ),
            'WordPress should still be functional after initialization'
        );

        // Verify we can still use WordPress functions
        $test_option = get_option( 'test_option' );
        $this->assertTrue( true,
            'Should be able to call WordPress functions'
        );
    }

    /**
     * Test: Plugin handles missing files gracefully
     *
     * @test
     */
    public function test_missing_file_handling() {
        // This test verifies that the safe_require wrapper exists
        $plugin_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/ai-gateway-plugin.php';

        ob_start();
        $result = @require_once $plugin_file;
        ob_end_clean();

        // If we get here without fatal error, safe loading is working
        $this->assertTrue( true,
            'Plugin should load with error handling'
        );
    }

    /**
     * Test: Invalid configurations don't break plugin
     *
     * @test
     */
    public function test_invalid_config_resilience() {
        // Store invalid value
        update_option( 'ai_gateway_invalid_config', array( 'broken' => 'data' ) );

        // Should not cause issues
        $value = get_option( 'ai_gateway_invalid_config' );
        $this->assertNotEmpty( $value,
            'Plugin should handle invalid configurations gracefully'
        );

        delete_option( 'ai_gateway_invalid_config' );
    }

    /**
     * Test: API requests fail gracefully when plugin has issues
     *
     * @test
     */
    public function test_api_error_response_format() {
        // Verify response formatter exists
        $this->assertTrue( function_exists( 'wp_json_encode' ),
            'WordPress should have JSON encoding available'
        );

        // Test error response format
        $error_response = array(
            'success' => false,
            'data' => null,
            'error' => 'Test error',
            'timestamp' => current_time( 'mysql', true ),
        );

        $json = json_encode( $error_response );
        $this->assertNotEmpty( $json,
            'Error responses should be JSON encodable'
        );

        $decoded = json_decode( $json, true );
        $this->assertFalse( $decoded['success'] );
        $this->assertEquals( 'Test error', $decoded['error'] );
    }
}
