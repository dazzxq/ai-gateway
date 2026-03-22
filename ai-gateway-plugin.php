<?php
/**
 * Plugin Name: AI Gateway for WordPress
 * Plugin URI: https://github.com/theduyet/ai-gateway
 * Description: Secure REST API for AI assistants and automation tools to manage WordPress content
 * Version: 0.7.0
 * Author: Duyet
 * Author URI: https://duyet.dev
 * License: MIT
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.0
 *
 * @package AI_Gateway
 */

// Verify WordPress is loaded.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRE-FLIGHT CHECKS
 *
 * Before loading any plugin classes, verify environment compatibility.
 * If any check fails, the plugin is automatically deactivated and an
 * admin notice is displayed explaining the problem.
 *
 * Checks performed:
 * - PHP version >= 8.0
 * - WordPress version >= 6.0
 * - Required PHP extensions: json, hash
 */

// Check PHP version.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			ai_gateway_display_admin_notice(
				sprintf(
					'AI Gateway requires PHP 8.0 or higher. Your server is running PHP %s. Please contact your hosting provider to upgrade PHP.',
					esc_html( PHP_VERSION )
				),
				'error'
			);
		}
	);
	deactivate_plugins( plugin_basename( __FILE__ ) );
	return;
}

// Check WordPress version.
if ( version_compare( $GLOBALS['wp_version'], '6.0', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			ai_gateway_display_admin_notice(
				sprintf(
					'AI Gateway requires WordPress 6.0 or higher. Your site is running WordPress %s. Please upgrade WordPress.',
					esc_html( $GLOBALS['wp_version'] )
				),
				'error'
			);
		}
	);
	deactivate_plugins( plugin_basename( __FILE__ ) );
	return;
}

// Check required PHP extensions.
$required_extensions = array( 'json', 'hash' );
foreach ( $required_extensions as $extension ) {
	if ( ! extension_loaded( $extension ) ) {
		add_action(
			'admin_notices',
			function () use ( $extension ) {
				ai_gateway_display_admin_notice(
					sprintf(
						'AI Gateway requires the PHP %s extension, which is not installed on your server. Please contact your hosting provider to enable this extension.',
						esc_html( $extension )
					),
					'error'
				);
			}
		);
		deactivate_plugins( plugin_basename( __FILE__ ) );
		return;
	}
}

/**
 * Display an admin notice message.
 *
 * @param string $message The message to display.
 * @param string $type    The notice type (error, warning, success, info).
 * @return void
 */
function ai_gateway_display_admin_notice( $message, $type = 'error' ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$class = 'notice notice-' . $type . ' is-dismissible';
	printf(
		'<div class="%s"><p><strong>AI Gateway:</strong> %s</p></div>',
		esc_attr( $class ),
		wp_kses_post( $message )
	);
}

// Define plugin constants.
define( 'AI_GATEWAY_PLUGIN_DIR', __DIR__ );
define( 'AI_GATEWAY_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'AI_GATEWAY_VERSION', '0.7.0' );

/**
 * PSR-4-style autoloader for AI_Gateway namespace.
 *
 * Maps AI_Gateway\* classes to includes/ directory:
 * - AI_Gateway\API\PostController       -> includes/api/class-post-controller.php
 * - AI_Gateway\ContentManagement\*      -> includes/content-management/class-*.php
 * - AI_Gateway\Security\*               -> includes/security/class-*.php
 * - AI_Gateway\Observability\*          -> includes/observability/class-*.php
 * - AI_Gateway\FileOperations\*         -> includes/file-operations/class-*.php
 * - AI_Gateway\Exceptions\*             -> includes/exceptions/class-*.php
 * - AI_Gateway\Admin\*                  -> includes/admin/class-*.php
 * - AI_Gateway\PluginLoader             -> includes/class-plugin-loader.php
 * - AI_Gateway\Activation              -> includes/class-activation.php
 * - AI_Gateway\Deactivation            -> includes/class-deactivation.php
 *
 * Handles both CamelCase (PostController -> class-post-controller.php)
 * and underscore names (Admin_Controller -> class-admin-controller.php).
 */
spl_autoload_register(
	function ( $class ) {
		// Only handle AI_Gateway namespace.
		$prefix = 'AI_Gateway\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		// Remove the namespace prefix.
		$relative_class = substr( $class, strlen( $prefix ) );

		// Split into namespace parts and class name.
		$parts      = explode( '\\', $relative_class );
		$class_name = array_pop( $parts );

		// Map namespace segments to directory names.
		$namespace_map = array(
			'API'               => 'api',
			'ContentManagement' => 'content-management',
			'Security'          => 'security',
			'Observability'     => 'observability',
			'FileOperations'    => 'file-operations',
			'Exceptions'        => 'exceptions',
			'Admin'             => 'admin',
		);

		// Build directory path.
		$dir = AI_GATEWAY_PLUGIN_DIR . '/includes';
		foreach ( $parts as $part ) {
			if ( isset( $namespace_map[ $part ] ) ) {
				$dir .= '/' . $namespace_map[ $part ];
			} else {
				// Fallback: convert CamelCase to kebab-case.
				$dir .= '/' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $part ) );
			}
		}

		// Convert class name to file name.
		// Handle underscore-separated names: Admin_Controller -> admin-controller.
		// Handle CamelCase names: PostController -> post-controller.
		// Handle acronyms: ACFManager -> acf-manager, PHPLinter -> php-linter.
		if ( strpos( $class_name, '_' ) !== false ) {
			// Underscore-separated: Admin_Controller -> admin-controller.
			$file_name = strtolower( str_replace( '_', '-', $class_name ) );
		} else {
			// CamelCase: insert hyphens before uppercase letters, handle acronym runs.
			// ACFManager -> ACF-Manager -> acf-manager
			// PostController -> Post-Controller -> post-controller
			// PHPLinter -> PHP-Linter -> php-linter
			// ResponseFormatter -> Response-Formatter -> response-formatter
			$file_name = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $class_name );
			$file_name = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $file_name );
			$file_name = strtolower( $file_name );
		}

		$file = $dir . '/class-' . $file_name . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// Eagerly load files required for activation hooks (fire outside normal lifecycle).
require_once AI_GATEWAY_PLUGIN_DIR . '/includes/class-activation.php';
require_once AI_GATEWAY_PLUGIN_DIR . '/includes/class-deactivation.php';

// Register activation hook (must be before plugins_loaded).
register_activation_hook(
	__FILE__,
	array( 'AI_Gateway\Activation', 'activate' )
);

// Register deactivation hook (must be before plugins_loaded).
register_deactivation_hook(
	__FILE__,
	array( 'AI_Gateway\Deactivation', 'deactivate' )
);

/**
 * Bootstrap plugin after WordPress is fully loaded.
 *
 * Two-tier initialization:
 * 1. EAGER: Admin dashboard hooks (admin_menu, admin_enqueue_scripts)
 * 2. DEFERRED: REST API controllers + managers (constructed only on rest_api_init)
 *
 * Non-API page loads (frontend and admin) never instantiate controllers or managers.
 */
add_action(
	'plugins_loaded',
	function () {
		try {
			// EAGER: Register admin dashboard hooks (needed for admin menu on non-API pages).
			$audit_logger         = new AI_Gateway\ContentManagement\AuditLogger();
			$admin_dashboard      = new AI_Gateway\Admin\Admin_Dashboard( $audit_logger );
			$admin_asset_enqueuer = new AI_Gateway\Admin\Admin_Asset_Enqueuer();

			$admin_dashboard->register_hooks();
			$admin_asset_enqueuer->register_hooks();

			// DEFERRED: Construct PluginLoader and register REST routes only on API requests.
			add_action(
				'rest_api_init',
				function () {
					$loader = new AI_Gateway\PluginLoader();
					$loader->register_rest_routes();
				}
			);
		} catch ( \Throwable $e ) {
			// Log to error log if WP_DEBUG enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'AI Gateway initialization error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}

			// Display admin notice.
			add_action(
				'admin_notices',
				function () use ( $e ) {
					ai_gateway_display_admin_notice(
						sprintf(
							'AI Gateway initialization failed: %s (in %s:%d). Please contact your hosting provider with this error message.',
							esc_html( $e->getMessage() ),
							esc_html( basename( $e->getFile() ) ),
							(int) $e->getLine()
						),
						'error'
					);
				}
			);

			// Deactivate plugin.
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	}
);
