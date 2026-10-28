<?php
/**
 * The WordPress admin screens: menu, tabs, and the form handlers.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Admin {

	const PAGE = 'sixandfour-live';

	public static function tabs() {
		return array(
			'overview'    => 'Overview',
			'queue'       => 'Post queue',
			'cards'       => 'Score cards',
			'engage'      => 'Engage',
			'tournaments' => 'Tournaments',
			'teams'       => 'Teams',
			'manual'      => 'Manual post',
			'security'    => 'Security',
			'setup'       => 'Setup',
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save_setup', 'test_fb', 'test_api', 'run_now', 'queue_action', 'save_engage', 'follow', 'unfollow', 'search_series', 'save_teams', 'manual_post', 'create_pinned', 'sample', 'save_security' ) as $a ) {
			add_action( 'admin_post_sf_' . $a, array( __CLASS__, 'do_' . $a ) );
		}
	}

	/** Who may use the plugin. Change it with the sf_capability filter. */
	public static function cap() {
		return apply_filters( 'sf_capability', 'manage_options' );
	}

	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><g transform="rotate(25 120 120)"><path d="M115 12H125L126 16V74L140 92V214L134 222H106L100 214V92L114 74V16Z" fill="#a7aaad"/></g><path d="M8 190C70 166 170 98 234 18C174 124 76 198 8 190Z" fill="#a7aaad"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		add_menu_page( 'Six & Four Live', 'Six & Four', self::cap(), self::PAGE, array( __CLASS__, 'render' ), $icon, 58 );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'sf-admin', SF_URL . 'assets/admin.css', array(), SF_VERSION );
		wp_enqueue_script( 'sf-admin', SF_URL . 'assets/admin.js', array( 'jquery' ), SF_VERSION, true );
		$tab = self::tab();
		if ( in_array( $tab, array( 'teams', 'manual' ), true ) ) {
			wp_enqueue_media();
		}
	}

	public static function tab() {
		$t = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification
		return isset( self::tabs()[ $t ] ) ? $t : 'overview';
	}

	public static function url( $tab = 'overview', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	public static function notice( $type, $msg ) {
		set_transient( 'sf_notice_' . get_current_user_id(), array( $type, $msg ), 120 );
	}

	private static function done( $tab, $type, $msg ) {
		self::notice( $type, $msg );
		wp_safe_redirect( self::url( $tab ) );
		exit;
	}

	private static function guard( $nonce_action = 'sf_admin' ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sixandfour-live' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	public static function render() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to see this page.', 'sixandfour-live' ), 403 );
		}
		$tab    = self::tab();
		$notice = get_transient( 'sf_notice_' . get_current_user_id() );
		delete_transient( 'sf_notice_' . get_current_user_id() );
		echo '<div class="wrap sf-wrap"><h1 class="sf-title">Six &amp; Four Live</h1>';
		if ( $notice ) {
			printf( '<div class="sf-notice sf-%s" role="status">%s</div>', esc_attr( $notice[0] ), esc_html( $notice[1] ) );
		}
		echo '<nav class="sf-tabs" aria-label="Sections">';
		$pending = SF_DB::count_status( 'pending' );
		foreach ( self::tabs() as $key => $label ) {
			$badge = ( 'queue' === $key && $pending > 0 ) ? ' <span class="sf-count">' . (int) $pending . '</span>' : '';
			printf( '<a href="%s"%s>%s%s</a>', esc_url( self::url( $key ) ), $tab === $key ? ' aria-current="page"' : '', esc_html( $label ), $badge ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</nav>';
		$file = SF_DIR . 'admin/views/' . $tab . '.php';
		if ( is_readable( $file ) ) {
			include $file;
		}
		echo '<p class="sf-credit">Six &amp; Four Live ' . esc_html( SF_VERSION ) . ' by <a href="https://mudassar.work" target="_blank" rel="noopener">Mudassar</a></p></div>';
	}

	/** Open a form that posts to a handler. */
	public static function form_open( $action, $extra = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="action" value="sf_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'sf_admin' );
	}

	public static function switch_row( $name, $checked, $title, $desc = '' ) {
		printf(
			'<div class="sf-row"><div class="sf-l"><b>%s</b>%s</div><label class="sf-switch"><input type="checkbox" name="%s" value="1" %s><span></span><i class="screen-reader-text">%s</i></label></div>',
			esc_html( $title ),
			'' !== $desc ? '<span>' . esc_html( $desc ) . '</span>' : '',
			esc_attr( $name ),
			checked( ! empty( $checked ), true, false ),
			esc_html( $title )
		);
	}

	private static function bool( $key ) {
		return ! empty( $_POST[ $key ] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function text( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	public static function do_save_setup() {
		self::guard();
		$old   = SF_Options::all();
		$new   = array();
		$page  = preg_replace( '/\D/', '', self::text( 'fb_page_id', $old['fb_page_id'] ) );
		$token = isset( $_POST['fb_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['fb_token'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$key   = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$new['fb_page_id'] = $page;
		$new['fb_token']   = '' !== $token ? $token : $old['fb_token'];
		$new['api_key']    = '' !== $key ? $key : $old['api_key'];
		$new['fb_handle']  = self::text( 'fb_handle', $old['fb_handle'] );
		$new['mode']       = 'approve' === self::text( 'mode', 'auto' ) ? 'approve' : 'auto';
		$new['api_limit']  = max( 10, min( 100000, (int) self::text( 'api_limit', 100 ) ) );
		$new['max_posts_day'] = max( 1, min( 100, (int) self::text( 'max_posts_day', 15 ) ) );
		foreach ( array( 'quiet_start', 'quiet_end' ) as $k ) {
			$v          = self::text( $k, $old[ $k ] );
			$new[ $k ]  = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ? $v : $old[ $k ];
		}
		$new['noindex'] = self::bool( 'noindex' );
		SF_Options::update( $new );
		SF_Poster::protect_dir();
		$msg = 'Settings saved.';
		if ( '' !== $new['fb_page_id'] && '' !== $new['fb_token'] && ( $new['fb_token'] !== $old['fb_token'] || $new['fb_page_id'] !== $old['fb_page_id'] ) ) {
			$res = SF_Facebook::verify();
			$msg .= is_wp_error( $res ) ? ' Facebook said: ' . $res->get_error_message() : ' Connected to ' . $res['name'] . '.';
			self::done( 'setup', is_wp_error( $res ) ? 'warning' : 'success', $msg );
		}
		self::done( 'setup', 'success', $msg );
	}

	public static function do_save_security() {
		self::guard();
		$new = array();
		foreach ( array_keys( SF_Security::defaults() ) as $k ) {
			$new[ $k ] = self::bool( 'sec_' . $k );
		}
		SF_Options::update( array( 'security' => $new ) );
		SF_Poster::protect_dir();
		self::done( 'security', 'success', 'Security settings saved.' );
	}

	public static function do_test_fb() {
		self::guard();
		$res = SF_Facebook::verify();
		if ( is_wp_error( $res ) ) {
			self::done( 'setup', 'error', 'Facebook: ' . $res->get_error_message() );
		}
		self::done( 'setup', 'success', 'Connected to ' . $res['name'] . '. Nothing was posted.' );
	}

	public static function do_test_api() {
		self::guard();
		$res = SF_API::ping();
		if ( is_wp_error( $res ) ) {
			self::done( 'setup', 'error', 'Cricket API: ' . $res->get_error_message() );
		}
		$s = SF_API::stats();
		self::done( 'setup', 'success', 'The cricket API key works. ' . $s['hits'] . ' of ' . $s['limit'] . ' calls used today.' );
	}

	public static function do_run_now() {
		self::guard();
		if ( ! SF_Options::ready() ) {
			self::done( 'overview', 'warning', 'Finish Setup first: the Facebook Page, the access token and the cricket API key.' );
		}
		SF_Engine::tick( true );
		self::done( 'overview', 'success', 'Checked the matches now.' );
	}

	public static function do_queue_action() {
		self::guard();
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$act = self::text( 'do' );
		$row = SF_DB::get_post( $id );
		if ( ! $row ) {
			self::done( 'queue', 'error', 'That post no longer exists.' );
		}
		if ( 'posted' === $row['status'] ) {
			self::done( 'queue', 'error', 'That post was already sent, so it cannot be changed.' );
		}
		if ( 'approve' === $act || 'retry' === $act ) {
			if ( isset( $_POST['caption'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				SF_DB::update_post( $id, array( 'caption' => sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			SF_Poster::approve( $id );
			$row = SF_DB::get_post( $id );
			$res = SF_Poster::publish_one( $row );
			$msg = array( 'posted' => 'Posted to Facebook.', 'waiting' => 'Approved. It will go out when the posting rules allow.', 'skipped' => 'Skipped by the posting rules.', 'failed' => 'Facebook refused it. See the reason in the list.' );
			self::done( 'queue', 'posted' === $res ? 'success' : ( 'failed' === $res ? 'error' : 'warning' ), $msg[ $res ] );
		}
		if ( 'skip' === $act ) {
			SF_Poster::skip( $id );
			self::done( 'queue', 'success', 'Skipped.' );
		}
		self::done( 'queue', 'error', 'Unknown action.' );
	}

	public static function do_save_engage() {
		self::guard();
		$o    = SF_Options::all();
		$jobs = array();
		foreach ( array_keys( $o['jobs'] ) as $j ) {
			$jobs[ $j ] = self::bool( 'job_' . $j );
		}
		$mil = array();
		foreach ( array_keys( $o['milestones'] ) as $k ) {
			$mil[ $k ] = self::bool( 'ms_' . $k );
		}
		$q = isset( $_POST['questions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['questions'] ) ) : $o['questions']; // phpcs:ignore WordPress.Security.NonceVerification
		SF_Options::update(
			array(
				'jobs'              => $jobs,
				'milestones'        => $mil,
				'questions'         => $q,
				'poll_hours_before' => max( 1, min( 12, (int) self::text( 'poll_hours_before', 2 ) ) ),
				'polls_per_day'     => max( 0, min( 10, (int) self::text( 'polls_per_day', 2 ) ) ),
				'live_cards'        => max( 1, min( 5, (int) self::text( 'live_cards', 2 ) ) ),
				'per_match_hour'    => max( 1, min( 6, (int) self::text( 'per_match_hour', 2 ) ) ),
				'digest'            => self::bool( 'digest' ),
				'digest_every'      => max( 10, min( 240, (int) self::text( 'digest_every', 30 ) ) ),
			)
		);
		self::done( 'engage', 'success', 'Engagement settings saved.' );
	}

	public static function do_create_pinned() {
		self::guard();
		$res = SF_Engine::create_pinned();
		if ( is_wp_error( $res ) ) {
			self::done( 'engage', 'error', $res->get_error_message() );
		}
		self::done( 'engage', 'success', 'The post was created. Open your Page, find it, and choose Pin to top of Page. The plugin will keep its text up to date.' );
	}

	public static function do_search_series() {
		self::guard();
		$q   = self::text( 'q' );
		$res = SF_API::series_search( $q );
		if ( is_wp_error( $res ) ) {
			self::done( 'tournaments', 'error', $res->get_error_message() );
		}
		set_transient( 'sf_series_results_' . get_current_user_id(), array( $q, $res ), 600 );
		wp_safe_redirect( self::url( 'tournaments', array( 'searched' => 1 ) ) );
		exit;
	}

	public static function do_follow() {
		self::guard();
		$id   = self::text( 'series_id' );
		$name = self::text( 'series_name' );
		if ( '' === $id ) {
			self::done( 'tournaments', 'error', 'Add a tournament id.' );
		}
		$f        = SF_Options::get( 'followed', array() );
		$f[ $id ] = '' !== $name ? $name : $id;
		SF_Options::update( array( 'followed' => $f ) );
		self::done( 'tournaments', 'success', 'Following ' . $f[ $id ] . '.' );
	}

	public static function do_unfollow() {
		self::guard();
		$id = self::text( 'series_id' );
		$f  = SF_Options::get( 'followed', array() );
		unset( $f[ $id ] );
		SF_Options::update( array( 'followed' => $f ) );
		self::done( 'tournaments', 'success', 'Removed.' );
	}

	public static function do_save_teams() {
		self::guard();
		$keys = isset( $_POST['logo_key'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['logo_key'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$ids  = isset( $_POST['logo_id'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['logo_id'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$map  = array();
		foreach ( $keys as $i => $k ) {
			if ( ! empty( $ids[ $i ] ) && wp_attachment_is_image( $ids[ $i ] ) ) {
				$map[ strtolower( $k ) ] = $ids[ $i ];
			}
		}
		SF_Options::update( array( 'team_logos' => $map ) );
		self::done( 'teams', 'success', 'Team pictures saved.' );
	}

	public static function do_manual_post() {
		self::guard();
		wp_raise_memory_limit( 'image' );
		$caption = isset( $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$src     = '';
		$att     = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $att && wp_attachment_is_image( $att ) ) {
			$src = get_attached_file( $att );
		} elseif ( ! empty( $_FILES['image']['name'] ) ) {
			$up = wp_handle_upload(
				$_FILES['image'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				array(
					'test_form' => false,
					'mimes'     => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ),
				)
			);
			if ( isset( $up['error'] ) ) {
				self::done( 'manual', 'error', $up['error'] );
			}
			$src = $up['file'];
		}
		if ( '' === $src && '' === $caption ) {
			self::done( 'manual', 'error', 'Add a picture or some text.' );
		}
		if ( self::bool( 'add_question' ) ) {
			$lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) SF_Options::get( 'questions', '' ) ) ) ) );
			if ( $lines ) {
				$caption = trim( $caption . "\n" . $lines[ array_rand( $lines ) ] );
			}
		}
		$rel = '';
		if ( '' !== $src ) {
			if ( filesize( $src ) > 8 * MB_IN_BYTES ) {
				self::done( 'manual', 'error', 'That picture is larger than 8 MB. Use a smaller file.' );
			}
			$sub   = 'manual/' . gmdate( 'Y-m' ) . '/';
			$name  = 'post-' . wp_generate_password( 12, false ) . '.' . SF_Cards::output_extension( $src );
			$dest  = SF_Poster::dir()['path'] . $sub . $name;
			$pos   = in_array( self::text( 'wm_pos' ), array( 'tl', 'tr', 'bl', 'br' ), true ) ? self::text( 'wm_pos' ) : 'br';
			SF_Poster::protect_dir();
			$cards = new SF_Cards();
			$res   = $cards->clean_image( $src, $dest, (bool) self::bool( 'watermark' ), $pos );
			if ( is_wp_error( $res ) ) {
				self::done( 'manual', 'error', $res->get_error_message() );
			}
			$rel = $sub . $name;
		}
		$when = null;
		if ( 'later' === self::text( 'when' ) && '' !== self::text( 'when_at' ) ) {
			$dt = date_create( self::text( 'when_at' ), wp_timezone() );
			if ( $dt && $dt->getTimestamp() > time() ) {
				$when = gmdate( 'Y-m-d H:i:s', $dt->getTimestamp() );
			}
		}
		$id = SF_DB::add_post(
			array(
				'type'         => 'manual',
				'dedupe'       => 'manual:' . wp_generate_password( 14, false ),
				'status'       => 'approved',
				'caption'      => $caption,
				'image'        => $rel,
				'scheduled_at' => $when,
			)
		);
		if ( ! $id ) {
			self::done( 'manual', 'error', 'Could not add the post.' );
		}
		if ( null !== $when ) {
			self::done( 'manual', 'success', 'Scheduled for ' . wp_date( 'j M, H:i', strtotime( $when . ' UTC' ) ) . '.' );
		}
		$row = SF_DB::get_post( $id );
		$res = SF_Poster::publish_one( $row );
		if ( 'posted' === $res ) {
			self::done( 'manual', 'success', 'Posted to Facebook.' );
		}
		$row = SF_DB::get_post( $id );
		self::done( 'manual', 'error', 'Facebook did not accept it: ' . ( ! empty( $row['error'] ) ? $row['error'] : 'unknown reason' ) . ' It will retry a few times.' );
	}

	/** Draw a sample card and send it as an image. */
	public static function do_sample() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( 'Not allowed', 403 );
		}
		check_admin_referer( 'sf_sample' );
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'live';
		$all  = SF_Samples::all();
		if ( ! isset( $all[ $type ] ) ) {
			wp_die( 'Unknown card', 404 );
		}
		wp_raise_memory_limit( 'image' );
		$tmp = wp_tempnam( 'sf-sample.png' );
		$cards = new SF_Cards();
		$res   = $cards->render( $type, $all[ $type ], $tmp );
		if ( is_wp_error( $res ) ) {
			wp_die( esc_html( $res->get_error_message() ) );
		}
		nocache_headers();
		header( 'Content-Type: image/png' );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		exit;
	}
}
