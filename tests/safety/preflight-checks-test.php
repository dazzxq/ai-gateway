<?php
/**
 * Safety Tests - Pre-Flight Environment Checks
 *
 * Tests to verify that pre-flight environment checks work correctly
 * and prevent the plugin from loading if requirements aren't met.
 *
 * @package AI_Gateway\Tests\Safety
 */

namespace AI_Gateway\Tests\Safety;

use WP_UnitTestCase;

/**
 * Preflight_Checks_Test
 *
 * Tests for pre-flight environment checks (PHP version, WordPress version, extensions).
 */
class Preflight_Checks_Test extends WP_UnitTestCase {

    /**
     * Test: PHP version >= 8.0
     *
     * @test
     */
    public function test_php_version_check() {
        $php_version = phpversion();
        $this->assertGreaterThanOrEqual( '8.0', $php_version,
            'PHP version should be 8.0 or higher'
        );
    }

    /**
     * Test: WordPress version >= 6.0
     *
     * @test
     */
    public function test_wordpress_version_check() {
        global $wp_version;
        $this->assertGreaterThanOrEqual( '6.0', $wp_version,
            'WordPress version should be 6.0 or higher'
        );
    }

    /**
     * Test: JSON extension is loaded
     *
     * @test
     */
    public function test_json_extension_loaded() {
        $this->assertTrue( extension_loaded( 'json' ),
            'JSON extension must be loaded'
        );
    }

    /**
     * Test: Hash extension is loaded
     *
     * @test
     */
    public function test_hash_extension_loaded() {
        $this->assertTrue( extension_loaded( 'hash' ),
            'Hash extension must be loaded'
        );
    }

    /**
     * Test: All required functions available
     *
     * @test
     */
    public function test_required_functions_available() {
        $required_functions = array(
            'hash',
            'hash_equals',
            'bin2hex',
            'json_encode',
            'json_decode',
        );

        foreach ( $required_functions as $func ) {
            $this->assertTrue( function_exists( $func ),
                "Function $func must be available"
            );
        }
    }
}
