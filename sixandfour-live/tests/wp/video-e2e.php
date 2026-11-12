<?php
/**
 * Real WordPress + the real video render service + the fake Facebook.
 * Needs the service on 127.0.0.1:8200 (API_KEY=testkey) and the mock servers. Run through tests/wp/run.php.
 */
if ( ! defined( 'SF_TEST_WP' ) ) {
	exit( "Run this through tests/wp/run.php\n" );
}
$fail = 0;
function ok( $c, $l ) {
	global $fail;
	echo ( $c ? '  PASS  ' : '  FAIL  ' ) . $l . "\n";
	if ( ! $c ) {
		$fail++;
	}
}
global $wpdb;
$wpdb->query( 'DELETE FROM ' . SF_DB::posts() );
SF_Options::update( array( 'fb_page_id' => '123456', 'fb_token' => 'EAAB1234567890SECRETTOKENVALUE', 'api_key' => 'k', 'post_mode' => 'video', 'video_url' => 'http://127.0.0.1:8200', 'video_key' => 'testkey', 'mode' => 'auto', 'paused' => 0, 'video_voice' => 0, 'max_posts_day' => 60 ) );
echo "Video service\n";
ok( true === SF_Video::ping(), 'the service answers the health check' );
$payload = json_decode( file_get_contents( getenv( 'SF_VIDEO_JOB' ) ), true );
$id = SF_Poster::queue_video( 'e2e:1', $payload, "RESULT | Asia Cup 2026: England won by 5 wickets.\n#CricketSport #Cricket", array( 'match_id' => 'e2e' ) );
ok( $id > 0, 'a video post is queued' );
$start = time();
$status = '';
while ( time() - $start < 150 ) {
	$wpdb->query( "UPDATE {$wpdb->prefix}sf_posts SET scheduled_at = '2000-01-01 00:00:00' WHERE id = " . (int) $id );
	SF_Poster::publish_due();
	$row = SF_DB::get_post( $id );
	$status = $row['status'];
	if ( in_array( $status, array( 'posted', 'failed' ), true ) ) {
		break;
	}
	sleep( 4 );
}
ok( 'posted' === $status, 'the video was made and posted (' . $status . ', ' . ( time() - $start ) . ' s)' . ( 'failed' === $status ? ': ' . $row['error'] : '' ) );
$state = json_decode( file_get_contents( 'http://127.0.0.1:8100/__state' ), true );
$vid   = null;
foreach ( $state['fb'] as $f ) {
	if ( 'video' === $f['kind'] ) {
		$vid = $f;
	}
}
ok( $vid && 0 === strpos( $vid['file_url'], 'http://127.0.0.1:8200/videos/' ), 'Facebook was given the video address' );
ok( $vid && false !== strpos( $vid['description'], 'RESULT | Asia Cup 2026' ) && $vid['tokenInBody'], 'with the caption, and the token in the request body' );
if ( $vid ) {
	$bytes = file_get_contents( $vid['file_url'] );
	file_put_contents( '/tmp/e2e-video.mp4', $bytes );
	ok( strlen( $bytes ) > 100000 && 'ftyp' === substr( $bytes, 4, 4 ), 'the address serves an MP4 file (' . round( strlen( $bytes ) / 1024 ) . ' KB)' );
}
echo "\n", $fail ? "$fail FAILED\n" : "All checks passed\n";
exit( $fail ? 1 : 0 );
