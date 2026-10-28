<?php
/**
 * Password-free sign-in for administrators: passkeys (fingerprint, face or screen lock) and one-time email links.
 *
 * Only users who can use this plugin (administrators by default) can sign in this way.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Passwordless {

	const NS          = 'sixandfour/v1';
	const MAGIC_TTL   = 600;   // 10 minutes.

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'login_message', array( __CLASS__, 'login_block' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_assets' ) );
		add_action( 'init', array( __CLASS__, 'magic_landing' ), 1 );
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_passwords' ), 20, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	public static function rp_id() {
		return apply_filters( 'sf_webauthn_rp_id', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	public static function origin() {
		$u    = wp_parse_url( home_url() );
		$port = isset( $u['port'] ) ? ':' . $u['port'] : '';
		return apply_filters( 'sf_webauthn_origin', $u['scheme'] . '://' . $u['host'] . $port );
	}

	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return apply_filters( 'sf_client_ip', $ip );
	}

	/** Allow $limit tries per $window seconds for this address. */
	private static function throttle( $bucket, $limit, $window ) {
		$k = 'sf_thr_' . $bucket . '_' . md5( self::ip() );
		$n = (int) get_transient( $k );
		if ( $n >= $limit ) {
			return false;
		}
		set_transient( $k, $n + 1, $window );
		return true;
	}

	private static function can_admin( $user ) {
		return $user instanceof WP_User && user_can( $user, SF_Plugin::cap() );
	}

	public static function passkeys( $uid ) {
		$p = get_user_meta( $uid, 'sf_passkeys', true );
		return is_array( $p ) ? $p : array();
	}

	private static function save_passkeys( $uid, array $p ) {
		update_user_meta( $uid, 'sf_passkeys', $p );
	}

	private static function handle( $uid ) {
		$h = get_user_meta( $uid, 'sf_passkey_handle', true );
		if ( ! $h ) {
			$h = SF_WebAuthn::b64u_encode( random_bytes( 32 ) );
			update_user_meta( $uid, 'sf_passkey_handle', $h );
		}
		return $h;
	}

	private static function err( $msg, $status = 400 ) {
		return new WP_Error( 'sf_passkey', $msg, array( 'status' => $status ) );
	}

	/** Where to send someone after signing in. */
	private static function landing( $redirect = '' ) {
		$redirect = $redirect ? wp_validate_redirect( $redirect, '' ) : '';
		return $redirect ? $redirect : admin_url();
	}

	private static function finish_login( WP_User $user ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );
		SF_Security::clear_failures();
	}

	/* ------------------------------------------------------------------ */
	/* REST routes                                                         */
	/* ------------------------------------------------------------------ */

	public static function routes() {
		$admin = function () {
			return current_user_can( SF_Plugin::cap() );
		};
		$pub = '__return_true';
		$map = array(
			'/passkey/login/options'    => array( 'login_options', $pub ),
			'/passkey/login/verify'     => array( 'login_verify', $pub ),
			'/passkey/register/options' => array( 'register_options', $admin ),
			'/passkey/register/verify'  => array( 'register_verify', $admin ),
			'/passkey/delete'           => array( 'delete_passkey', $admin ),
			'/magic/request'            => array( 'magic_request', $pub ),
		);
		foreach ( $map as $route => $cfg ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $cfg[0] ),
					'permission_callback' => $cfg[1],
				)
			);
		}
	}

	public static function register_options( WP_REST_Request $req ) {
		$user = wp_get_current_user();
		$ch   = SF_WebAuthn::b64u_encode( random_bytes( 32 ) );
		set_transient( 'sf_wa_reg_' . $user->ID, $ch, 5 * MINUTE_IN_SECONDS );
		$exclude = array();
		foreach ( self::passkeys( $user->ID ) as $id => $p ) {
			$exclude[] = array( 'type' => 'public-key', 'id' => $id );
		}
		return array(
			'publicKey' => array(
				'challenge'              => $ch,
				'rp'                     => array( 'name' => get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'Cricket Sport Live', 'id' => self::rp_id() ),
				'user'                   => array( 'id' => self::handle( $user->ID ), 'name' => $user->user_login, 'displayName' => $user->display_name ),
				'pubKeyCredParams'       => array( array( 'type' => 'public-key', 'alg' => -7 ), array( 'type' => 'public-key', 'alg' => -257 ) ),
				'timeout'                => 60000,
				'attestation'            => 'none',
				'excludeCredentials'     => $exclude,
				'authenticatorSelection' => array( 'residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required' ),
			),
		);
	}

	public static function register_verify( WP_REST_Request $req ) {
		$user = wp_get_current_user();
		$ch   = get_transient( 'sf_wa_reg_' . $user->ID );
		delete_transient( 'sf_wa_reg_' . $user->ID );
		if ( ! $ch ) {
			return self::err( 'The request expired. Press Add a passkey again.' );
		}
		$r = (array) $req->get_param( 'response' );
		try {
			list( $id, $pem, $alg, $count ) = SF_WebAuthn::verify_registration(
				isset( $r['clientDataJSON'] ) ? $r['clientDataJSON'] : '',
				isset( $r['attestationObject'] ) ? $r['attestationObject'] : '',
				$ch,
				self::origin(),
				self::rp_id()
			);
		} catch ( RuntimeException $e ) {
			return self::err( $e->getMessage() );
		}
		$keys = self::passkeys( $user->ID );
		if ( count( $keys ) >= 10 ) {
			return self::err( 'You already have 10 passkeys. Remove one first.' );
		}
		$name = sanitize_text_field( (string) $req->get_param( 'name' ) );
		$keys[ $id ] = array(
			'pem'     => $pem,
			'alg'     => $alg,
			'count'   => $count,
			'name'    => '' !== $name ? mb_substr( $name, 0, 40 ) : 'Passkey ' . ( count( $keys ) + 1 ),
			'created' => time(),
			'used'    => 0,
		);
		self::save_passkeys( $user->ID, $keys );
		SF_Log::add( 'A passkey was added for ' . $user->user_login . '.' );
		return array( 'ok' => true, 'name' => $keys[ $id ]['name'] );
	}

	public static function delete_passkey( WP_REST_Request $req ) {
		$user = wp_get_current_user();
		$id   = (string) $req->get_param( 'id' );
		$keys = self::passkeys( $user->ID );
		if ( ! isset( $keys[ $id ] ) ) {
			return self::err( 'That passkey was not found.', 404 );
		}
		if ( SF_Options::get( 'passwordless_only' ) && 1 === count( $keys ) ) {
			return self::err( 'Passwords are off, so keep at least one passkey. Turn passwords back on first.' );
		}
		unset( $keys[ $id ] );
		self::save_passkeys( $user->ID, $keys );
		return array( 'ok' => true );
	}

	public static function login_options( WP_REST_Request $req ) {
		if ( ! self::throttle( 'wa_opt', 30, 10 * MINUTE_IN_SECONDS ) ) {
			return self::err( 'Too many tries. Wait a few minutes.', 429 );
		}
		$ch = SF_WebAuthn::b64u_encode( random_bytes( 32 ) );
		$id = bin2hex( random_bytes( 12 ) );
		set_transient( 'sf_wa_login_' . $id, $ch, 5 * MINUTE_IN_SECONDS );
		return array(
			'id'        => $id,
			'publicKey' => array( 'challenge' => $ch, 'rpId' => self::rp_id(), 'timeout' => 60000, 'userVerification' => 'required' ),
		);
	}

	public static function login_verify( WP_REST_Request $req ) {
		$fail = function ( $msg ) {
			SF_Security::record_failure( 'passkey' );
			return self::err( $msg, 401 );
		};
		if ( SF_Security::is_locked() ) {
			return self::err( 'Too many failed sign-ins. Try again in a few minutes.', 429 );
		}
		$id = sanitize_key( (string) $req->get_param( 'id' ) );
		$ch = $id ? get_transient( 'sf_wa_login_' . $id ) : false;
		if ( $id ) {
			delete_transient( 'sf_wa_login_' . $id );
		}
		if ( ! $ch ) {
			return self::err( 'The sign-in request expired. Try again.', 400 );
		}
		$cred = (array) $req->get_param( 'credential' );
		$r    = isset( $cred['response'] ) ? (array) $cred['response'] : array();
		$cid  = isset( $cred['id'] ) ? (string) $cred['id'] : '';
		$uh   = isset( $r['userHandle'] ) ? (string) $r['userHandle'] : '';
		if ( '' === $cid || '' === $uh ) {
			return $fail( 'This passkey did not say who it belongs to.' );
		}
		$users = get_users( array( 'meta_key' => 'sf_passkey_handle', 'meta_value' => $uh, 'number' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$user  = $users ? $users[0] : null;
		$keys  = $user ? self::passkeys( $user->ID ) : array();
		if ( ! $user || ! isset( $keys[ $cid ] ) ) {
			return $fail( 'That passkey is not registered on this site.' );
		}
		try {
			$count = SF_WebAuthn::verify_assertion(
				isset( $r['clientDataJSON'] ) ? $r['clientDataJSON'] : '',
				isset( $r['authenticatorData'] ) ? $r['authenticatorData'] : '',
				isset( $r['signature'] ) ? $r['signature'] : '',
				$keys[ $cid ],
				$ch,
				self::origin(),
				self::rp_id()
			);
		} catch ( RuntimeException $e ) {
			return $fail( $e->getMessage() );
		}
		if ( ! self::can_admin( $user ) ) {
			return $fail( 'This account cannot use passkeys here.' );
		}
		$keys[ $cid ]['count'] = $count;
		$keys[ $cid ]['used']  = time();
		self::save_passkeys( $user->ID, $keys );
		self::finish_login( $user );
		SF_Log::add( $user->user_login . ' signed in with a passkey.' );
		return array( 'ok' => true, 'redirect' => self::landing( (string) $req->get_param( 'redirect_to' ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Email link                                                          */
	/* ------------------------------------------------------------------ */

	public static function magic_request( WP_REST_Request $req ) {
		$reply = array( 'ok' => true, 'message' => 'If that account can sign in this way, a link is on its way. It works once and lasts 10 minutes.' );
		if ( ! SF_Options::get( 'magic_link', 1 ) ) {
			return $reply;
		}
		if ( ! self::throttle( 'magic', 5, 15 * MINUTE_IN_SECONDS ) ) {
			return self::err( 'Too many requests. Wait 15 minutes.', 429 );
		}
		$login = sanitize_text_field( (string) $req->get_param( 'login' ) );
		$user  = false;
		if ( '' !== $login ) {
			$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
		}
		if ( $user && self::can_admin( $user ) ) {
			$last = (int) get_user_meta( $user->ID, 'sf_magic_sent', true );
			if ( time() - $last >= 60 ) {
				$token = SF_WebAuthn::b64u_encode( random_bytes( 32 ) );
				update_user_meta( $user->ID, 'sf_magic', array( hash( 'sha256', $token ), time() + self::MAGIC_TTL ) );
				update_user_meta( $user->ID, 'sf_magic_sent', time() );
				$link = add_query_arg( array( 'sf_magic' => $token, 'sf_u' => $user->ID ), home_url( '/' ) );
				$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
				wp_mail(
					$user->user_email,
					'Your sign-in link for ' . $site,
					"Hi " . $user->display_name . ",\n\nOpen this link to sign in to " . $site . ". It works once and expires in 10 minutes.\n\n" . $link . "\n\nIf you did not ask for this, ignore this email. Nobody can sign in without the link."
				);
			}
		}
		return $reply;
	}

	/** The page a link opens. It asks for one tap, so email scanners that open links cannot use it up. */
	public static function magic_landing() {
		if ( ! isset( $_GET['sf_magic'], $_GET['sf_u'] ) || is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['sf_magic'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$uid   = absint( $_GET['sf_u'] ); // phpcs:ignore WordPress.Security.NonceVerification
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		$user = get_user_by( 'id', $uid );
		$meta = $user ? get_user_meta( $uid, 'sf_magic', true ) : false;
		$good = $user && is_array( $meta ) && time() < (int) $meta[1] && hash_equals( (string) $meta[0], hash( 'sha256', $token ) ) && self::can_admin( $user ) && SF_Options::get( 'magic_link', 1 );

		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['sf_magic_go'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! self::throttle( 'magic_use', 10, 15 * MINUTE_IN_SECONDS ) ) {
				self::landing_page( 'Too many tries. Wait 15 minutes.', false );
			}
			if ( $good ) {
				delete_user_meta( $uid, 'sf_magic' );   // One use only.
				self::finish_login( $user );
				SF_Log::add( $user->user_login . ' signed in with an email link.' );
				wp_safe_redirect( self::landing( '' ) );
				exit;
			}
			self::landing_page( 'This link has expired or was already used. Ask for a new one.', false );
		}
		self::landing_page( $good ? 'Tap the button to sign in as ' . $user->display_name . '.' : 'This link has expired or was already used. Ask for a new one.', (bool) $good );
	}

	private static function landing_page( $message, $show_button ) {
		$btn = $show_button ? '<form method="post"><button name="sf_magic_go" value="1" type="submit">Sign in</button></form>' : '<a class="alt" href="' . esc_url( wp_login_url() ) . '">Go to the sign-in page</a>';
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Sign in</title><style>'
			. 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text",Inter,system-ui,sans-serif;color:#1d1d1f}'
			. 'main{background:#fff;border-radius:22px;padding:32px 28px;width:min(92vw,380px);text-align:center;box-shadow:0 8px 28px rgba(10,27,68,.1)}'
			. 'h1{font-size:22px;margin:0 0 10px;letter-spacing:-.02em}p{color:#6e6e73;margin:0 0 22px;line-height:1.45}'
			. 'button{width:100%;min-height:52px;border:0;border-radius:999px;background:#0a1b44;color:#fff;font-size:17px;font-weight:600;cursor:pointer}'
			. '.alt{display:block;min-height:52px;line-height:52px;border-radius:999px;background:#ececf0;color:#0a1b44;font-weight:600;text-decoration:none}'
			. '</style></head><body><main><h1>Cricket Sport Live</h1><p>' . esc_html( $message ) . '</p>' . $btn . '</main></body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Passwords off                                                       */
	/* ------------------------------------------------------------------ */

	public static function block_passwords( $user, $password ) {
		if ( ! SF_Options::get( 'passwordless_only' ) || ( defined( 'SF_ALLOW_PASSWORD' ) && SF_ALLOW_PASSWORD ) ) {
			return $user;
		}
		if ( self::can_admin( $user ) ) {
			return new WP_Error( 'sf_passwordless', 'Passwords are turned off for administrators. Use your passkey, or ask for an email sign-in link below.' );
		}
		return $user;
	}

	/* ------------------------------------------------------------------ */
	/* Sign-in page                                                        */
	/* ------------------------------------------------------------------ */

	private static function is_plain_login() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $action, array( 'login', '' ), true ) && empty( $_GET['interim-login'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function login_assets() {
		if ( ! self::is_plain_login() ) {
			return;
		}
		wp_enqueue_style( 'sf-login', SF_URL . 'assets/login.css', array(), SF_VERSION );
		wp_enqueue_script( 'sf-passkey', SF_URL . 'assets/sf-passkey.js', array(), SF_VERSION, true );
		wp_enqueue_script( 'sf-login', SF_URL . 'assets/login.js', array( 'sf-passkey' ), SF_VERSION, true );
		wp_localize_script( 'sf-login', 'SFLogin', self::js_config() );
	}

	public static function js_config() {
		return array(
			'rest'     => esc_url_raw( rest_url( self::NS ) ),
			'magic'    => (bool) SF_Options::get( 'magic_link', 1 ),
			'redirect' => isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		);
	}

	public static function login_block( $message ) {
		if ( ! self::is_plain_login() ) {
			return $message;
		}
		$magic = SF_Options::get( 'magic_link', 1 );
		ob_start();
		?>
		<div class="sf-pl" id="sf-pl" hidden>
			<button type="button" class="sf-pl-btn sf-pl-main" id="sf-pk-login">Sign in with a passkey</button>
			<?php if ( $magic ) : ?>
				<button type="button" class="sf-pl-btn" id="sf-ml-open">Email me a sign-in link</button>
				<div class="sf-pl-ml" id="sf-ml" hidden>
					<label for="sf-ml-login">Your user name or email</label>
					<input type="text" id="sf-ml-login" autocomplete="username" autocapitalize="none">
					<button type="button" class="sf-pl-btn sf-pl-main" id="sf-ml-send">Send the link</button>
				</div>
			<?php endif; ?>
			<p class="sf-pl-msg" id="sf-pl-msg" role="status" aria-live="polite"></p>
			<p class="sf-pl-or">or use your password</p>
		</div>
		<?php
		return $message . ob_get_clean();
	}
}
