<?php
/**
 * PHPUnit Bootstrap
 *
 * Load WordPress, plugin classes, and test helpers for test suite execution.
 */

// Define test mode constant
if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

// Locate wp-load.php
$wp_load_path = null;
$current_dir = dirname(__FILE__);

// Try multiple paths to find wp-load.php
$possible_paths = array(
    dirname($current_dir) . '/../../wp-load.php',  // Tests in plugin directory
    dirname($current_dir) . '/../../../wp-load.php', // If plugin in subdirectory
    dirname($current_dir) . '/../../../../wp-load.php', // Multiple subdirectories
);

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
}

if (!$wp_load_path) {
    // Try composer vendor autoload
    $vendor_paths = array(
        dirname($current_dir) . '/../../vendor/autoload.php',
        dirname($current_dir) . '/../../../vendor/autoload.php',
    );

    foreach ($vendor_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }

    die('ERROR: Could not locate wp-load.php. Please ensure WordPress is installed in the parent directory.');
}

// Load WordPress
require_once $wp_load_path;

// Define plugin constants
if (!defined('PLUGIN_DIR')) {
    define('PLUGIN_DIR', dirname(dirname(__FILE__)));
}
if (!defined('PLUGIN_URL')) {
    define('PLUGIN_URL', plugins_url('ai-gateway'));
}

// Load plugin main file to ensure classes are available
require_once dirname(dirname(__FILE__)) . '/ai-gateway-plugin.php';

// Factory helper classes
if (!class_exists('WP_UnitTest_Factory_For_Post')) {
    class WP_UnitTest_Factory_For_Post {
        public function create($args = array()) {
            $defaults = array(
                'post_title' => 'Test Post ' . wp_generate_password(6, false),
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'post',
            );
            $args = wp_parse_args($args, $defaults);
            return wp_insert_post($args);
        }
    }
}

if (!class_exists('WP_UnitTest_Factory')) {
    class WP_UnitTest_Factory {
        public $post;

        public function __construct() {
            $this->post = new WP_UnitTest_Factory_For_Post();
        }
    }
}

// Provide WP_UnitTestCase shim if WordPress test library is not installed
// This allows tests to extend WP_UnitTestCase while using standard PHPUnit
if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
        protected static $test_posts = array();
        protected static $factory;

        public static function setUpBeforeClass(): void {
            parent::setUpBeforeClass();
            if (!isset(self::$factory)) {
                self::$factory = new WP_UnitTest_Factory();
            }
        }

        public static function factory() {
            if (!isset(self::$factory)) {
                self::$factory = new WP_UnitTest_Factory();
            }
            return self::$factory;
        }

        public function setUp(): void {
            parent::setUp();
        }

        public function tearDown(): void {
            parent::tearDown();
        }

        /**
         * Assert WP_Error
         */
        public function assertWPError($actual, $message = '') {
            $this->assertInstanceOf('WP_Error', $actual, $message);
        }

        /**
         * Assert not WP_Error
         */
        public function assertNotWPError($actual, $message = '') {
            $this->assertNotInstanceOf('WP_Error', $actual, $message);
        }

        /**
         * Factory-like helper to create posts
         */
        protected static function factory_create_post($args = array()) {
            $defaults = array(
                'post_title' => 'Test Post ' . wp_generate_password(6, false),
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'post',
            );
            $args = wp_parse_args($args, $defaults);
            $post_id = wp_insert_post($args);
            if (!is_wp_error($post_id)) {
                self::$test_posts[] = $post_id;
            }
            return $post_id;
        }
    }
}

// Load test helper class
require_once dirname(__FILE__) . '/helpers/test-helpers.php';

// Make sure test directories exist
$test_dirs = array(
    dirname(__FILE__) . '/unit',
    dirname(__FILE__) . '/integration',
    dirname(__FILE__) . '/api',
    dirname(__FILE__) . '/safety',
    dirname(__FILE__) . '/fixtures',
    dirname(__FILE__) . '/helpers',
    dirname(__FILE__) . '/coverage',
);

foreach ($test_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
