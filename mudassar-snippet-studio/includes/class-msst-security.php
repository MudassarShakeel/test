<?php
/**
 * Capabilities, nonces, integrity signatures and safe mode.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central security helpers.
 */
class MSST_Security {

	const CAP = 'msst_manage_snippets';

	/**
	 * Can the current user use the plugin at all?
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::CAP );
	}

	/**
	 * Can the current user create or edit raw JS/CSS/HTML?
	 *
	 * @return bool
	 */
	public static function can_edit_raw() {
		return self::can_manage() && current_user_can( 'unfiltered_html' );
	}

	/**
	 * Can the current user create or edit PHP snippets?
	 *
	 * Requires unfiltered_html, super admin on multisite and file editing not disabled
	 * (unless the site owner opts in with MSST_ALLOW_PHP_EDIT in wp-config.php).
	 *
	 * @return bool
	 */
	public static function can_edit_php() {
		if ( ! self::can_edit_raw() ) {
			return false;
		}
		if ( is_multisite() && ! is_super_admin() ) {
			return false;
		}
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT && ! ( defined( 'MSST_ALLOW_PHP_EDIT' ) && MSST_ALLOW_PHP_EDIT ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Guard for admin-post handlers: nonce and capability.
	 *
	 * @param string $action Action name without prefix.
	 */
	public static function guard( $action ) {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mudassar-snippet-studio' ), 403 );
		}
		check_admin_referer( 'msst_' . $action );
	}

	/**
	 * HMAC signature binding content to this site's secret keys.
	 *
	 * @param string $payload Data to sign.
	 * @return string
	 */
	public static function sign( $payload ) {
		return hash_hmac( 'sha256', (string) $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Constant-time signature check.
	 *
	 * @param string $payload   Data that was signed.
	 * @param string $signature Stored signature.
	 * @return bool
	 */
	public static function verify( $payload, $signature ) {
		return is_string( $signature ) && '' !== $signature && hash_equals( self::sign( $payload ), $signature );
	}

	/**
	 * Create the safe-mode secret if missing.
	 *
	 * @return string
	 */
	public static function ensure_secret() {
		$secret = get_option( 'msst_safe_secret', '' );
		if ( ! is_string( $secret ) || strlen( $secret ) < 24 ) {
			$secret = wp_generate_password( 32, false, false );
			update_option( 'msst_safe_secret', $secret, false );
		}
		return $secret;
	}

	/**
	 * Is safe mode active for this request?
	 *
	 * Triggered by the MSST_DISABLE_SNIPPETS constant or the secret URL parameter.
	 *
	 * @return bool
	 */
	public static function safe_mode() {
		static $active = null;
		if ( null !== $active ) {
			return $active;
		}
		$active = false;
		if ( defined( 'MSST_DISABLE_SNIPPETS' ) && MSST_DISABLE_SNIPPETS ) {
			$active = true;
		} elseif ( isset( $_GET['msst_safe_mode'] ) && is_string( $_GET['msst_safe_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Secret token compared in constant time.
			$given  = sanitize_text_field( wp_unslash( $_GET['msst_safe_mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$secret = (string) get_option( 'msst_safe_secret', '' );
			$active = '' !== $secret && hash_equals( $secret, $given );
		}
		return $active;
	}

	/**
	 * Clean a run-time test mode flag for the current user.
	 *
	 * @return bool
	 */
	public static function test_mode() {
		return is_user_logged_in() && (bool) get_user_meta( get_current_user_id(), 'msst_test_mode', true );
	}
}
