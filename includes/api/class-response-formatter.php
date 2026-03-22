<?php
/**
 * Response Formatter Class
 *
 * Static helper class to format consistent success/error API responses.
 *
 * @package AI_Gateway\API
 */

namespace AI_Gateway\API;

use WP_REST_Response;

/**
 * ResponseFormatter
 *
 * Provides static methods to format consistent REST API responses
 * with success/error envelopes and ISO-8601 timestamps.
 */
class ResponseFormatter {
	/**
	 * Format a success response.
	 *
	 * @param array|object $data       The response data.
	 * @param int          $http_code  HTTP status code (default: 200).
	 *
	 * @return WP_REST_Response REST response object with success structure.
	 */
	public static function success( $data = array(), $http_code = 200 ) {
		$body = array(
			'success'   => true,
			'data'      => $data,
			'timestamp' => self::get_iso_timestamp(),
		);

		return new WP_REST_Response( $body, $http_code );
	}

	/**
	 * Format an error response.
	 *
	 * @param string $code       Error code (machine-readable).
	 * @param string $message    Error message (human-readable).
	 * @param array  $data       Additional error data (default: empty array).
	 * @param int    $http_code  HTTP status code (default: 400).
	 *
	 * @return WP_REST_Response REST response object with error structure.
	 */
	public static function error( $code = '', $message = '', $data = array(), $http_code = 400 ) {
		$body = array(
			'success'   => false,
			'code'      => $code,
			'message'   => $message,
			'data'      => $data,
			'timestamp' => self::get_iso_timestamp(),
		);

		return new WP_REST_Response( $body, $http_code );
	}

	/**
	 * Format a production-safe error response from an exception.
	 *
	 * In WP_DEBUG mode, includes file/line info for developer debugging.
	 * In production mode, strips file paths, line numbers, and class prefixes
	 * to prevent information disclosure.
	 *
	 * @param string     $code      Error code (machine-readable).
	 * @param \Throwable $e         The exception or error.
	 * @param int        $http_code HTTP status code (default: 500).
	 *
	 * @return WP_REST_Response REST response with error structure.
	 */
	public static function from_exception( $code, \Throwable $e, $http_code = 500 ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$message = $e->getMessage() . ' in ' . basename( $e->getFile() ) . ':' . $e->getLine();
		} else {
			$message = $e->getMessage();
			$message = preg_replace( '/\s+in\s+\S+\.php(:\d+)?/', '', $message );
			$message = preg_replace( '/^[A-Z][a-zA-Z\\\\]+::\w+\(\):\s*/', '', $message );
		}

		return self::error( $code, $message, array(), $http_code );
	}

	/**
	 * Passthrough sanitizer for code/CSS content.
	 *
	 * Validates the value is a string (preserves content unchanged).
	 * Used as sanitize_callback for REST API route args that accept code or CSS.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The original string value, or empty string if not a string.
	 */
	public static function sanitize_passthrough( $value ) {
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Extract HTTP status code from WP_Error data.
	 *
	 * WP_Error stores data as array('status' => 4xx). This helper extracts
	 * the status code or returns the provided default.
	 *
	 * @param \WP_Error $error   The WP_Error object.
	 * @param int       $default Default status code if not found.
	 *
	 * @return int HTTP status code.
	 */
	public static function get_wp_error_status( \WP_Error $error, $default = 500 ) {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			return (int) $data['status'];
		}
		return $default;
	}

	/**
	 * Get ISO-8601 formatted UTC timestamp.
	 *
	 * @return string ISO-8601 formatted timestamp (e.g., 2026-03-10T12:34:56Z).
	 */
	private static function get_iso_timestamp() {
		return gmdate( 'c' );
	}
}
