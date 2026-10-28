<?php
/**
 * Site hardening that goes with the plugin, each switched on or off in the Security screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Security {

	const LOCK_TRIES  = 5;
	const LOCK_WINDOW = 900;  // 15 minutes.
	const LOCK_TIME   = 900;

	public static function defaults() {
		return array(
			'login_limit'   => 1,
			'block_editor'  => 1,
			'xmlrpc_off'    => 1,
			'hide_version'  => 1,
			'user_enum_off' => 1,
			'headers'       => 1,
		);
	}

	public static function on( $key ) {
		$s = SF_Options::get( 'security', array() );
		$s = array_merge( self::defaults(), is_array( $s ) ? $s : array() );
		return ! empty( $s[ $key ] );
	}

	public static function init() {
		if ( self::on( 'login_limit' ) ) {
			add_filter( 'authenticate', array( __CLASS__, 'check_lock' ), 30, 3 );
			add_action( 'wp_login_failed', array( __CLASS__, 'record_failure' ) );
			add_action( 'wp_login', array( __CLASS__, 'clear_failures' ), 10, 1 );
		}
		if ( self::on( 'block_editor' ) ) {
			add_filter( 'map_meta_cap', array( __CLASS__, 'block_editor' ), 10, 2 );
		}
		if ( self::on( 'xmlrpc_off' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( __CLASS__, 'drop_pingback_header' ) );
			// The filter above only stops methods that need a log-in. Refuse the whole door, including pingbacks.
			if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
				status_header( 403 );
				header( 'Content-Type: text/plain; charset=utf-8' );
				exit( 'XML-RPC is turned off on this site.' );
			}
		}
		if ( self::on( 'hide_version' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		if ( self::on( 'user_enum_off' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'block_author_scan' ), 1 );
			add_filter( 'rest_endpoints', array( __CLASS__, 'hide_rest_users' ) );
		}
		if ( self::on( 'headers' ) ) {
			add_action( 'send_headers', array( __CLASS__, 'send_headers' ) );
			add_action( 'admin_init', array( __CLASS__, 'send_headers' ) );
		}
	}

	/* ---- Login limiter ---- */

	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return apply_filters( 'sf_client_ip', $ip );
	}

	private static function tkey( $kind ) {
		return 'sf_login_' . $kind . '_' . md5( self::ip() );
	}

	public static function is_locked() {
		return (int) get_transient( self::tkey( 'lock' ) ) > time();
	}

	public static function check_lock( $user, $username = '', $password = '' ) {
		$until = (int) get_transient( self::tkey( 'lock' ) );
		if ( $until > time() ) {
			return new WP_Error( 'sf_locked', sprintf( 'Too many failed log-ins. Try again in %d minutes.', max( 1, (int) ceil( ( $until - time() ) / 60 ) ) ) );
		}
		return $user;
	}

	public static function record_failure( $username = '' ) {
		$k = self::tkey( 'fail' );
		$n = (int) get_transient( $k ) + 1;
		set_transient( $k, $n, self::LOCK_WINDOW );
		if ( $n >= self::LOCK_TRIES ) {
			set_transient( self::tkey( 'lock' ), time() + self::LOCK_TIME, self::LOCK_TIME );
			delete_transient( $k );
			SF_Log::add( 'Log-in locked for 15 minutes after ' . self::LOCK_TRIES . ' failed tries from ' . self::ip() . '.', 'warning' );
		}
	}

	public static function clear_failures( $login = '' ) {
		delete_transient( self::tkey( 'fail' ) );
		delete_transient( self::tkey( 'lock' ) );
	}

	/* ---- Other hardening ---- */

	public static function block_editor( $caps, $cap ) {
		if ( in_array( $cap, array( 'edit_plugins', 'edit_themes', 'edit_files' ), true ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	public static function drop_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function block_author_scan() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( isset( $_GET['author'] ) || is_author() ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public static function hide_rest_users( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	public static function send_headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	}

	/* ---- Health check ---- */

	/** @return array of [ label, status ok|warn|bad, detail ] */
	public static function checks() {
		$rows = array();
		$add  = function ( $label, $status, $detail ) use ( &$rows ) {
			$rows[] = array( $label, $status, $detail );
		};
		$https = is_ssl() || 0 === strpos( home_url(), 'https://' );
		$add( 'HTTPS', $https ? 'ok' : 'bad', $https ? 'The site uses HTTPS.' : 'Turn on the free SSL certificate in Hostinger. Tokens and log-ins should never travel over plain HTTP.' );

		$o   = get_option( SF_Options::KEY, array() );
		$enc = true;
		$any = false;
		foreach ( array( 'fb_token', 'api_key' ) as $k ) {
			if ( ! empty( $o[ $k ] ) ) {
				$any = true;
				$enc = $enc && SF_Secrets::is_encrypted( $o[ $k ] );
			}
		}
		if ( ! SF_Secrets::available() ) {
			$add( 'Secrets encrypted', 'warn', 'PHP OpenSSL with AES-GCM is missing, so tokens are stored as plain text. Ask Hostinger to enable OpenSSL.' );
		} else {
			$add( 'Secrets encrypted', ( ! $any || $enc ) ? 'ok' : 'warn', ( ! $any || $enc ) ? 'The Facebook token and API key are encrypted in the database.' : 'Save Setup once to encrypt the stored secrets.' );
		}

		$dir  = SF_Poster::dir()['path'];
		$safe = is_readable( $dir . '.htaccess' ) && is_readable( $dir . 'index.php' );
		$add( 'Picture folder protected', $safe ? 'ok' : 'warn', $safe ? 'The picture folder lists nothing and cannot run PHP files.' : 'The protection files are missing. Save Setup to create them.' );

		$fe = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$add( 'File editor', ( $fe || self::on( 'block_editor' ) ) ? 'ok' : 'warn', ( $fe || self::on( 'block_editor' ) ) ? 'The built-in plugin and theme editor is blocked.' : 'Turn on Block the file editor below.' );

		$dbg = defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY );
		$add( 'Error display', $dbg ? 'bad' : 'ok', $dbg ? 'PHP errors are shown on the page. Set WP_DEBUG to false on a live site.' : 'PHP errors are not shown to visitors.' );

		$php = PHP_VERSION;
		$add( 'PHP version', version_compare( $php, '8.1', '>=' ) ? 'ok' : ( version_compare( $php, '8.0', '>=' ) ? 'warn' : 'bad' ), 'Running PHP ' . $php . '. ' . ( version_compare( $php, '8.1', '>=' ) ? 'Good.' : 'Older versions stop getting security fixes. Switch in Hostinger hPanel under PHP Configuration.' ) );

		$has_admin = (bool) get_user_by( 'login', 'admin' );
		$add( 'Admin user name', $has_admin ? 'warn' : 'ok', $has_admin ? 'A user called "admin" exists. Attackers try this name first. Create a different administrator and delete this one.' : 'No user is called "admin".' );

		$fb = get_option( 'sf_fb_status', array() );
		$add( 'Facebook token', ( is_array( $fb ) && ! empty( $fb['ok'] ) ) ? 'ok' : 'warn', ( is_array( $fb ) && ! empty( $fb['ok'] ) ) ? 'The last check passed.' : 'Not verified yet, or it failed. Use Test connection in Setup.' );

		$pk = false;
		foreach ( get_users( array( 'capability' => SF_Plugin::cap(), 'fields' => 'ID', 'number' => 50 ) ) as $uid ) {
			if ( SF_Passwordless::passkeys( (int) $uid ) ) {
				$pk = true;
				break;
			}
		}
		$add( 'Passkey for administrators', $pk ? 'ok' : 'warn', $pk ? 'At least one administrator can sign in with a passkey.' : 'Add a passkey below so you can sign in with a fingerprint or face.' );
		$po = (bool) SF_Options::get( 'passwordless_only', 0 );
		$add( 'Passwords off for administrators', $po ? 'ok' : 'warn', $po ? 'Administrators sign in with a passkey or an email link only.' : 'Optional. Turn it on below once a passkey is added.' );

		$add( 'Log-in limiter', self::on( 'login_limit' ) ? 'ok' : 'warn', self::on( 'login_limit' ) ? 'Five wrong passwords lock that address out for 15 minutes.' : 'Off.' );
		return $rows;
	}
}
