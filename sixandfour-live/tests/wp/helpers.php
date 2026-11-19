<?php
/** Small helpers for the browser test. Usage: php run.php helpers.php <command> [args] */
global $wpdb;
$cmd = isset( $argv[2] ) ? $argv[2] : '';
switch ( $cmd ) {
	case 'clear-locks':
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%sf_login_%'" );
		echo "ok\n";
		break;
	case 'subscriber':
		$login = 'fan1';
		if ( ! get_user_by( 'login', $login ) ) {
			wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'Fan#Pass12345', 'role' => 'subscriber', 'user_email' => 'fan1@example.test' ) );
		}
		echo "ok\n";
		break;
	case 'raw-option':
		echo wp_json_encode( get_option( SF_Options::KEY ) ), "\n";
		break;
	case 'reset':
		$wpdb->query( 'DELETE FROM ' . SF_DB::posts() );
		$wpdb->query( 'DELETE FROM ' . SF_DB::results() );
		foreach ( array( 'sf_options', 'sf_state', 'sf_log', 'sf_api_stats', 'sf_fb_status', 'sf_next_check', 'sf_last_digest', 'sf_known_teams', 'sf_last_matches' ) as $o ) {
			delete_option( $o );
		}
		delete_transient( 'sf_current_matches' );
		echo "ok\n";
		break;
	case 'posts':
		echo wp_json_encode( $wpdb->get_results( 'SELECT id,type,status,image,error FROM ' . SF_DB::posts() . ' ORDER BY id', ARRAY_A ) ), "\n";
		break;
	case 'uploads-dir':
		echo SF_Poster::dir()['path'], "\n";
		break;
	case 'mobile-reset':
		SF_Options::update( array( 'passwordless_only' => 0, 'magic_link' => 1, 'paused' => 0 ) );
		delete_user_meta( 1, 'sf_passkeys' );
		delete_user_meta( 1, 'sf_magic' );
		delete_user_meta( 1, 'sf_magic_sent' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%sf_%'" );
		@unlink( WP_CONTENT_DIR . '/mail-capture.json' );
		echo "ok\n";
		break;
	case 'manual-set':
		SF_Options::update( array( 'manual_enabled' => (int) $argv[3] ) );
		echo "ok\n";
		break;
	case 'option':
		echo wp_json_encode( SF_Options::get( $argv[3], null ) ), "\n";
		break;
	case 'passkey-count':
		echo count( SF_Passwordless::passkeys( 1 ) ), "\n";
		break;
	case 'add-pending':
		SF_DB::add_post( array( 'type' => 'toss', 'dedupe' => 'mob' . time(), 'status' => 'pending', 'caption' => 'TOSS | mobile test', 'image' => '' ) );
		echo "ok\n";
		break;
	case 'video-queue':
		SF_Options::update( array( 'fb_page_id' => '123456', 'fb_token' => 'EAAB1234567890SECRETTOKENVALUE', 'api_key' => 'k', 'post_mode' => 'video', 'video_engine' => 'browser', 'mode' => 'auto', 'paused' => 0, 'max_posts_day' => 60 ) );
		$wpdb->query( 'DELETE FROM ' . SF_DB::posts() );
		$payload = json_decode( file_get_contents( getenv( 'SF_VIDEO_JOB' ) ), true );
		echo SF_Poster::queue_video( 'browser:1', $payload, "RESULT | Asia Cup 2026: England won by 5 wickets.\n#CricketSport #Cricket", array( 'match_id' => 'b1' ) ), "\n";
		break;
	case 'video-state':
		$row = $wpdb->get_row( 'SELECT status, error, payload, fb_post_id FROM ' . SF_DB::posts() . " WHERE type = 'video' ORDER BY id DESC LIMIT 1", ARRAY_A );
		$pl  = json_decode( (string) $row['payload'], true );
		echo wp_json_encode( array( 'status' => $row['status'], 'error' => $row['error'], 'file' => isset( $pl['_file'] ) ? $pl['_file'] : '', 'url' => isset( $pl['_file'] ) ? SF_VideoMaker::file_url( $pl['_file'] ) : '', 'fb' => $row['fb_post_id'] ) ), "\n";
		break;
	case 'video-publish':
		$wpdb->query( 'UPDATE ' . SF_DB::posts() . " SET scheduled_at = '2000-01-01 00:00:00' WHERE type = 'video'" );
		SF_Poster::publish_due();
		echo "ok\n";
		break;
	case 'video-reset':
		SF_Options::update( array( 'post_mode' => 'cards' ) );
		echo "ok\n";
		break;
	case 'console-url':
		$page = (int) SF_Options::get( 'home_page', 0 );
		if ( ! $page || 'page' !== get_post_type( $page ) ) {
			$page = wp_insert_post( array( 'post_title' => 'Cricket Sport', 'post_name' => 'cricket-sport-console', 'post_content' => '[sixandfour_admin]', 'post_status' => 'publish', 'post_type' => 'page' ) );
			SF_Options::update( array( 'home_page' => $page ) );
		}
		echo get_permalink( $page ), "\n";
		break;
}
