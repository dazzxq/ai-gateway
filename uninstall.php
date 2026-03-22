<?php
/**
 * Uninstall Script for AI Gateway Plugin
 *
 * This file is executed when the plugin is deleted (not just deactivated).
 * DEPLOYMENT-SAFE: Only cleans up ephemeral data.
 * User deploys via Deactivate > Delete > Upload ZIP > Activate,
 * so we must NOT drop tables or delete persistent settings.
 *
 * @package AI_Gateway
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// DO NOT delete:
// - ai_gateway_audit table (persistent audit trail)
// - ai_gateway_activated_at (activation timestamp)
// - ai_gateway_db_version (migration tracking)
// - ai_gateway_admin_setting_* (user preferences)
//
// These are preserved across Delete > Upload > Activate deployments.
// For true uninstall, user should manually drop the table via phpMyAdmin.
//
// Note: ai_gateway_api_key_hash and ai_gateway_api_keys are removed by.
// v3.0 migration (Bearer auth replaced by WP Application Password).
