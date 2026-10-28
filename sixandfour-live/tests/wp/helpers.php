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
}
