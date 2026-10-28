<?php
/**
 * The mobile console: a phone-first control screen that you place on any page with [sixandfour_admin].
 *
 * Visitors see only a sign-in card. Administrators see the console. Every action goes through the REST API
 * with a nonce, and every request checks the same capability as the admin screens.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Console {

	const SHORTCODE = 'sixandfour_admin';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	private static function on_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$p = get_post();
		return $p && has_shortcode( (string) $p->post_content, self::SHORTCODE );
	}

	public static function admin_bar( $show ) {
		return self::on_page() ? false : $show;
	}

	public static function body_class( $c ) {
		if ( self::on_page() ) {
			$c[] = 'sfc-page';
		}
		return $c;
	}

	public static function head() {
		if ( ! self::on_page() ) {
			return;
		}
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
		echo '<meta name="theme-color" content="#0a1b44">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="Six &amp; Four">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	}

	public static function assets() {
		if ( ! self::on_page() ) {
			return;
		}
		wp_enqueue_style( 'sf-console', SF_URL . 'assets/console.css', array(), SF_VERSION );
		wp_enqueue_script( 'sf-passkey', SF_URL . 'assets/sf-passkey.js', array(), SF_VERSION, true );
		wp_enqueue_script( 'sf-console', SF_URL . 'assets/console.js', array( 'sf-passkey' ), SF_VERSION, true );
		wp_localize_script(
			'sf-console',
			'SFC',
			array(
				'rest'    => esc_url_raw( rest_url( SF_Passwordless::NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'isAdmin' => current_user_can( SF_Plugin::cap() ),
				'magic'   => (bool) SF_Options::get( 'magic_link', 1 ),
				'login'   => esc_url_raw( wp_login_url( get_permalink() ) ),
				'admin'   => esc_url_raw( admin_url( 'admin.php?page=sixandfour-live' ) ),
				'logout'  => esc_url_raw( wp_logout_url( get_permalink() ) ),
				'site'    => get_bloginfo( 'name' ),
				'version' => SF_VERSION,
			)
		);
	}

	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'fullscreen' => '1' ), $atts, self::SHORTCODE );
		$full = '0' !== $atts['fullscreen'] ? ' sfc-full' : '';
		nocache_headers();
		return '<div id="sfc" class="sfc' . esc_attr( $full ) . '" data-state="loading"><noscript><p class="sfc-noscript">Please turn on JavaScript to use this page.</p></noscript></div>';
	}

	/* ------------------------------------------------------------------ */
	/* REST                                                                */
	/* ------------------------------------------------------------------ */

	public static function routes() {
		$admin = function () {
			return current_user_can( SF_Plugin::cap() );
		};
		$r = function ( $path, $method, $cb ) use ( $admin ) {
			register_rest_route( SF_Passwordless::NS, '/console/' . $path, array( 'methods' => $method, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => $admin ) );
		};
		$r( 'state', 'GET', 'rest_state' );
		$r( 'queue', 'POST', 'rest_queue' );
		$r( 'check', 'POST', 'rest_check' );
		$r( 'mode', 'POST', 'rest_mode' );
		$r( 'pause', 'POST', 'rest_pause' );
		$r( 'job', 'POST', 'rest_job' );
		$r( 'manual', 'POST', 'rest_manual' );
	}

	private static function core_jobs() {
		return array(
			'toss'       => 'Toss',
			'live'       => 'Live updates',
			'wicket'     => 'Every wicket',
			'result'     => 'Result',
			'summary'    => 'Match summary',
			'milestones' => 'Milestone alerts',
			'poll'       => 'Pick a side',
			'table'      => 'Weekly points table',
			'ranking'    => 'Monthly team ranking',
			'potm'       => 'Top performer card',
			'fixtures'   => "Today's matches",
		);
	}

	public static function state() {
		$o     = SF_Options::all();
		$fb    = get_option( 'sf_fb_status', array() );
		$fb    = is_array( $fb ) ? $fb : array();
		$stats = SF_API::stats();
		list( $seen, $raw ) = SF_API::last_matches();
		$matches = array();
		$live_i  = 0;
		foreach ( $raw as $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$m = SF_API::normalize_match( $m );
			if ( '' === $m['id'] || ! SF_API::wanted( $m ) ) {
				continue;
			}
			if ( $m['ended'] ) {
				$plan = 'Done';
			} elseif ( $m['started'] ) {
				$plan = $live_i < (int) $o['live_cards'] ? 'Live card' : 'In scoreboard';
				$live_i++;
			} else {
				$plan = 'Upcoming';
			}
			$matches[] = array(
				'time'   => $m['start'] ? wp_date( 'H:i', $m['start'] ) : '',
				'teams'  => $m['teams'][0]['name'] . ' v ' . $m['teams'][1]['name'],
				'series' => SF_Options::series_name( $m['series_id'] ),
				'status' => $m['status'],
				'plan'   => $plan,
			);
		}
		$queue = array();
		foreach ( array_merge( SF_DB::posts_by_status( 'pending', 20 ), SF_DB::posts_by_status( 'failed', 10 ), SF_DB::posts_by_status( 'approved', 10 ) ) as $p ) {
			$queue[] = self::post_view( $p );
		}
		$recent = array();
		foreach ( SF_DB::recent_posts( 10 ) as $p ) {
			$recent[] = self::post_view( $p );
		}
		$jobs = array();
		foreach ( self::core_jobs() as $k => $label ) {
			$jobs[] = array( 'key' => $k, 'label' => $label, 'on' => ! empty( $o['jobs'][ $k ] ) );
		}
		$log = array();
		foreach ( SF_Log::recent( 6 ) as $l ) {
			$log[] = array( 'time' => wp_date( 'H:i', $l[0] ), 'text' => $l[2] );
		}
		$next = (int) get_option( 'sf_next_check', 0 );
		return array(
			'ready'    => SF_Options::ready(),
			'mode'     => $o['mode'],
			'paused'   => (bool) $o['paused'],
			'fb'       => array( 'ok' => ! empty( $fb['ok'] ), 'name' => isset( $fb['name'] ) ? $fb['name'] : '', 'fans' => isset( $fb['fans'] ) ? (int) $fb['fans'] : 0, 'message' => isset( $fb['message'] ) ? $fb['message'] : '' ),
			'api'      => array( 'hits' => (int) $stats['hits'], 'limit' => (int) $stats['limit'], 'keys' => (int) $stats['keys'] ),
			'next'     => $next > time() ? human_time_diff( time(), $next ) : 'now',
			'seen'     => $seen ? human_time_diff( $seen ) . ' ago' : '',
			'matches'  => array_slice( $matches, 0, 20 ),
			'queue'    => $queue,
			'recent'   => $recent,
			'jobs'     => $jobs,
			'log'      => $log,
			'passkeys' => count( SF_Passwordless::passkeys( get_current_user_id() ) ),
			'user'     => wp_get_current_user()->display_name,
		);
	}

	private static function post_view( array $p ) {
		return array(
			'id'      => (int) $p['id'],
			'type'    => $p['type'],
			'status'  => $p['status'],
			'caption' => $p['caption'],
			'image'   => SF_Poster::image_url( $p ),
			'error'   => (string) $p['error'],
			'time'    => wp_date( 'H:i', strtotime( ( $p['posted_at'] ? $p['posted_at'] : $p['created_at'] ) . ' UTC' ) ),
			'when'    => $p['scheduled_at'] ? wp_date( 'j M H:i', strtotime( $p['scheduled_at'] . ' UTC' ) ) : '',
		);
	}

	public static function rest_state() {
		return self::state();
	}

	public static function rest_queue( WP_REST_Request $req ) {
		$id  = absint( $req->get_param( 'id' ) );
		$act = (string) $req->get_param( 'do' );
		$row = SF_DB::get_post( $id );
		if ( ! $row ) {
			return new WP_Error( 'sf_gone', 'That post no longer exists.', array( 'status' => 404 ) );
		}
		if ( 'posted' === $row['status'] ) {
			return new WP_Error( 'sf_posted', 'That post was already sent, so it cannot be changed.', array( 'status' => 409 ) );
		}
		if ( 'skip' === $act ) {
			SF_Poster::skip( $id );
			return array( 'message' => 'Skipped.', 'state' => self::state() );
		}
		if ( 'approve' === $act || 'retry' === $act ) {
			$cap = $req->get_param( 'caption' );
			if ( null !== $cap ) {
				SF_DB::update_post( $id, array( 'caption' => sanitize_textarea_field( (string) $cap ) ) );
			}
			SF_Poster::approve( $id );
			$res = SF_Poster::publish_one( SF_DB::get_post( $id ) );
			$msg = array( 'posted' => 'Posted to Facebook.', 'waiting' => 'Approved. It goes out when the posting rules allow.', 'skipped' => 'Skipped by the posting rules.', 'failed' => 'Facebook refused it. The reason is shown on the post.' );
			return array( 'message' => $msg[ $res ], 'ok' => ( 'posted' === $res || 'waiting' === $res ), 'state' => self::state() );
		}
		return new WP_Error( 'sf_bad', 'Unknown action.', array( 'status' => 400 ) );
	}

	public static function rest_check() {
		if ( ! SF_Options::ready() ) {
			return new WP_Error( 'sf_setup', 'Finish Setup first: the Page, the token and the API key.', array( 'status' => 409 ) );
		}
		if ( SF_Options::get( 'paused' ) ) {
			return new WP_Error( 'sf_paused', 'Posting is paused. Turn it back on first.', array( 'status' => 409 ) );
		}
		SF_Engine::tick( true );
		return array( 'message' => 'Checked the matches.', 'state' => self::state() );
	}

	public static function rest_mode( WP_REST_Request $req ) {
		$mode = 'approve' === $req->get_param( 'mode' ) ? 'approve' : 'auto';
		SF_Options::update( array( 'mode' => $mode ) );
		return array( 'message' => 'auto' === $mode ? 'Auto-post is on.' : 'New posts will wait for your approval.', 'state' => self::state() );
	}

	public static function rest_pause( WP_REST_Request $req ) {
		$p = (bool) $req->get_param( 'pause' );
		SF_Options::update( array( 'paused' => $p ? 1 : 0 ) );
		SF_Log::add( $p ? 'Posting was paused from the mobile console.' : 'Posting was resumed from the mobile console.' );
		return array( 'message' => $p ? 'Posting is paused.' : 'Posting is back on.', 'state' => self::state() );
	}

	public static function rest_job( WP_REST_Request $req ) {
		$k    = sanitize_key( (string) $req->get_param( 'job' ) );
		$jobs = self::core_jobs();
		if ( ! isset( $jobs[ $k ] ) ) {
			return new WP_Error( 'sf_bad', 'Unknown setting.', array( 'status' => 400 ) );
		}
		$o          = SF_Options::all();
		$j          = $o['jobs'];
		$j[ $k ]    = $req->get_param( 'on' ) ? 1 : 0;
		SF_Options::update( array( 'jobs' => $j ) );
		return array( 'message' => $jobs[ $k ] . ( $j[ $k ] ? ' is on.' : ' is off.' ), 'state' => self::state() );
	}

	public static function rest_manual( WP_REST_Request $req ) {
		$files = $req->get_file_params();
		$res   = SF_Poster::manual_post(
			array(
				'caption'      => (string) $req->get_param( 'caption' ),
				'file'         => isset( $files['image'] ) ? $files['image'] : null,
				'add_question' => (bool) $req->get_param( 'add_question' ),
				'watermark'    => (bool) $req->get_param( 'watermark' ),
				'wm_pos'       => (string) $req->get_param( 'wm_pos' ),
				'when'         => (string) $req->get_param( 'when' ),
				'when_at'      => (string) $req->get_param( 'when_at' ),
			)
		);
		if ( ! $res['ok'] ) {
			return new WP_Error( 'sf_manual', $res['message'], array( 'status' => 400 ) );
		}
		return array( 'message' => $res['message'], 'state' => self::state() );
	}
}
