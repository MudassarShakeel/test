<?php
/**
 * Runs inside a real WordPress (SQLite for the database). See tests/wp/README.md.
 * It checks activation, the database, encryption, the hardening hooks and the engine with real WP functions.
 */
if ( ! defined( 'SF_TEST_WP' ) ) {
	exit( "Run this through tests/wp/run.php\n" );
}
$fail = 0;
SF_API::$reuse = 0;
function ok( $c, $l ) {
	global $fail;
	echo ( $c ? '  PASS  ' : '  FAIL  ' ) . $l . "\n";
	if ( ! $c ) {
		$fail++;
	}
}
global $wpdb;

$wpdb->query( 'DELETE FROM ' . SF_DB::posts() );
$wpdb->query( 'DELETE FROM ' . SF_DB::results() );
delete_option( 'sf_state' );
delete_option( 'sf_log' );
delete_option( 'sf_api_stats' );
echo "1. Activation\n";
deactivate_plugins( 'sixandfour-live/sixandfour-live.php' );
foreach ( array( 'sf_tick', 'sf_publish', 'sf_daily', 'sf_weekly', 'sf_monthly' ) as $h ) {
	wp_clear_scheduled_hook( $h );
}
$r = activate_plugin( 'sixandfour-live/sixandfour-live.php' );
ok( ! is_wp_error( $r ), 'plugin activates without errors' );
foreach ( array( 'sf_tick', 'sf_publish', 'sf_daily', 'sf_weekly', 'sf_monthly' ) as $h ) {
	ok( (bool) wp_next_scheduled( $h ), "$h is scheduled straight after activation" );
}
ok( is_file( SF_Poster::dir()['path'] . '.htaccess' ) && is_file( SF_Poster::dir()['path'] . 'index.php' ), 'picture folder has index.php and .htaccess' );
ok( false !== strpos( file_get_contents( SF_Poster::dir()['path'] . '.htaccess' ), 'Options -Indexes' ), '.htaccess stops listings and PHP' );

echo "2. Database\n";
ok( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SF_DB::posts() ) >= 0, 'posts table exists' );
$id1 = SF_DB::add_post( array( 'type' => 'test', 'dedupe' => 'dup-1', 'caption' => 'a' ) );
$id2 = SF_DB::add_post( array( 'type' => 'test', 'dedupe' => 'dup-1', 'caption' => 'b' ) );
ok( $id1 > 0 && 0 === $id2, 'the same event cannot be queued twice' );
ok( 'pending' === SF_DB::get_post( $id1 )['status'], 'a post can be read back' );

echo "3. Secrets\n";
SF_Options::update( array( 'fb_token' => 'EAAB1234567890SECRETTOKENVALUE', 'api_key' => 'cricket-key-123456', 'fb_page_id' => '123456', 'mode' => 'auto' ) );
$raw = get_option( SF_Options::KEY );
$dump = maybe_serialize( $raw );
ok( 0 === strpos( $raw['fb_token'], 'sf1:' ) && 0 === strpos( $raw['api_key'], 'sf1:' ), 'token and API key are encrypted in wp_options' );
ok( false === strpos( $dump, 'SECRETTOKENVALUE' ) && false === strpos( $dump, 'cricket-key-123456' ), 'no plain text secret in the saved option' );
ok( 'EAAB1234567890SECRETTOKENVALUE' === SF_Options::get( 'fb_token' ), 'the plugin reads the token back correctly' );
$legacy = $raw;
$legacy['fb_token'] = 'PLAINLEGACYTOKEN12345';
update_option( SF_Options::KEY, $legacy );
SF_Options::protect();
ok( 0 === strpos( get_option( SF_Options::KEY )['fb_token'], 'sf1:' ) && 'PLAINLEGACYTOKEN12345' === SF_Options::get( 'fb_token' ), 'a plain text token from an older version is encrypted and still works' );
SF_Options::update( array( 'fb_token' => 'EAAB1234567890SECRETTOKENVALUE' ) );
$tampered = get_option( SF_Options::KEY );
$tampered['fb_token'] = substr( $tampered['fb_token'], 0, -4 ) . 'AAAA';
update_option( SF_Options::KEY, $tampered );
ok( '' === SF_Options::get( 'fb_token' ), 'a damaged secret reads as empty, it does not crash or leak' );
SF_Options::update( array( 'fb_token' => 'EAAB1234567890SECRETTOKENVALUE' ) );
SF_Log::add( 'Failed: https://x.test/?apikey=cricket-key-123456&access_token=EAAB1234567890SECRETTOKENVALUE' );
$line = SF_Log::recent( 1 )[0][2];
ok( false === strpos( $line, 'cricket-key' ) && false === strpos( $line, 'SECRETTOKEN' ), 'secrets are scrubbed from the activity log (' . $line . ')' );

echo "4. Hardening hooks\n";
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
delete_transient( 'sf_login_fail_' . md5( '203.0.113.9' ) );
delete_transient( 'sf_login_lock_' . md5( '203.0.113.9' ) );
SF_Options::update( array( 'security' => array() ) );
$admin = get_user_by( 'login', 'admin' );
for ( $i = 0; $i < 5; $i++ ) {
	$u = wp_authenticate( 'admin', 'wrong-password' );
}
$locked = wp_authenticate( 'admin', 'Admin#Pass12345' );
ok( is_wp_error( $locked ) && 'sf_locked' === $locked->get_error_code(), 'after 5 wrong passwords even the right one is refused: ' . ( is_wp_error( $locked ) ? $locked->get_error_message() : 'allowed' ) );
SF_Security::clear_failures();
$good = wp_authenticate( 'admin', 'Admin#Pass12345' );
ok( $good instanceof WP_User, 'a correct log-in works again once cleared' );
wp_set_current_user( $admin->ID );
ok( ! current_user_can( 'edit_plugins' ) && ! current_user_can( 'edit_themes' ), 'the plugin and theme file editor is blocked for administrators' );
ok( false === apply_filters( 'xmlrpc_enabled', true ), 'XML-RPC is turned off' );
ok( '' === apply_filters( 'the_generator', '<meta name="generator" content="WordPress">', 'html' ), 'the WordPress version tag is empty' );
wp_set_current_user( 0 );
$eps = apply_filters( 'rest_endpoints', array( '/wp/v2/users' => array(), '/wp/v2/posts' => array() ) );
ok( ! isset( $eps['/wp/v2/users'] ) && isset( $eps['/wp/v2/posts'] ), 'the public users list is hidden from visitors but posts stay' );
wp_set_current_user( $admin->ID );
$eps = apply_filters( 'rest_endpoints', array( '/wp/v2/users' => array() ) );
ok( isset( $eps['/wp/v2/users'] ), 'logged-in users still see the users list' );

echo "5. Capability and the sf_capability filter\n";
if ( ! class_exists( 'SF_Admin' ) ) {
	require SF_DIR . 'includes/class-sf-admin.php';
}
$sub = wp_insert_user( array( 'user_login' => 'fan' . wp_rand( 100, 999 ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $sub );
ok( ! current_user_can( SF_Admin::cap() ), 'a subscriber does not have the plugin capability' );
wp_set_current_user( $admin->ID );
ok( current_user_can( SF_Admin::cap() ), 'an administrator does' );
wp_delete_user( $sub );

echo "6. Engine with real WordPress\n";
$world = array( 'matches' => array(), 'cards' => array(), 'calls' => array() );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$world ) {
	$world['calls'][] = array( $args['method'], $url, $args );
	$resp = function ( $data, $code = 200 ) {
		return array( 'headers' => array(), 'body' => wp_json_encode( $data ), 'response' => array( 'code' => $code, 'message' => 'x' ), 'cookies' => array(), 'filename' => null );
	};
	if ( false !== strpos( $url, '/cricket/' ) ) {
		$info = array( 'hitsToday' => count( $world['calls'] ), 'hitsLimit' => 100 );
		if ( false !== strpos( $url, 'currentMatches' ) ) {
			return $resp( array( 'status' => 'success', 'data' => $world['matches'], 'info' => $info ) );
		}
		return $resp( array( 'status' => 'success', 'data' => array(), 'info' => $info ) );
	}
	if ( false !== strpos( $url, '/graph/' ) ) {
		if ( 'GET' === $args['method'] ) {
			return $resp( array( 'name' => 'Six & Four Live', 'fan_count' => 9800 ) );
		}
		if ( preg_match( '#/photos$#', $url ) ) {
			$world['photo'] = $args;
			return $resp( array( 'id' => 'P1', 'post_id' => '1_1' ) );
		}
		return $resp( array( 'id' => '1_feed' ) );
	}
	return $pre;
}, 10, 3 );
SF_Options::update( array( 'quiet_start' => '00:00', 'quiet_end' => '00:00', 'followed' => array() ) );
$now = time();
$world['matches'] = array( array(
	'id' => 'wm1', 'name' => 'England vs Australia, 14th Match', 'matchType' => 't20', 'status' => 'Australia won the toss and elected to bat first', 'venue' => 'Harbour Oval, Harbour',
	'dateTimeGMT' => gmdate( 'Y-m-d\TH:i:s', $now + 1800 ), 'teams' => array( 'England', 'Australia' ),
	'teamInfo' => array( array( 'name' => 'England', 'shortname' => 'ENG' ), array( 'name' => 'Australia', 'shortname' => 'AUS' ) ),
	'score' => array(), 'series_id' => 's1', 'matchStarted' => false, 'matchEnded' => false,
) );
delete_transient( 'sf_current_matches' );
update_option( 'sf_next_check', 0 );
SF_Engine::tick( true );
$rows = $wpdb->get_results( 'SELECT type, status, image FROM ' . SF_DB::posts() . " WHERE type IN ('toss','poll')", ARRAY_A );
ok( 2 === count( $rows ), 'toss and poll posts were queued in the real database' );
ok( 2 === count( array_filter( $rows, function ( $r ) {
	return 'posted' === $r['status'];
} ) ), 'both were posted (to the fake Facebook)' );
ok( is_file( SF_Poster::dir()['path'] . $rows[0]['image'] ), 'the card file was written to the uploads folder' );
$size = getimagesize( SF_Poster::dir()['path'] . $rows[0]['image'] );
ok( $size && 1200 === $size[0] && 630 === $size[1], 'the toss card is 1200 x 630' );
$photo = isset( $world['photo'] ) ? $world['photo'] : null;
ok( $photo && false !== strpos( $photo['body'], "\x89PNG" ) && false !== strpos( $photo['body'], 'name="caption"' ), 'the photo request is multipart with the PNG and a caption' );
foreach ( $world['calls'] as $c ) {
	if ( 'POST' === $c[0] ) {
		ok( false === strpos( $c[1], 'access_token' ), 'POST URL carries no token: ' . preg_replace( '#https?://[^/]+#', '', $c[1] ) );
		break;
	}
}
SF_Engine::tick( true );
ok( 2 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SF_DB::posts() . " WHERE type IN ('toss','poll')" ), 'a second tick makes no duplicates' );
ok( SF_API::stats()['hits'] > 0, 'API usage is counted: ' . SF_API::stats()['hits'] . ' calls' );

echo "7. Daily limit and quiet hours\n";
SF_Options::update( array( 'quiet_start' => '00:00', 'quiet_end' => '23:59' ) );
ok( SF_Poster::in_quiet_hours(), 'quiet hours block posting' );
SF_Options::update( array( 'quiet_start' => '00:00', 'quiet_end' => '00:00' ) );

echo "\n", $fail ? "$fail FAILED\n" : "All checks passed\n";
exit( $fail ? 1 : 0 );
