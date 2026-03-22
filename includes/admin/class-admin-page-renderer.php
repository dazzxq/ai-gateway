<?php
/**
 * Admin Page Renderer Class
 *
 * Handles rendering of dashboard templates and tab content.
 *
 * @package AI_Gateway\Admin
 */

namespace AI_Gateway\Admin;

/**
 * AdminPageRenderer class
 *
 * Renders dashboard templates and individual tab content.
 */
class Admin_Page_Renderer {

	/**
	 * Render the main dashboard page
	 *
	 * @param array $data Data to pass to template.
	 * @return string HTML content.
	 */
	public function render( $data = array() ) {
		ob_start();
		$this->include_template( 'dashboard', $data );
		return ob_get_clean();
	}

	/**
	 * Get tab content for a specific tab
	 *
	 * @param string $tab_name Tab name to render.
	 * @param array  $data Data to pass to template.
	 * @return string HTML content.
	 */
	public function get_tab_content( $tab_name, $data = array() ) {
		$allowed_tabs = array( 'overview', 'audit-log', 'health', 'endpoint-tester', 'settings' );

		if ( ! in_array( $tab_name, $allowed_tabs, true ) ) {
			return '<p>' . esc_html__( 'Invalid tab.', 'ai-gateway' ) . '</p>';
		}

		ob_start();
		$this->include_template( "tab-$tab_name", $data );
		return ob_get_clean();
	}

	/**
	 * Include a template file
	 *
	 * @param string $template_name Template name (without extension).
	 * @param array  $data Data to pass to template.
	 * @return void
	 */
	private function include_template( $template_name, $data = array() ) {
		// Extract variables to local scope.
		extract( $data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		$template_path = dirname( __DIR__, 2 ) . "/templates/admin/$template_name.php";

		if ( ! file_exists( $template_path ) ) {
			echo '<p>' . esc_html__( 'Template not found.', 'ai-gateway' ) . '</p>';
			return;
		}

		include $template_path;
	}
}
