<?php
/**
 * Admin Dashboard Template
 *
 * Main dashboard page with tab navigation and content containers.
 *
 * @package AI_Gateway\Admin
 */

// Verify user can access.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-gateway' ) );
}
?>

<!-- Notice containers (inside wrap for WordPress styling) -->
<div id="ai-gateway-notice-container"></div>

<div class="wrap ai-gateway-dashboard-wrap">
	<h1 class="ai-gateway-page-title">
		<span class="dashicons dashicons-rest-api" style="margin-right: 8px; font-size: 28px; vertical-align: middle;"></span>
		<?php esc_html_e( 'AI Gateway', 'ai-gateway' ); ?>
		<span class="ai-gateway-version-tag">v<?php echo esc_html( $plugin_version ); ?></span>
	</h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper ai-gateway-nav" role="tablist" aria-label="<?php esc_attr_e( 'Dashboard tabs', 'ai-gateway' ); ?>">
		<button class="nav-tab nav-tab-active ai-gateway-tab-btn" data-tab="overview" role="tab" aria-selected="true" aria-controls="tab-overview">
			<span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Overview', 'ai-gateway' ); ?>
		</button>
		<button class="nav-tab ai-gateway-tab-btn" data-tab="api-reference" role="tab" aria-selected="false" aria-controls="tab-api-reference">
			<span class="dashicons dashicons-book-alt"></span> <?php esc_html_e( 'API Reference', 'ai-gateway' ); ?>
		</button>
		<button class="nav-tab ai-gateway-tab-btn" data-tab="audit-log" role="tab" aria-selected="false" aria-controls="tab-audit-log">
			<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Audit Log', 'ai-gateway' ); ?>
		</button>
		<button class="nav-tab ai-gateway-tab-btn" data-tab="health" role="tab" aria-selected="false" aria-controls="tab-health">
			<span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Health', 'ai-gateway' ); ?>
		</button>
		<button class="nav-tab ai-gateway-tab-btn" data-tab="endpoint-tester" role="tab" aria-selected="false" aria-controls="tab-endpoint-tester">
			<span class="dashicons dashicons-code-standards"></span> <?php esc_html_e( 'Endpoint Tester', 'ai-gateway' ); ?>
		</button>
		<button class="nav-tab ai-gateway-tab-btn" data-tab="settings" role="tab" aria-selected="false" aria-controls="tab-settings">
			<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'ai-gateway' ); ?>
		</button>
	</nav>

	<!-- Nonce field for AJAX -->
	<?php wp_nonce_field( 'wp_rest', 'ai_gateway_nonce' ); ?>

	<!-- Tab Content Containers -->
	<div id="tab-overview" class="ai-gateway-tab-content ai-gateway-tab-content-active" role="tabpanel">
		<div class="ai-gateway-loading"><span class="spinner is-active"></span></div>
	</div>
	<div id="tab-api-reference" class="ai-gateway-tab-content" role="tabpanel">
		<div class="ai-gateway-loading"><span class="spinner is-active"></span></div>
	</div>
	<div id="tab-audit-log" class="ai-gateway-tab-content" role="tabpanel">
		<div class="ai-gateway-loading"><span class="spinner is-active"></span></div>
	</div>
	<div id="tab-health" class="ai-gateway-tab-content" role="tabpanel">
		<div class="ai-gateway-loading"><span class="spinner is-active"></span></div>
	</div>
	<div id="tab-endpoint-tester" class="ai-gateway-tab-content" role="tabpanel">
		<div class="ai-gateway-loading"><span class="spinner is-active"></span></div>
	</div>
	<div id="tab-settings" class="ai-gateway-tab-content" role="tabpanel">
		<div class="ai-gateway-loading"><span class="spinner is-active"></span></div>
	</div>
</div>
