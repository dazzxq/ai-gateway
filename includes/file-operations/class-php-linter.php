<?php
/**
 * PHP Linter Class
 *
 * @package AI_Gateway
 * @subpackage FileOperations
 */

namespace AI_Gateway\FileOperations;

use AI_Gateway\Exceptions\GatewayException;

/**
 * Validates PHP files using php -l command with output parsing.
 *
 * @since 0.1.0
 */
class PHPLinter {

	/**
	 * Lint PHP content.
	 *
	 * @since 0.1.0
	 * @param string $php_content PHP code to lint.
	 * @return array|\WP_Error Result array with 'valid' and 'errors' keys, or WP_Error on syntax failure.
	 * @throws GatewayException If linting infrastructure fails.
	 */
	public function lint_content( $php_content ) {
		// Create temp file for linting.
		$temp_file = tempnam( sys_get_temp_dir(), 'php-lint-' );

		if ( ! $temp_file ) {
			throw new GatewayException(
				'Failed to create temporary file for linting',
				'lint_error'
			);
		}

		try {
			// Write content to temp file.
			$bytes_written = @file_put_contents( $temp_file, $php_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			if ( false === $bytes_written ) {
				unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				throw new GatewayException(
					'Failed to write PHP content to temporary file',
					'lint_error'
				);
			}

			// Execute php -l.
			$escaped_file = escapeshellarg( $temp_file );
			$output       = shell_exec( "php -l {$escaped_file} 2>&1" ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec

			// Parse output.
			$result = $this->parse_lint_output( $output );

			// Return WP_Error for syntax failures so callers can use is_wp_error().
			if ( ! $result['valid'] ) {
				return new \WP_Error(
					'php_syntax_error',
					'PHP syntax validation failed',
					$result['errors']
				);
			}

			return $result;
		} finally {
			// Clean up temp file.
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
	}

	/**
	 * Parse php -l output
	 *
	 * @since 0.1.0
	 * @param string $output Output from php -l command.
	 * @return array Result array with 'valid' and 'errors' keys.
	 */
	private function parse_lint_output( $output ) {
		$output = trim( $output );

		// Check for syntax OK.
		if ( strpos( $output, 'No syntax errors detected' ) !== false ) {
			return array(
				'valid'  => true,
				'errors' => array(),
			);
		}

		// Parse errors.
		$errors = array();

		// Match Parse error: ... on line X.
		if ( preg_match_all( '/(?:Parse error|Fatal error|Error):\s*(.+?)\s+on line\s+(\d+)/i', $output, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$errors[] = array(
					'line'    => (int) $match[2],
					'message' => $match[1],
					'file'    => 'temp.php',
				);
			}
		}

		// If no errors parsed but output contains error indicators, generic error.
		if ( empty( $errors ) && ( strpos( $output, 'error' ) !== false || strpos( $output, 'Error' ) !== false ) ) {
			$errors[] = array(
				'line'    => 0,
				'message' => $output,
				'file'    => 'temp.php',
			);
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Quick boolean check if PHP is valid
	 *
	 * @since 0.1.0
	 * @param string $php_content PHP code to check.
	 * @return bool True if valid PHP.
	 */
	public function is_valid_php( $php_content ) {
		$result = $this->lint_content( $php_content );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return $result['valid'];
	}

	/**
	 * Get lint errors
	 *
	 * @since 0.1.0
	 * @param string $php_content PHP code to check.
	 * @return array Array of error arrays with line and message.
	 */
	public function get_lint_errors( $php_content ) {
		$result = $this->lint_content( $php_content );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_data( $result->get_error_code() );
		}
		return $result['errors'];
	}
}
