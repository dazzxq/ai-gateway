<?php
/**
 * Gateway Exception Class
 *
 * Custom exception for all AI Gateway plugin errors.
 *
 * @package AI_Gateway\Exceptions
 */

namespace AI_Gateway\Exceptions;

/**
 * GatewayException
 *
 * Base exception class extending \Exception with error code support.
 * Stores both a human-readable message and a machine-readable error code.
 */
class GatewayException extends \Exception {
	/**
	 * Error code slug (machine-readable).
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * Constructor
	 *
	 * @param string          $message  Human-readable error message.
	 * @param string          $code     Machine-readable error code (default: empty string).
	 * @param \Exception|null $previous Previous exception for chaining.
	 */
	public function __construct( $message = '', $code = '', ?\Exception $previous = null ) {
		$this->error_code = $code;
		parent::__construct( $message, 0, $previous );
	}

	/**
	 * Get the error code slug.
	 *
	 * @return string The machine-readable error code.
	 */
	public function get_code() {
		return $this->error_code;
	}

	/**
	 * Convert exception to array format.
	 *
	 * @return array Array with 'code' and 'message' keys.
	 */
	public function to_array() {
		return array(
			'code'    => $this->error_code,
			'message' => $this->getMessage(),
		);
	}
}
