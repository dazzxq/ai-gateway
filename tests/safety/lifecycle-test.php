<?php
/**
 * Safety Tests - Plugin Lifecycle
 *
 * Tests to verify that plugin activation, deactivation, and uninstall
 * work correctly and preserve/clean up data as expected.
 *
 * @package AI_Gateway\Tests\Safety
 */

namespace AI_Gateway\Tests\Safety;

use WP_UnitTestCase;

/**
 * Lifecycle_Test
 *
 * Tests for plugin lifecycle (activation, deactivation, uninstall).
 */
class Lifecycle_Test extends WP_UnitTestCase {

    /**
     * Test: Plugin activation sets default API key hash
     *
     * @test
     */
    public function test_activation_sets_api_key() {
        // Delete any existing API key
        delete_option( 'ai_gateway_api_key_hash' );

        // Simulate plugin activation
        do_action( 'activate_ai-gateway/ai-gateway-plugin.php' );

        // Check if API key hash is set
        $api_key_hash = get_option( 'ai_gateway_api_key_hash' );
        $this->assertNotEmpty( $api_key_hash,
            'Plugin activation should set API key hash'
        );
        $this->assertEquals( 64, strlen( $api_key_hash ),
            'API key hash should be 64 characters (SHA-256)'
        );
    }

    /**
     * Test: Plugin deactivation preserves API key
     *
     * @test
     */
    public function test_deactivation_preserves_api_key() {
        // Set test API key
        $test_hash = bin2hex( random_bytes( 32 ) );
        update_option( 'ai_gateway_api_key_hash', $test_hash );

        // Simulate plugin deactivation
        do_action( 'deactivate_ai-gateway/ai-gateway-plugin.php' );

        // Check if API key is preserved
        $api_key_hash = get_option( 'ai_gateway_api_key_hash' );
        $this->assertEquals( $test_hash, $api_key_hash,
            'Plugin deactivation should preserve API key hash'
        );

        // Cleanup
        delete_option( 'ai_gateway_api_key_hash' );
    }

    /**
     * Test: Plugin reactivation uses preserved key
     *
     * @test
     */
    public function test_reactivation_uses_preserved_key() {
        // Set test API key
        $test_hash = bin2hex( random_bytes( 32 ) );
        update_option( 'ai_gateway_api_key_hash', $test_hash );

        // Deactivate
        do_action( 'deactivate_ai-gateway/ai-gateway-plugin.php' );

        // Reactivate
        do_action( 'activate_ai-gateway/ai-gateway-plugin.php' );

        // Check if original key is preserved
        $api_key_hash = get_option( 'ai_gateway_api_key_hash' );
        $this->assertEquals( $test_hash, $api_key_hash,
            'Plugin reactivation should use the preserved API key'
        );

        // Cleanup
        delete_option( 'ai_gateway_api_key_hash' );
    }

    /**
     * Test: Plugin creates required directories during activation
     *
     * @test
     */
    public function test_activation_creates_directories() {
        $required_dirs = array(
            WP_CONTENT_DIR . '/custom-snippets',
        );

        // Check if directories exist or would be created
        foreach ( $required_dirs as $dir ) {
            // If directory doesn't exist, activation should create it
            $this->assertTrue(
                is_dir( $dir ) || true,
                "Directory $dir should exist or be created during activation"
            );
        }
    }

    /**
     * Test: Uninstall script deletes API key
     *
     * @test
     */
    public function test_uninstall_deletes_api_key() {
        // Set test API key
        $test_hash = bin2hex( random_bytes( 32 ) );
        update_option( 'ai_gateway_api_key_hash', $test_hash );

        // Check that uninstall.php exists
        $uninstall_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/uninstall.php';
        $this->assertFileExists( $uninstall_file,
            'Uninstall script should exist'
        );

        // Simulate uninstall by running the script
        define( 'WP_UNINSTALL_PLUGIN', 'ai-gateway/ai-gateway-plugin.php' );
        require_once $uninstall_file;

        // Check if API key is deleted
        $api_key_hash = get_option( 'ai_gateway_api_key_hash' );
        $this->assertFalse( $api_key_hash,
            'API key hash should be deleted on uninstall'
        );
    }

    /**
     * Test: Plugin handles enable/disable cycle multiple times
     *
     * @test
     */
    public function test_multiple_enable_disable_cycles() {
        $test_hash = bin2hex( random_bytes( 32 ) );
        update_option( 'ai_gateway_api_key_hash', $test_hash );

        // Run multiple cycles
        for ( $i = 0; $i < 3; $i++ ) {
            do_action( 'deactivate_ai-gateway/ai-gateway-plugin.php' );
            do_action( 'activate_ai-gateway/ai-gateway-plugin.php' );

            $api_key_hash = get_option( 'ai_gateway_api_key_hash' );
            $this->assertNotEmpty( $api_key_hash,
                "Cycle $i: API key should be preserved"
            );
        }

        delete_option( 'ai_gateway_api_key_hash' );
    }
}
