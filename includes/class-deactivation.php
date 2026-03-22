<?php
/**
 * Deactivation Hook Class
 *
 * Handles plugin deactivation cleanup.
 *
 * @package AI_Gateway
 */

namespace AI_Gateway;

/**
 * Deactivation
 *
 * Executes on plugin deactivation to clean up temporary data.
 *
 * Note: Deactivation preserves user data (API key, settings, audit logs).
 * Deletion of all data happens only in uninstall.php when plugin is fully deleted.
 * This allows users to reactivate the plugin without losing configuration.
 */
class Deactivation {
	/**
	 * Deactivate the plugin.
	 *
	 * Cleans up temporary data (transients, crons) but preserves API key and settings.
	 * Data deletion happens in uninstall.php when plugin is fully removed.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Check if preservation is explicitly requested.
		if ( defined( 'AI_GATEWAY_PRESERVE_OPTIONS' ) && AI_GATEWAY_PRESERVE_OPTIONS ) {
			return;
		}

		// Deactivation preserves user data.
		// The API key hash and activation timestamp are intentionally NOT deleted here.
		// They will be deleted in uninstall.php if the plugin is fully removed.

		// Clean up temporary data if needed (transients, scheduled crons, etc.)
		// Example: delete_transient( 'ai_gateway_temp_data' );
		// Currently no temporary data to clean up.
	}
}
