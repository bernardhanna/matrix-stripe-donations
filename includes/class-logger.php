<?php
/**
 * Lightweight plugin logger.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Logger {

	const OPTION_LOGS = 'matrix_donations_logs';
	const MAX_LOGS    = 500;

	/**
	 * Write a log entry when debug mode is enabled.
	 *
	 * @param string $level   Log level.
	 * @param string $message Human-readable message.
	 * @param array  $context Optional context payload.
	 * @return void
	 */
	public static function log( $level, $message, $context = array() ) {
		if ( ! Matrix_Donations_Settings::is_debug_enabled() ) {
			return;
		}

		$logs = self::get_logs( self::MAX_LOGS );
		array_unshift(
			$logs,
			array(
				'timestamp' => gmdate( 'c' ),
				'level'     => sanitize_text_field( $level ),
				'message'   => sanitize_text_field( $message ),
				'context'   => self::sanitize_context( $context ),
			)
		);

		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, 0, self::MAX_LOGS );
		}

		update_option( self::OPTION_LOGS, $logs, false );
	}

	/**
	 * Read logs.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public static function get_logs( $limit = 200 ) {
		$logs = get_option( self::OPTION_LOGS, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		$limit = max( 1, (int) $limit );
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear all logs.
	 *
	 * @return void
	 */
	public static function clear_logs() {
		delete_option( self::OPTION_LOGS );
	}

	/**
	 * Remove sensitive values from context payload.
	 *
	 * @param mixed $context Context payload.
	 * @return mixed
	 */
	private static function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return array();
		}
		$clean = array();
		foreach ( $context as $key => $value ) {
			$key_string = strtolower( (string) $key );
			if ( false !== strpos( $key_string, 'secret' ) || false !== strpos( $key_string, 'token' ) || false !== strpos( $key_string, 'password' ) ) {
				$clean[ $key ] = '[redacted]';
				continue;
			}
			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			} else {
				$clean[ $key ] = '[complex]';
			}
		}
		return $clean;
	}
}
