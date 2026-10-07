<?php
/**
 * Audit log, problem list (options) and a protected error log FILE.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logging.
 */
class MSST_Logger {

	const AUDIT_MAX = 200;
	const ERROR_MAX = 100;
	const FILE_MAX  = 1048576; // 1 MB, then rotated.

	/**
	 * Record an administrative action.
	 *
	 * @param string $message Human readable message (plain text).
	 */
	public static function audit( $message ) {
		$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
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
	 * Record a snippet problem (list + log file).
	 *
	 * @param int    $id      Snippet ID.
	 * @param string $title   Snippet title.
	 * @param string $message Error message.
	 * @param string $level   error|warning.
	 */
	public static function error( $id, $title, $message, $level = 'error' ) {
		$level = 'warning' === $level ? 'warning' : 'error';
		self::push(
			'msst_errors',
			array(
				'time'  => time(),
				'id'    => (int) $id,
				'title' => sanitize_text_field( $title ),
				'msg'   => sanitize_text_field( mb_substr( $message, 0, 500 ) ),
				'level' => $level,
			),
			self::ERROR_MAX
		);
		self::write_file( self::format_line( time(), $level, (int) $id, $title, $message, self::request_context() ) );
	}

	/**
	 * Read a list log.
	 *
	 * @param string $name audit|errors.
	 * @return array
	 */
	public static function get( $name ) {
		$rows = get_option( 'audit' === $name ? 'msst_audit' : 'msst_errors', array() );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Clear a list log.
	 *
	 * @param string $name audit|errors.
	 */
	public static function clear( $name ) {
		update_option( 'audit' === $name ? 'msst_audit' : 'msst_errors', array(), false );
	}

	/**
	 * One safe log line. Newlines and control characters are removed so nobody can forge entries.
	 *
	 * @param int    $time    Unix time.
	 * @param string $level   error|warning.
	 * @param int    $id      Snippet ID.
	 * @param string $title   Snippet title.
	 * @param string $message Message.
	 * @param string $context Extra context (path, user).
	 * @return string Line ending in a newline.
	 */
	public static function format_line( $time, $level, $id, $title, $message, $context = '' ) {
		$clean = static function ( $text, $max ) {
			$text = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', (string) $text );
			$text = is_string( $text ) ? trim( $text ) : '';
			return mb_substr( $text, 0, $max );
		};
		return sprintf(
			"[%s UTC] %s snippet#%d \"%s\": %s%s\n",
			gmdate( 'Y-m-d H:i:s', (int) $time ),
			strtoupper( 'warning' === $level ? 'warning' : 'error' ),
			(int) $id,
			$clean( $title, 120 ),
			$clean( $message, 600 ),
			'' !== $context ? ' | ' . $clean( $context, 200 ) : ''
		);
	}

	/**
	 * Where the request happened (path only, no query string) and who.
	 *
	 * @return string
	 */
	private static function request_context() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : 'cli';
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return 'path=' . $path . ' user=' . $user;
	}

	/**
	 * Directory that holds the log file.
	 *
	 * @return string
	 */
	public static function log_dir() {
		$upload = wp_upload_dir( null, false );
		return trailingslashit( $upload['basedir'] ) . 'scripts-manager-logs';
	}

	/**
	 * Full path of the (randomly named) log file.
	 *
	 * @return string
	 */
	public static function log_path() {
		$name = get_option( 'msst_log_name', '' );
		if ( ! is_string( $name ) || ! preg_match( '/^errors-[a-f0-9]{16}\.log$/', $name ) ) {
			$name = 'errors-' . bin2hex( random_bytes( 8 ) ) . '.log';
			update_option( 'msst_log_name', $name, false );
		}
		return self::log_dir() . '/' . $name;
	}

	/**
	 * Create the log directory with deny-all rules.
	 *
	 * @return bool
	 */
	private static function ensure_dir() {
		$dir = self::log_dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- Tiny protected log folder.
				file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return true;
	}

	/**
	 * Append a line to the log file (rotates at 1 MB).
	 *
	 * @param string $line Prepared line.
	 * @return bool
	 */
	private static function write_file( $line ) {
		if ( ! self::ensure_dir() ) {
			return false;
		}
		$path = self::log_path();
		if ( file_exists( $path ) && filesize( $path ) > self::FILE_MAX ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			rename( $path, $path . '.1' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		}
		return false !== file_put_contents( $path, $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Information about the log file for the admin screen.
	 *
	 * @return array { exists: bool, size: int, writable: bool, name: string }
	 */
	public static function file_info() {
		$path = self::log_path();
		$dir  = self::log_dir();
		return array(
			'exists'   => file_exists( $path ), // phpcs:ignore WordPress.WP.AlternativeFunctions
			'size'     => file_exists( $path ) ? (int) filesize( $path ) : 0, // phpcs:ignore WordPress.WP.AlternativeFunctions
			'writable' => is_dir( $dir ) ? is_writable( $dir ) : is_writable( dirname( $dir ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			'name'     => basename( $path ),
		);
	}

	/**
	 * Last lines of the log file, newest last.
	 *
	 * @param int $lines Number of lines.
	 * @return string[]
	 */
	public static function tail( $lines = 50 ) {
		$path = self::log_path();
		if ( ! file_exists( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return array();
		}
		$size   = (int) filesize( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array();
		}
		if ( $size < 1 ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array();
		}
		$chunk = min( $size, 65536 );
		fseek( $handle, -$chunk, SEEK_END );
		$data = (string) fread( $handle, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$rows = array_values( array_filter( explode( "\n", $data ), 'strlen' ) );
		return array_slice( $rows, -max( 1, (int) $lines ) );
	}

	/**
	 * Empty the log file.
	 */
	public static function clear_file() {
		$path = self::log_path();
		if ( file_exists( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $path, '', LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Delete the whole log folder (used by uninstall).
	 */
	public static function delete_files() {
		$dir = self::log_dir();
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) glob( $dir . '/{,.}*', GLOB_BRACE ) as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
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
