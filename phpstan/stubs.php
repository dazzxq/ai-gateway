<?php
/**
 * PHPStan stubs for third-party plugin classes and functions.
 *
 * These stubs provide type information for classes/functions from plugins
 * that are used inside class_exists() / function_exists() guards.
 *
 * @package AI_Gateway
 */

namespace SpeedyCache {
	/**
	 * SpeedyCache cache deletion handler.
	 */
	class Delete {
		/**
		 * Run cache deletion.
		 *
		 * @param array<string, mixed> $args Deletion arguments.
		 * @return void
		 */
		public static function run( array $args = array() ) {}
	}
}

namespace Code_Snippets {
	/**
	 * Deactivate a code snippet.
	 *
	 * @param int  $id      Snippet ID.
	 * @param bool $network Whether this is a network snippet.
	 * @return bool|string True on success, error message string on failure.
	 */
	function deactivate_snippet( int $id, bool $network = false ) {
		return true;
	}

	/**
	 * Activate a code snippet.
	 *
	 * @param int  $id      Snippet ID.
	 * @param bool $network Whether this is a network snippet.
	 * @return bool|string True on success, error message string on failure.
	 */
	function activate_snippet( int $id, bool $network = false ) {
		return true;
	}

	/**
	 * Clean the snippets cache.
	 *
	 * @param string $table Table name.
	 * @return void
	 */
	function clean_snippets_cache( string $table ) {}
}
