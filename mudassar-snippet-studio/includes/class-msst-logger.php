<?php
/**
 * Audit and error logs (capped, stored in options).
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logging.
 */
class MSST_Logger {

	const AUDIT_MAX = 200;
	const ERROR_MAX = 100;

	/**
	 * Record an administrative action.
	 *
	 * @param string $message Human readable message (plain text).
	 */
	public static function audit( $message ) {
		$user = wp_get_current_user();
		self::push(
			'msst_audit',
			array(
				'time' => time(),
				'user' => $user && $user->exists() ? $user->user_login : 'system',
				'msg'  => sanitize_text_field( $message ),
			),
			self::AUDIT_MAX
		);
	}

	/**
	 * Record a snippet error.
	 *
	 * @param int    $id      Snippet ID.
	 * @param string $title   Snippet title.
	 * @param string $message Error message.
	 * @param string $level   error|warning.
	 */
	public static function error( $id, $title, $message, $level = 'error' ) {
		self::push(
			'msst_errors',
			array(
				'time'  => time(),
				'id'    => (int) $id,
				'title' => sanitize_text_field( $title ),
				'msg'   => sanitize_text_field( mb_substr( $message, 0, 500 ) ),
				'level' => 'warning' === $level ? 'warning' : 'error',
			),
			self::ERROR_MAX
		);
	}

	/**
	 * Read a log.
	 *
	 * @param string $name audit|errors.
	 * @return array
	 */
	public static function get( $name ) {
		$rows = get_option( 'audit' === $name ? 'msst_audit' : 'msst_errors', array() );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Clear a log.
	 *
	 * @param string $name audit|errors.
	 */
	public static function clear( $name ) {
		update_option( 'audit' === $name ? 'msst_audit' : 'msst_errors', array(), false );
	}

	/**
	 * Prepend a row and cap the list.
	 *
	 * @param string $option Option name.
	 * @param array  $row    Row.
	 * @param int    $max    Max rows.
	 */
	private static function push( $option, array $row, $max ) {
		$rows = get_option( $option, array() );
		$rows = is_array( $rows ) ? $rows : array();
		array_unshift( $rows, $row );
		update_option( $option, array_slice( $rows, 0, $max ), false );
	}
}
