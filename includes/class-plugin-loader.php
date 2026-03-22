<?php
/**
 * Plugin Loader Class
 *
 * Orchestrates component initialization and REST route registration.
 * Constructed lazily during rest_api_init to avoid loading controllers
 * and managers on non-API requests.
 *
 * @package AI_Gateway
 */

namespace AI_Gateway;

use AI_Gateway\Security\PermissionChecker;
use AI_Gateway\API\PostController;
use AI_Gateway\API\ACFController;
use AI_Gateway\API\SettingsController;
use AI_Gateway\API\AuditController;
use AI_Gateway\API\SystemInfoController;
use AI_Gateway\API\LogReaderController;
use AI_Gateway\API\HealthCheckController;
use AI_Gateway\FileOperations\PHPLinter;
use AI_Gateway\ContentManagement\PostManager;
use AI_Gateway\ContentManagement\ACFManager;
use AI_Gateway\ContentManagement\OptionsManager;
use AI_Gateway\ContentManagement\AuditLogger;
use AI_Gateway\Observability\SystemInfoGetter;
use AI_Gateway\Observability\LogReader;
use AI_Gateway\Observability\HealthChecker;
use AI_Gateway\API\Admin_Controller;
use AI_Gateway\API\CodeSnippetsController;
use AI_Gateway\API\GlobalStylesController;
use AI_Gateway\API\SystemController;
use AI_Gateway\ContentManagement\CodeSnippetsManager;
use AI_Gateway\ContentManagement\GlobalStylesManager;

/**
 * PluginLoader
 *
 * Main loader that initializes components and registers REST routes.
 * Constructed only when a REST API request is made (via rest_api_init hook).
 */
class PluginLoader {
	/**
	 * Permission Checker instance.
	 *
	 * @var PermissionChecker
	 */
	private $permission_checker;

	/**
	 * PHP Linter instance.
	 *
	 * @var PHPLinter
	 */
	private $linter;

	/**
	 * Post Manager instance.
	 *
	 * @var PostManager
	 */
	private $post_manager;

	/**
	 * ACF Manager instance.
	 *
	 * @var ACFManager
	 */
	private $acf_manager;

	/**
	 * Options Manager instance.
	 *
	 * @var OptionsManager
	 */
	private $options_manager;

	/**
	 * Audit Logger instance.
	 *
	 * @var AuditLogger
	 */
	private $audit_logger;

	/**
	 * Post Controller instance.
	 *
	 * @var PostController
	 */
	private $post_controller;

	/**
	 * ACF Controller instance.
	 *
	 * @var ACFController
	 */
	private $acf_controller;

	/**
	 * Settings Controller instance.
	 *
	 * @var SettingsController
	 */
	private $settings_controller;

	/**
	 * Audit Controller instance.
	 *
	 * @var AuditController
	 */
	private $audit_controller;

	/**
	 * System Info Getter instance.
	 *
	 * @var SystemInfoGetter
	 */
	private $system_info_getter;

	/**
	 * Log Reader instance.
	 *
	 * @var LogReader
	 */
	private $log_reader;

	/**
	 * Health Checker instance.
	 *
	 * @var HealthChecker
	 */
	private $health_checker;

	/**
	 * System Info Controller instance.
	 *
	 * @var SystemInfoController
	 */
	private $system_info_controller;

	/**
	 * Log Reader Controller instance.
	 *
	 * @var LogReaderController
	 */
	private $log_reader_controller;

	/**
	 * Health Check Controller instance.
	 *
	 * @var HealthCheckController
	 */
	private $health_check_controller;

	/**
	 * Admin Controller instance.
	 *
	 * @var Admin_Controller
	 */
	private $admin_controller;

	/**
	 * Code Snippets Manager instance.
	 *
	 * @var CodeSnippetsManager
	 */
	private $code_snippets_manager;

	/**
	 * Code Snippets Controller instance.
	 *
	 * @var CodeSnippetsController
	 */
	private $code_snippets_controller;

	/**
	 * Global Styles Manager instance.
	 *
	 * @var GlobalStylesManager
	 */
	private $global_styles_manager;

	/**
	 * Global Styles Controller instance.
	 *
	 * @var GlobalStylesController
	 */
	private $global_styles_controller;

	/**
	 * System Controller instance.
	 *
	 * @var SystemController
	 */
	private $system_controller;

	/**
	 * Constructor
	 *
	 * Instantiates all API components (managers and controllers).
	 * Admin hooks are registered separately in ai-gateway-plugin.php.
	 */
	public function __construct() {
		$this->permission_checker = new PermissionChecker();
		$this->linter             = new PHPLinter();

		// Initialize content managers.
		$this->acf_manager     = new ACFManager();
		$this->post_manager    = new PostManager( $this->acf_manager );
		$this->options_manager = new OptionsManager();
		$this->audit_logger    = new AuditLogger();

		// Initialize controllers (Pattern A: Manager + PermissionChecker + AuditLogger).
		$this->post_controller = new PostController(
			$this->post_manager,
			$this->permission_checker,
			$this->audit_logger
		);
		$this->acf_controller = new ACFController(
			$this->acf_manager,
			$this->permission_checker,
			$this->audit_logger
		);
		$this->settings_controller = new SettingsController(
			$this->options_manager,
			$this->permission_checker,
			$this->audit_logger
		);
		$this->audit_controller = new AuditController(
			$this->audit_logger,
			$this->permission_checker
		);

		// Initialize observability components.
		$this->system_info_getter = new SystemInfoGetter();
		$this->log_reader         = new LogReader();
		$this->health_checker     = new HealthChecker();

		// Initialize observability controllers.
		$this->system_info_controller = new SystemInfoController(
			$this->system_info_getter,
			$this->permission_checker,
			$this->audit_logger
		);
		$this->log_reader_controller = new LogReaderController(
			$this->log_reader,
			$this->permission_checker,
			$this->audit_logger
		);
		$this->health_check_controller = new HealthCheckController(
			$this->health_checker
		);

		// Initialize admin API controller.
		$this->admin_controller = new Admin_Controller(
			$this->permission_checker,
			$this->audit_logger
		);

		// Initialize Code Snippets components.
		$this->code_snippets_manager    = new CodeSnippetsManager( $this->linter );
		$this->code_snippets_controller = new CodeSnippetsController(
			$this->code_snippets_manager,
			$this->permission_checker,
			$this->audit_logger
		);

		// Initialize Global Styles components.
		$this->global_styles_manager    = new GlobalStylesManager();
		$this->global_styles_controller = new GlobalStylesController(
			$this->global_styles_manager,
			$this->permission_checker,
			$this->audit_logger
		);

		// Initialize System Controller.
		$this->system_controller = new SystemController(
			$this->permission_checker,
			$this->audit_logger
		);
	}

	/**
	 * Register REST API routes.
	 *
	 * All controllers self-register their routes via register_routes().
	 * Called directly from rest_api_init hook in ai-gateway-plugin.php.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Post search routes.
		$this->post_controller->register_routes();

		// ACF routes.
		$this->acf_controller->register_routes();

		// Settings routes.
		$this->settings_controller->register_routes();

		// Audit routes.
		$this->audit_controller->register_routes();

		// System info routes.
		$this->system_info_controller->register_routes();

		// Log reader routes.
		$this->log_reader_controller->register_routes();

		// Health check routes.
		$this->health_check_controller->register_routes();

		// Admin dashboard routes.
		$this->admin_controller->register_routes();

		// Code Snippets routes.
		$this->code_snippets_controller->register_routes();

		// Global Styles routes.
		$this->global_styles_controller->register_routes();

		// System routes.
		$this->system_controller->register_routes();
	}
}
