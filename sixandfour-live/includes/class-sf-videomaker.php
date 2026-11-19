<?php
/**
 * Lets an open admin or console page make the result video in the browser and hand it to the plugin.
 *
 * The browser asks for a video to make, builds the MP4, and uploads it here. The plugin then posts it.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_VideoMaker {

	const MAX_BYTES = 60 * MB_IN_BYTES;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
	}

	/** True when videos are made in the browser. */
	public static function active() {
		return SF_Options::video_mode() && 'browser' === SF_Options::get( 'video_engine', 'browser' );
	}

	public static function routes() {
		$admin = function () {
			return current_user_can( SF_Plugin::cap() );
		};
		$ns = SF_Passwordless::NS;
		register_rest_route( $ns, '/video/next', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_next' ), 'permission_callback' => $admin ) );
		register_rest_route( $ns, '/video/upload', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_upload' ), 'permission_callback' => $admin ) );
		register_rest_route( $ns, '/video/fail', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_fail' ), 'permission_callback' => $admin ) );
	}

	/** Load the maker on this plugin's admin screens and on a page with the console. */
	public static function assets() {
		if ( ! self::active() || ! current_user_can( SF_Plugin::cap() ) ) {
			return;
		}
		$on_admin = is_admin() && isset( $_GET['page'] ) && SF_Admin::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $on_admin && ! ( ! is_admin() && wp_script_is( 'sf-console', 'enqueued' ) ) ) {
			return;
		}
		wp_enqueue_script( 'sf-mp4-muxer', SF_URL . 'assets/vendor/mp4-muxer.js', array(), '5.2.2', true );
		wp_enqueue_script( 'sf-video-maker', SF_URL . 'assets/video-maker.js', array( 'sf-mp4-muxer' ), SF_VERSION, true );
		wp_localize_script(
			'sf-video-maker',
			'SFVM',
			array(
				'enabled'  => true,
				'rest'     => esc_url_raw( rest_url( SF_Passwordless::NS ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'fonts'    => array(
					'extra' => SF_URL . 'assets/fonts/Inter-ExtraBold.otf',
					'bold'  => SF_URL . 'assets/fonts/Inter-Bold.otf',
					'semi'  => SF_URL . 'assets/fonts/Inter-SemiBold.otf',
				),
				'flagBase' => SF_URL . 'assets/flags/',
				'logo'     => SF_URL . 'assets/img/logo-navy.png',
				'avatar'   => SF_URL . 'assets/video/avatar.png',
				'music'    => SF_URL . 'assets/video/music.mp3',
			)
		);
	}

	private static function payload_of( array $row ) {
		$p = json_decode( (string) $row['payload'], true );
		return is_array( $p ) ? $p : array();
	}

	/** Videos still to make: approved or pending, no file yet, and not claimed by another page a moment ago. */
	private static function todo() {
		$out = array();
		foreach ( array_merge( SF_DB::posts_by_status( 'approved', 20 ), SF_DB::posts_by_status( 'pending', 20 ) ) as $row ) {
			if ( 'video' !== $row['type'] ) {
				continue;
			}
			$p = self::payload_of( $row );
			if ( ! empty( $p['_file'] ) ) {
				continue;
			}
			if ( ! empty( $p['_claim'] ) && time() - (int) $p['_claim'] < 10 * MINUTE_IN_SECONDS ) {
				continue;
			}
			$out[] = array( $row, $p );
		}
		return array_reverse( $out );   // Oldest first.
	}

	public static function rest_next() {
		$todo = self::todo();
		if ( ! $todo ) {
			return array( 'job' => null );
		}
		list( $row, $p ) = $todo[0];
		$p['_claim']     = time();
		SF_DB::update_post( (int) $row['id'], array( 'payload' => wp_json_encode( $p ) ) );
		$clean = $p;
		foreach ( array_keys( $clean ) as $k ) {
			if ( 0 === strpos( $k, '_' ) ) {
				unset( $clean[ $k ] );
			}
		}
		return array( 'job' => array( 'id' => (int) $row['id'], 'payload' => $clean ) );
	}

	public static function rest_upload( WP_REST_Request $req ) {
		$id  = absint( $req->get_param( 'id' ) );
		$row = SF_DB::get_post( $id );
		if ( ! $row || 'video' !== $row['type'] || 'posted' === $row['status'] ) {
			return new WP_Error( 'sf_video_gone', 'That video post no longer exists or was already posted.', array( 'status' => 404 ) );
		}
		$files = $req->get_file_params();
		if ( ! empty( $files['file']['error'] ) && UPLOAD_ERR_OK !== (int) $files['file']['error'] ) {
			$limit = ini_get( 'upload_max_filesize' ) . ' / ' . ini_get( 'post_max_size' );
			return new WP_Error( 'sf_video_upload', 'The server refused the video upload. Its limits are upload_max_filesize and post_max_size (now ' . $limit . '). Raise both to at least 32M in Hostinger, PHP Configuration.', array( 'status' => 413 ) );
		}
		if ( empty( $files['file']['tmp_name'] ) && empty( $_FILES ) && isset( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification
			return new WP_Error( 'sf_video_upload', 'The server dropped the video upload because it is bigger than post_max_size (' . ini_get( 'post_max_size' ) . '). Raise upload_max_filesize and post_max_size to at least 32M in Hostinger, PHP Configuration.', array( 'status' => 413 ) );
		}
		if ( empty( $files['file']['tmp_name'] ) || ! is_uploaded_file( $files['file']['tmp_name'] ) && ! ( defined( 'SF_TEST_WP' ) && is_readable( $files['file']['tmp_name'] ) ) ) {
			return new WP_Error( 'sf_video_nofile', 'No video file came with the request.', array( 'status' => 400 ) );
		}
		$tmp  = $files['file']['tmp_name'];
		$size = filesize( $tmp );
		if ( $size < 20000 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'sf_video_size', 'The video file is the wrong size.', array( 'status' => 400 ) );
		}
		$head = file_get_contents( $tmp, false, null, 0, 12 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( 'ftyp' !== substr( (string) $head, 4, 4 ) ) {
			return new WP_Error( 'sf_video_type', 'That is not an MP4 video.', array( 'status' => 400 ) );
		}
		SF_Poster::protect_dir();
		$rel  = 'videos/' . gmdate( 'Y-m' ) . '/result-' . wp_generate_password( 16, false ) . '.mp4';
		$dest = SF_Poster::dir()['path'] . $rel;
		wp_mkdir_p( dirname( $dest ) );
		if ( ! copy( $tmp, $dest ) ) {
			return new WP_Error( 'sf_video_save', 'The video could not be saved on the server.', array( 'status' => 500 ) );
		}
		$p          = self::payload_of( $row );
		$p['_file'] = $rel;
		unset( $p['_claim'] );
		SF_DB::update_post( $id, array( 'payload' => wp_json_encode( $p ), 'scheduled_at' => null, 'error' => null ) );
		SF_Log::add( 'The video for post ' . $id . ' was made in the browser (' . round( $size / 1024 ) . ' KB).' );
		SF_Poster::publish_due();
		return array( 'ok' => true );
	}

	public static function rest_fail( WP_REST_Request $req ) {
		$id  = absint( $req->get_param( 'id' ) );
		$row = $id ? SF_DB::get_post( $id ) : null;
		if ( ! $row || 'video' !== $row['type'] ) {
			return array( 'ok' => true );
		}
		$msg = sanitize_text_field( (string) $req->get_param( 'message' ) );
		$p   = self::payload_of( $row );
		unset( $p['_claim'] );
		$n   = (int) $row['attempts'] + 1;
		if ( $n >= 3 ) {
			SF_DB::update_post( $id, array( 'status' => 'failed', 'attempts' => $n, 'error' => 'This browser could not make the video: ' . $msg . ' Open the page in Chrome or Edge on a computer or Android phone.', 'payload' => wp_json_encode( $p ) ) );
		} else {
			SF_DB::update_post( $id, array( 'attempts' => $n, 'error' => 'The browser could not make the video: ' . $msg, 'payload' => wp_json_encode( $p ) ) );
		}
		return array( 'ok' => true );
	}

	/** A public address for a saved video file. */
	public static function file_url( $rel ) {
		return SF_Poster::dir()['url'] . $rel;
	}
}
