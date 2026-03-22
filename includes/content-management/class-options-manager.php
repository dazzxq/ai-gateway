<?php
/**
 * Options Manager Class
 *
 * Handles safe WordPress settings reading.
 *
 * @package AI_Gateway\ContentManagement
 */

namespace AI_Gateway\ContentManagement;

/**
 * OptionsManager
 *
 * Provides access to safe WordPress options via a whitelist.
 */
class OptionsManager {
	/**
	 * Whitelist of safe option keys.
	 *
	 * @var array
	 */
	private $safe_keys = array(
		'siteurl',
		'home',
		'blogname',
		'blogdescription',
		'timezone_string',
		'permalink_structure',
		'date_format',
		'time_format',
	);

	/**
	 * Get all safe WordPress options.
	 *
	 * @return array Key-value pairs of safe options
	 */
	public function get_safe_options() {
		$options = array();

		foreach ( $this->safe_keys as $key ) {
			$value = get_option( $key );
			if ( false !== $value ) {
				$options[ $key ] = $value;
			}
		}

		return $options;
	}
}
