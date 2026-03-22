<?php
/**
 * Log Reader Class
 *
 * Reads and parses WordPress debug log files with filtering capabilities.
 *
 * @package AI_Gateway\Observability
 */

namespace AI_Gateway\Observability;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * LogReader
 *
 * Provides methods to read debug.log with line count limiting and content filtering.
 */
class LogReader {
	/**
	 * Path to the debug log file.
	 *
	 * @var string
	 */
	private $log_file_path;

	/**
	 * Constructor
	 *
	 * Initializes the log file path.
	 */
	public function __construct() {
		$this->log_file_path = WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Check if debug logging is available.
	 *
	 * Returns true only if WP_DEBUG is enabled and the log file exists.
	 *
	 * @return bool True if debug logging is available.
	 */
	public function is_available() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $this->log_file_path );
	}

	/**
	 * Read logs with optional filtering.
	 *
	 * @param int    $line_count Maximum lines to read (capped at 500).
	 * @param string $filter     Optional filter string to match in log lines.
	 *
	 * @return array Array with entries, total_count, matched_count, filter_used.
	 */
	public function read_logs( $line_count = 50, $filter = '' ) {
		// Cap line count to prevent abuse.
		$line_count = min( (int) $line_count, 500 );

		if ( ! $this->is_available() ) {
			return array(
				'entries'       => array(),
				'total_count'   => 0,
				'matched_count' => 0,
				'filter_used'   => $filter,
			);
		}

		$file_size = filesize( $this->log_file_path );
		$lines     = array();

		// Use tail for large files (>5MB) to avoid memory spikes.
		if ( $file_size > 5 * 1024 * 1024 ) {
			$cmd    = 'tail -n ' . (int) $line_count . ' ' . escapeshellarg( $this->log_file_path );
			$output = shell_exec( $cmd ); // phpcs:ignore WordPress.PHP.DiscouragedFunctions.system_calls_shell_exec
			$lines  = $output ? explode( "\n", trim( $output ) ) : array();
		} else {
			$lines = file( $this->log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file
			if ( $lines && count( $lines ) > $line_count ) {
				$lines = array_slice( $lines, -$line_count );
			}
		}

		$parsed_entries = array();

		foreach ( $lines as $line ) {
			if ( empty( $line ) ) {
				continue;
			}

			// Apply filter if provided.
			if ( ! empty( $filter ) && stripos( $line, $filter ) === false ) {
				continue;
			}

			$parsed_entries[] = $this->parse_log_entry( $line );
		}

		return array(
			'entries'       => $parsed_entries,
			'total_count'   => count( $lines ),
			'matched_count' => count( $parsed_entries ),
			'filter_used'   => $filter,
		);
	}

	/**
	 * Parse a single log entry into structured data.
	 *
	 * Expects format: [10-Mar-2026 12:34:56 UTC] Level: Message
	 *
	 * @param string $line The raw log line.
	 *
	 * @return array Parsed entry with timestamp, level, message.
	 */
	private function parse_log_entry( $line ) {
		$pattern = '/\[(.*?)\]\s+(.+?):\s*(.*)/';
		$matches = array();

		if ( preg_match( $pattern, $line, $matches ) ) {
			$timestamp_str = $matches[1];
			$level         = strtolower( trim( $matches[2] ) );
			$message       = $matches[3];

			$iso_timestamp = $this->parse_wp_timestamp( $timestamp_str );

			return array(
				'timestamp' => $iso_timestamp,
				'level'     => $level,
				'message'   => $message,
			);
		}

		// Fallback for unparseable lines.
		return array(
			'timestamp' => gmdate( 'c' ),
			'level'     => 'unknown',
			'message'   => $line,
		);
	}

	/**
	 * Parse WordPress timestamp format to ISO-8601.
	 *
	 * WordPress format: "10-Mar-2026 12:34:56 UTC"
	 *
	 * @param string $wp_timestamp The WordPress timestamp string.
	 *
	 * @return string ISO-8601 formatted timestamp or current UTC time on failure.
	 */
	private function parse_wp_timestamp( $wp_timestamp ) {
		try {
			$dt = DateTime::createFromFormat(
				'd-M-Y H:i:s e',
				$wp_timestamp,
				new DateTimeZone( 'UTC' )
			);

			if ( false !== $dt ) {
				return $dt->format( 'c' );
			}
		} catch ( Exception $e ) {
			// Date parse failed -- fallback to current time below.
			unset( $e );
		}

		// Fallback to current UTC time if parse fails.
		return gmdate( 'c' );
	}
}
