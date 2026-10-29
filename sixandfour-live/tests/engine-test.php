<?php
/**
 * End-to-end test of the engine with fake CricketData and Facebook responses.
 * Run: php tests/engine-test.php
 */

require __DIR__ . '/wp-stubs.php';

$fail = 0;
SF_API::$reuse = 0;
SF_Engine::$catch_up = false;
function ok( $cond, $label ) {
	global $fail;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $label . "\n";
	if ( ! $cond ) {
		$fail++;
	}
}
function iso( $ts ) {
	return gmdate( 'Y-m-d\TH:i:s', $ts );
}
function match_json( $id, $a, $b, $start, $started, $ended, $status, $score = array(), $series = 's1', $type = 't20' ) {
	return array(
		'id' => $id, 'name' => "$a vs $b, 14th Match", 'matchType' => $type, 'status' => $status, 'venue' => 'Harbour Oval, Harbour',
		'date' => gmdate( 'Y-m-d', $start ), 'dateTimeGMT' => iso( $start ),
		'teams' => array( $a, $b ),
		'teamInfo' => array( array( 'name' => $a, 'shortname' => strtoupper( substr( $a, 0, 3 ) ) ), array( 'name' => $b, 'shortname' => strtoupper( substr( $b, 0, 3 ) ) ) ),
		'score' => $score, 'series_id' => $series, 'matchStarted' => $started, 'matchEnded' => $ended,
	);
}

// ---- Fake world -----------------------------------------------------------
$world = array( 'matches' => array(), 'hits' => 0, 'scorecards' => array(), 'points' => array(), 'fb_posts' => array(), 'fb_edits' => array() );
$GLOBALS['sf_http'] = function ( $method, $url, $args ) use ( &$world ) {
	if ( false !== strpos( $url, 'api.cricapi.com' ) ) {
		$world['hits']++;
		$info = array( 'hitsToday' => $world['hits'], 'hitsLimit' => 100 );
		if ( false !== strpos( $url, '/currentMatches' ) ) {
			return sf_json( array( 'status' => 'success', 'data' => $world['matches'], 'info' => $info ) );
		}
		if ( false !== strpos( $url, '/match_scorecard' ) ) {
			parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
			return sf_json( array( 'status' => 'success', 'data' => isset( $world['scorecards'][ $q['id'] ] ) ? $world['scorecards'][ $q['id'] ] : array(), 'info' => $info ) );
		}
		if ( false !== strpos( $url, '/series_points' ) ) {
			return sf_json( array( 'status' => 'success', 'data' => $world['points'], 'info' => $info ) );
		}
		return sf_json( array( 'status' => 'success', 'data' => array(), 'info' => $info ) );
	}
	if ( false !== strpos( $url, 'graph.facebook.com' ) ) {
		if ( 'GET' === $method ) {
			return sf_json( array( 'name' => 'Cricket Sport', 'username' => 'cricketsportinfo', 'fan_count' => 9800, 'category' => 'Sports' ) );
		}
		if ( preg_match( '#/photos$#', $url ) ) {
			$world['fb_posts'][] = array( 'photo', strlen( $args['body'] ) );
			return sf_json( array( 'id' => 'P' . count( $world['fb_posts'] ), 'post_id' => '1_' . count( $world['fb_posts'] ) ) );
		}
		if ( preg_match( '#/feed$#', $url ) ) {
			$world['fb_posts'][] = array( 'feed', $args['body']['message'] );
			return sf_json( array( 'id' => '1_feed' . count( $world['fb_posts'] ) ) );
		}
		$world['fb_edits'][] = array( $url, $args['body']['message'] );
		return sf_json( array( 'success' => true ) );
	}
	return array( 'code' => 404, 'body' => '' );
};

SF_Options::update( array(
	'api_key' => 'k', 'fb_page_id' => '123', 'fb_token' => 'tok', 'mode' => 'auto',
	'max_posts_day' => 50, 'per_match_hour' => 5, 'live_cards' => 2,
	'followed' => array( 's1' => 'Harbour Cup 2026' ), 'fb_handle' => 'facebook.com/cricketsportinfo',
	'cover' => 'top', 'digest' => 1, 'polls_per_day' => 10, 'per_match_hour' => 5,
	'jobs' => array_fill_keys( array( 'fixtures', 'poll', 'toss', 'innings', 'result', 'summary', 'live', 'wicket', 'stumps', 'potm', 'milestones', 'table', 'ranking', 'pinned' ), 1 ),
) );
function posts( $type = null ) {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM wp_sf_posts ORDER BY id' );
	return $type ? array_values( array_filter( $rows, function ( $r ) use ( $type ) {
		return $r['type'] === $type;
	} ) ) : $rows;
}
function force_due() {
	global $wpdb;
	$wpdb->query( "UPDATE wp_sf_posts SET scheduled_at = '2000-01-01 00:00:00' WHERE scheduled_at IS NOT NULL" );
}
function tick() {
	update_option( 'sf_next_check', 0 );
	SF_API::current_matches( true );
	SF_Engine::tick( true );
}

echo "1. Facebook and API checks\n";
$fb = SF_Facebook::verify();
ok( ! is_wp_error( $fb ) && 'Cricket Sport' === $fb['name'], 'verify() reads the Page name' );
ok( SF_Options::ready(), 'plugin is ready with keys' );

echo "2. Upcoming match: toss post and Pick a side post\n";
$now = time();
$world['matches'] = array( match_json( 'm1', 'England', 'Australia', $now + 1800, false, false, 'Australia won the toss and elected to bat first' ) );
tick();
ok( 1 === count( posts( 'toss' ) ), 'one toss post queued' );
ok( 1 === count( posts( 'poll' ) ), 'one poll post queued' );
ok( 'posted' === posts( 'toss' )[0]['status'] && 'posted' === posts( 'poll' )[0]['status'], 'both were sent to Facebook' );
tick();
ok( 1 === count( posts( 'toss' ) ) && 1 === count( posts( 'poll' ) ), 'ticking again makes no duplicates' );
ok( is_file( SF_Poster::dir()['path'] . posts( 'toss' )[0]['image'] ), 'toss card file exists' );

echo "3. Live match: wicket and innings break\n";
$sc1 = array( array( 'r' => 80, 'w' => 2, 'o' => 10.0, 'inning' => 'Australia Inning 1' ) );
$world['matches'] = array( match_json( 'm1', 'England', 'Australia', $now - 3600, true, false, 'Match in progress', $sc1 ) );
tick(); // first sight: no posts.
$before = count( posts( 'live' ) );
$sc1[0] = array( 'r' => 95, 'w' => 3, 'o' => 12.1, 'inning' => 'Australia Inning 1' );
$world['matches'][0]['score'] = $sc1;
tick();
ok( count( posts( 'live' ) ) === $before + 1, 'a wicket card was queued' );
$sc2 = array( array( 'r' => 168, 'w' => 7, 'o' => 20.0, 'inning' => 'Australia Inning 1' ), array( 'r' => 12, 'w' => 0, 'o' => 1.4, 'inning' => 'England Inning 1' ) );
$world['matches'][0]['score'] = $sc2;
tick();
ok( count( posts( 'live' ) ) === $before + 1 && 1 === count( posts( 'innings' ) ), 'an innings break card was queued, as its own post type' );
$live = posts( 'innings' );
$last = end( $live );
ok( 0 === strpos( $last['caption'], 'INNINGS BREAK' ), 'innings break caption: ' . strtok( $last['caption'], "\n" ) );

echo "4. Match ends: result, summary, top performer, century, five-for\n";
$sc3 = array( array( 'r' => 168, 'w' => 7, 'o' => 20.0, 'inning' => 'Australia Inning 1' ), array( 'r' => 171, 'w' => 4, 'o' => 18.3, 'inning' => 'England Inning 1' ) );
$bat = function ( $id, $name, $r, $b, $f, $s, $out = true ) {
	return array( 'batsman' => array( 'id' => $id, 'name' => $name ), 'dismissal' => $out ? 'b Someone' : 'not out', 'r' => $r, 'b' => $b, '4s' => $f, '6s' => $s, 'sr' => round( $r * 100 / $b, 2 ) );
};
$bowl = function ( $id, $name, $o, $m, $r, $w ) {
	return array( 'bowler' => array( 'id' => $id, 'name' => $name ), 'o' => $o, 'm' => $m, 'r' => $r, 'w' => $w, 'eco' => round( $r / $o, 2 ) );
};
$world['scorecards']['m1'] = array(
	'id' => 'm1', 'status' => 'England won by 6 wkts',
	'scorecard' => array(
		array( 'inning' => 'Australia Inning 1', 'batting' => array( $bat( 'a1', 'Jack Merriman', 61, 38, 6, 2 ), $bat( 'a2', 'Noah Pryce', 34, 25, 3, 1 ) ), 'bowling' => array( $bowl( 'e1', 'Sam Whitlock', 4, 1, 24, 3 ), $bowl( 'e2', 'Rhys Colby', 4, 0, 33, 1 ) ) ),
		array( 'inning' => 'England Inning 1', 'batting' => array( $bat( 'e3', 'Oliver Hartley', 100, 52, 8, 5, false ), $bat( 'e4', 'Dan Rowe', 40, 30, 4, 1 ) ), 'bowling' => array( $bowl( 'a3', 'Liam Corbett', 4, 0, 18, 5 ), $bowl( 'a4', 'Tom Ash', 3.3, 0, 41, 1 ) ) ),
	),
);
$world['matches'] = array( match_json( 'm1', 'England', 'Australia', $now - 10800, true, true, 'England won by 6 wkts', $sc3 ) );
tick();
ok( 1 === count( posts( 'result' ) ), 'result queued' );
ok( 1 === count( posts( 'summary' ) ), 'summary queued' );
ok( 1 === count( posts( 'potm' ) ), 'top performer queued' );
ok( 1 === count( posts( 'century' ) ), 'century queued (Oliver Hartley 100*)' );
ok( 1 === count( posts( 'fivefor' ) ), 'five-wicket haul queued (Liam Corbett 5/18)' );
ok( 'posted' === posts( 'result' )[0]['status'], 'result posted immediately' );
ok( 'approved' === posts( 'summary' )[0]['status'] && null !== posts( 'summary' )[0]['scheduled_at'], 'summary waits for its delay' );
force_due();
SF_Poster::publish_due();
SF_Poster::publish_due();
ok( 'posted' === posts( 'summary' )[0]['status'] && 'posted' === posts( 'potm' )[0]['status'], 'summary and top performer post after the delay' );
echo '  caption: ', str_replace( "\n", ' | ', posts( 'summary' )[0]['caption'] ), "\n";
tick();
ok( 1 === count( posts( 'result' ) ) && 1 === count( posts( 'summary' ) ), 'no duplicates on the next tick' );
ok( 1 === count( $GLOBALS['wpdb']->get_results( 'SELECT * FROM wp_sf_results' ) ), 'result stored for the monthly ranking' );

echo "5. Busy day: scoreboard digest\n";
$m = array();
foreach ( array( array( 'x1', 'India', 'Pakistan' ), array( 'x2', 'New Zealand', 'South Africa' ), array( 'x3', 'Sri Lanka', 'Bangladesh' ), array( 'x4', 'Desert Riders', 'City Falcons' ) ) as $i => $t ) {
	$m[] = match_json( $t[0], $t[1], $t[2], $now - 1800, true, false, 'Match in progress', array( array( 'r' => 50 + $i, 'w' => 1, 'o' => 6.0, 'inning' => $t[1] . ' Inning 1' ) ) );
}
$world['matches'] = $m;
tick();
ok( 1 === count( posts( 'digest' ) ), 'scoreboard digest queued for 4 live matches' );
tick();
ok( 1 === count( posts( 'digest' ) ), 'digest respects the 30 minute gap' );

echo "6. Daily, weekly and monthly jobs\n";
$world['matches'] = array(
	match_json( 'd1', 'England', 'Australia', $now + 600, false, false, 'Match not started' ),
	match_json( 'd2', 'India', 'Pakistan', $now + 1200, false, false, 'Match not started' ),
);
tick();
SF_Engine::daily();
ok( 1 === count( posts( 'fixtures' ) ), 'fixtures card queued' );
$world['points'] = array(
	array( 'teamname' => 'England', 'shortname' => 'ENG', 'matches' => 4, 'wins' => 3, 'loss' => 1, 'nr' => 0, 'points' => 6 ),
	array( 'teamname' => 'Australia', 'shortname' => 'AUS', 'matches' => 4, 'wins' => 3, 'loss' => 1, 'nr' => 0, 'points' => 6 ),
	array( 'teamname' => 'India', 'shortname' => 'IND', 'matches' => 4, 'wins' => 2, 'loss' => 2, 'nr' => 0, 'points' => 4 ),
);
SF_Engine::weekly();
ok( 1 === count( posts( 'table' ) ), 'points table queued' );
$rows = array();
$teams = array( 'England', 'Australia', 'India', 'New Zealand' );
for ( $i = 0; $i < 8; $i++ ) {
	$a = $teams[ $i % 4 ];
	$b = $teams[ ( $i + 1 ) % 4 ];
	$rows[] = array( 'team_a' => $a, 'team_b' => $b, 'winner' => $a, 'result_type' => 'win' );
}
$rk = SF_Engine::build_ranking( $rows, 'October 2026' );
ok( is_array( $rk ) && 'October 2026, from matches tracked on this Page' === $rk[0]['meta'], 'monthly ranking built from results' );
$cards = new SF_Cards();
$res = $cards->render( 'ranking', $rk[0], sys_get_temp_dir() . '/sf-ranking-test.png' );
ok( ! is_wp_error( $res ) && is_file( $res ), 'ranking card renders' );

echo "7. Pinned post\n";
$id = SF_Engine::create_pinned();
ok( ! is_wp_error( $id ) && SF_Options::get( 'pinned_post_id' ) === $id, 'pinned post created and its id saved' );
$world['matches'][0]['status'] = 'Match in progress';
$world['matches'][0]['matchStarted'] = true;
tick();
ok( count( $world['fb_edits'] ) >= 1, 'pinned post text was edited after a change' );
echo '  edit text: ', str_replace( "\n", ' | ', end( $world['fb_edits'] )[1] ), "\n";

echo "8. The daily limit, and no quiet hours\n";
SF_Options::update( array( 'max_posts_day' => 1 ) );
$id = SF_Poster::queue_text( 'live', 'limit-test', 'Over the limit', array( 'match_id' => 'zz' ) );
SF_Poster::publish_due();
$row = SF_DB::get_post( $id );
ok( 'skipped' === $row['status'], 'a live post over the daily limit is skipped (' . $row['error'] . ')' );

echo "9. Failure handling\n";
SF_Options::update( array( 'max_posts_day' => 50 ) );
$GLOBALS['sf_http'] = function () {
	return sf_json( array( 'error' => array( 'message' => 'Error validating access token', 'code' => 190 ) ), 400 );
};
$id = SF_Poster::queue_text( 'result', 'fail-test', 'Will fail' );
SF_Poster::publish_due();
$row = SF_DB::get_post( $id );
ok( 'failed' === $row['status'] && false !== strpos( $row['error'], 'access token' ), 'an expired token marks the post failed (' . $row['error'] . ')' );

echo "10. Which matches are covered\n";
function mk( $a, $b, $series = 'x9', $name = null ) {
	return SF_API::normalize_match( array_merge( match_json( 'z' . crc32( $a . $b . $series ), $a, $b, time() + 7200, false, false, 'Match not started', array(), $series ), $name ? array( 'name' => $name ) : array() ) );
}
SF_Options::update( array( 'followed' => array(), 'cover' => 'top', 'skip_women' => 1 ) );
ok( SF_API::wanted( mk( 'India', 'Australia' ) ), 'top team v top team is covered' );
ok( SF_API::wanted( mk( 'UAE', 'USA' ) ), 'short names UAE and USA count as top teams' );
ok( ! SF_API::wanted( mk( 'Mumbai Indians', 'Chennai Super Kings' ) ), 'a club match is skipped when you follow nothing' );
ok( ! SF_API::wanted( mk( 'India', 'Papua New Guinea' ) ), 'a match with a team outside the list is skipped' );
ok( ! SF_API::wanted( mk( 'India Women', 'Australia Women' ) ), "women's teams are skipped" );
ok( ! SF_API::wanted( mk( 'India', 'Australia', 'x9', 'India vs Australia (W), 3rd T20I' ) ), "a (W) match name is skipped" );
SF_Options::update( array( 'followed' => array( 'ipl' => 'IPL 2026' ) ) );
ok( SF_API::wanted( mk( 'Mumbai Indians', 'Chennai Super Kings', 'ipl' ) ), 'a tournament you follow is always covered' );
ok( SF_API::wanted( mk( 'India', 'Australia', 'other' ) ), 'a top-team match in a series you do not follow is still covered' );
ok( ! SF_API::wanted( mk( 'Mumbai Indians', 'Chennai Super Kings', 'other' ) ), 'a club match in a series you do not follow is skipped' );
SF_Options::update( array( 'cover' => 'followed' ) );
ok( ! SF_API::wanted( mk( 'India', 'Australia', 'other' ) ), 'in followed-only mode a series you do not follow is skipped' );
SF_Options::update( array( 'cover' => 'all' ) );
ok( SF_API::wanted( mk( 'Mumbai Indians', 'Chennai Super Kings', 'other' ) ) && SF_API::wanted( mk( 'England', 'Sri Lanka', 'tri' ) ), 'in all-matches mode every series is covered' );
ok( ! SF_API::wanted( mk( 'England Women', 'Sri Lanka Women', 'tri' ) ), "and women's matches are still skipped" );
SF_Options::update( array( 'cover' => 'top' ) );
ok( ! SF_API::wanted( mk( 'Delhi Women', 'Mumbai Women', 'ipl' ) ), "women's matches are skipped even in a followed series" );
SF_Options::update( array( 'followed' => array( 's1' => 'Harbour Cup 2026' ), 'skip_women' => 1 ) );

echo "10b. A waiting post does not block newer ones\n";
$GLOBALS['sf_http'] = function () {
	return sf_json( array( 'id' => '1_ok' ) );
};
SF_Options::update( array( 'per_match_hour' => 1, 'max_posts_day' => 50, 'paused' => 0 ) );
global $wpdb;
$wpdb->query( "UPDATE wp_sf_posts SET status = 'skipped' WHERE status = 'approved'" );
SF_DB::add_post( array( 'type' => 'live', 'dedupe' => 'blk-posted', 'match_id' => 'mm', 'status' => 'posted', 'caption' => 'x', 'posted_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$ids = array();
foreach ( array( 'a', 'b', 'c' ) as $k ) {
	$ids[] = SF_Poster::queue_text( 'live', 'blk-' . $k, 'Waiting ' . $k, array( 'match_id' => 'mm' ) );
}
$res_id = SF_Poster::queue_text( 'result', 'blk-result', 'RESULT | later post', array( 'match_id' => 'other' ) );
SF_Poster::publish_due();
$row = SF_DB::get_post( $res_id );
ok( 'posted' === $row['status'], 'a result is sent although three older live posts are waiting on the per-match limit (' . $row['status'] . ')' );
$w = SF_Poster::wait_reason( SF_DB::get_post( $ids[0] ) );
ok( false !== strpos( $w, 'already had 1 live posts' ), 'the waiting post says why: ' . $w );
SF_Options::update( array( 'paused' => 1 ) );
$w = SF_Poster::wait_reason( SF_DB::get_post( $ids[0] ) );
ok( false !== strpos( $w, 'paused' ), 'a paused site is named as the reason: ' . $w );
SF_Options::update( array( 'paused' => 0 ) );
ok( ! method_exists( 'SF_Poster', 'in_quiet_hours' ), 'there are no quiet hours' );
$ok_now = SF_Poster::publish_one( SF_DB::get_post( $ids[0] ), true );
ok( 'posted' === $ok_now, 'Post now sends it past the rules' );
$wpdb->query( "UPDATE wp_sf_posts SET status = 'skipped' WHERE status = 'approved'" );
SF_Options::update( array( 'per_match_hour' => 5 ) );

echo "11. Fewer calls and backup keys\n";
SF_Options::update( array( 'api_limit' => 10, 'api_key' => 'KEYONE', 'api_key2' => 'KEYTWO', 'api_key3' => '', 'api_key4' => '' ) );
update_option( 'sf_api_stats', array() );
delete_transient( 'sf_current_matches' );
$used = array();
$GLOBALS['sf_http'] = function ( $method, $url, $args ) use ( &$used ) {
	parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
	$used[] = $q['apikey'];
	if ( 'KEYONE' === $q['apikey'] ) {
		return sf_json( array( 'status' => 'failure', 'reason' => 'Hits today exceeded hits limit' ) );
	}
	return sf_json( array( 'status' => 'success', 'data' => array(), 'info' => array( 'hitsToday' => 1, 'hitsLimit' => 10 ) ) );
};
$r = SF_API::current_matches( true );
ok( ! is_wp_error( $r ), 'a refused key falls back to the next key in the same call' );
ok( array( 'KEYONE', 'KEYTWO' ) === $used, 'the main key was tried first, then the backup (' . implode( ',', $used ) . ')' );
$used = array();
delete_transient( 'sf_current_matches' );
update_option( 'sf_last_matches', array( 0, array() ) );
SF_API::current_matches( true );
ok( array( 'KEYTWO' ) === $used, 'the used-up key is not tried again today' );
$st = SF_API::stats();
ok( 2 === $st['keys'] && 20 === $st['limit'], 'the daily total adds up over both keys (' . $st['hits'] . ' of ' . $st['limit'] . ')' );
$used = array();
update_option( 'sf_last_matches', array( time(), array( array( 'id' => 'cached' ) ) ) );
SF_API::$reuse = 60;
$r = SF_API::current_matches( true );
ok( empty( $used ) && 'cached' === $r[0]['id'], 'two quick presses of Check now cost one call' ) ;
SF_API::$reuse = 0;

SF_Options::update( array( 'api_key2' => '', 'api_limit' => 100 ) );
update_option( 'sf_api_stats', array() );
SF_Options::update( array( 'followed' => array( 's1' => 'Harbour Cup 2026' ) ) );
$GLOBALS['sf_http'] = function ( $method, $url ) use ( &$world ) {
	return sf_json( array( 'status' => 'success', 'data' => $world['matches'], 'info' => array( 'hitsToday' => 1, 'hitsLimit' => 100 ) ) );
};
$world['matches'] = array( match_json( 'far', 'India', 'Australia', time() + 6 * 3600, false, false, 'Match not started', array(), 's1' ) );
delete_transient( 'sf_current_matches' );
update_option( 'sf_state', array() );
tick();
$wait = (int) get_option( 'sf_next_check' ) - time();
ok( $wait > 90 * 60 && $wait <= 120 * 60, 'with the next match 6 hours away the plugin sleeps 2 hours (' . round( $wait / 60 ) . ' min)' );
$world['matches'] = array();
delete_transient( 'sf_current_matches' );
tick();
$wait = (int) get_option( 'sf_next_check' ) - time();
ok( $wait > 80 * 60, 'with no match in sight the plugin waits 90 minutes (' . round( $wait / 60 ) . ' min)' );

echo "11b. Today's matches post, also when the 8 am job is missed\n";
SF_Engine::$catch_up = true;
delete_option( 'sf_daily_done' );
SF_Options::update( array( 'followed' => array( 's1' => 'Harbour Cup 2026' ) ) );
$before_fx = count( posts( 'fixtures' ) );
$world['matches'] = array( match_json( 'fxa', 'India', 'Pakistan', time() + 4 * 3600, false, false, 'Match not started', array(), 's1' ) );
$msg = SF_Engine::daily();
ok( 0 === strpos( $msg, "Today's post" ), 'daily() says what it did: ' . $msg );
$world['matches'] = array( match_json( 'fxw', 'India Women', 'Pakistan Women', time() + 4 * 3600, false, false, 'Match not started', array(), 's1' ) );
$msg = SF_Engine::daily();
ok( false !== strpos( $msg, 'No post made' ) && false !== strpos( $msg, "1 women's" ), 'when nothing is covered it explains why: ' . $msg );
SF_Engine::$catch_up = false;

echo "12. Test matches\n";
SF_Options::update( array( 'followed' => array( 's1' => 'Harbour Cup 2026' ), 'jobs' => array_merge( SF_Options::get( 'jobs' ), array( 'stumps' => 1 ) ) ) );
update_option( 'sf_state', array() );
$before_live = count( posts( 'live' ) );
$test = match_json( 'tst1', 'England', 'Australia', time() - 2 * DAY_IN_SECONDS, true, false, 'Day 2: Stumps - England lead by 68 runs',
	array(
		array( 'r' => 312, 'w' => 10, 'o' => 96.2, 'inning' => 'Australia Inning 1' ),
		array( 'r' => 244, 'w' => 10, 'o' => 71.0, 'inning' => 'England Inning 1' ),
		array( 'r' => 136, 'w' => 2, 'o' => 38.0, 'inning' => 'England Inning 2' ),
	), 's1', 'test' );
$world['matches'] = array( $test );
tick();
$st = posts( 'stumps' );
ok( 1 === count( $st ), 'a Test at stumps queues one day summary (' . count( $st ) . ')' );
ok( $st && false !== strpos( $st[0]['caption'], 'STUMPS, DAY 2' ), 'its caption names the day: ' . ( $st ? strtok( $st[0]['caption'], "\n" ) : '' ) );
ok( $st && false !== strpos( $st[0]['caption'], 'England 244 & 136/2' ), 'the caption shows both innings of each side' );
tick();
ok( 1 === count( posts( 'stumps' ) ), 'the same day is not posted twice' );
$world['matches'][0]['score'][] = array( 'r' => 150, 'w' => 3, 'o' => 40.0, 'inning' => 'England Inning 2' );
unset( $world['matches'][0]['score'][2] );
$world['matches'][0]['score'] = array_values( $world['matches'][0]['score'] );
tick();
ok( count( posts( 'live' ) ) === $before_live, 'no wicket cards for a Test' );
$n_inn = count( posts( 'innings' ) );
$world['matches'][0]['score'][] = array( 'r' => 20, 'w' => 0, 'o' => 4.0, 'inning' => 'Australia Inning 2' );
tick();
$inn = posts( 'innings' );
ok( count( $inn ) === $n_inn + 1 && 0 === strpos( end( $inn )['caption'], 'INNINGS ENDS' ), 'a Test posts when an innings ends: ' . strtok( end( $inn )['caption'], "\n" ) );
$world['matches'][0]['status'] = 'Day 3: Stumps - England lead by 120 runs';
tick();
ok( 2 === count( posts( 'stumps' ) ), 'day 3 gets its own summary' );
$world['matches'][0]['status'] = 'England won by an innings and 25 runs';
$world['matches'][0]['matchEnded'] = true;
tick();
$res = posts( 'result' );
$last = end( $res );
ok( $last && false !== strpos( $last['caption'], 'won by an innings and 25 runs' ), 'a Test result keeps "an innings and 25 runs"' );
$rm = new ReflectionMethod( 'SF_Engine', 'result_status' );
$rm->setAccessible( true );
$rs = $rm->invoke( null, array( 'status' => 'England won by an innings and 25 runs' ) );
ok( 'England' === $rs[0][0] && 'an innings and 25 runs' === trim( $rs[2][0] ), 'the result bar highlights the margin' );
list( $w, $type ) = SF_API::parse_result( array( 'status' => 'Match drawn', 'teams' => array( array( 'name' => 'England' ), array( 'name' => 'Australia' ) ) ) );
ok( 'draw' === $type && '' === $w, 'a drawn Test is recorded as a draw' );
$draw = SF_API::normalize_match( match_json( 'tst2', 'India', 'Australia', time() - 5 * DAY_IN_SECONDS, true, true, 'Match drawn', array(), 's1', 'test' ) );
ok( $draw['is_test'], 'matchType test is recognised' );

echo "13. Player photos\n";
$img = sys_get_temp_dir() . '/sf-player-test.jpg';
$g   = imagecreatetruecolor( 400, 600 );
imagefill( $g, 0, 0, imagecolorallocate( $g, 200, 40, 40 ) );
imagejpeg( $g, $img );
$GLOBALS['sf_attached'] = array( 77 => $img );
SF_Options::update( array( 'player_photos' => array( 'oliver hartley' => 77 ) ) );
ok( $img === SF_Players::photo( 'Oliver Hartley' ), 'a saved photo is found by name, ignoring case' );
ok( null === SF_Players::photo( 'Someone Else' ), 'a player without a photo gets none' );
ok( in_array( 'Someone Else', SF_Players::known(), true ), 'looked-up names are remembered for the Players screen' );
$cards = new SF_Cards();
$d     = SF_Samples::all();
$out1  = sys_get_temp_dir() . '/sf-photo-card.png';
$cards->render( 'potm', $d['potm'], $out1 );
$px = imagecreatefrompng( $out1 );
$c  = imagecolorat( $px, 160, 270 );
ok( ( ( $c >> 16 ) & 255 ) > 150 && ( ( $c >> 8 ) & 255 ) < 100, 'the photo is drawn on the card in place of the initials tile' );
SF_Options::update( array( 'player_photos' => array() ) );
$cards->render( 'potm', $d['potm'], $out1 );
$px = imagecreatefrompng( $out1 );
$c  = imagecolorat( $px, 160, 270 );
ok( ( ( $c >> 16 ) & 255 ) < 60, 'without a photo the navy initials tile is drawn' );

echo "14. Free player photos from Wikimedia Commons\n";
$wiki_calls = array();
$png = (function () { $g = imagecreatetruecolor( 300, 400 ); imagefill( $g, 0, 0, imagecolorallocate( $g, 30, 90, 200 ) ); ob_start(); imagejpeg( $g ); return ob_get_clean(); })();
$GLOBALS['sf_http'] = function ( $method, $url, $args ) use ( &$wiki_calls, $png ) {
	$wiki_calls[] = $url;
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	if ( false !== strpos( $url, 'en.wikipedia.org' ) && isset( $q['list'] ) ) {
		$name = trim( str_replace( ' cricketer', '', $q['srsearch'] ) );
		return sf_json( array( 'query' => array( 'search' => array( array( 'title' => $name ) ) ) ) );
	}
	if ( false !== strpos( $url, 'en.wikipedia.org' ) ) {
		$t = $q['titles'];
		$img = 'Oliver Hartley.jpg' ;
		if ( 'Nonfree Player' === $t ) { $img = 'Nonfree.jpg'; }
		if ( 'Nolic Player' === $t ) { $img = 'Nolic.jpg'; }
		return sf_json( array( 'query' => array( 'pages' => array( array( 'title' => $t, 'pageimage' => $img, 'pageprops' => array( 'wikibase-shortdesc' => 'English cricketer' ) ) ) ) ) );
	}
	if ( false !== strpos( $url, 'commons.wikimedia.org' ) ) {
		$f = $q['titles'];
		if ( 'File:Nonfree.jpg' === $f ) { return sf_json( array( 'query' => array( 'pages' => array( array( 'title' => $f, 'missing' => true ) ) ) ) ); }
		$lic = 'File:Nolic.jpg' === $f ? 'CC BY-NC 4.0' : 'CC BY-SA 4.0';
		return sf_json( array( 'query' => array( 'pages' => array( array( 'title' => $f, 'imageinfo' => array( array( 'thumburl' => 'https://upload.wikimedia.org/thumb/x.jpg', 'mime' => 'image/jpeg', 'extmetadata' => array( 'LicenseShortName' => array( 'value' => $lic ), 'Artist' => array( 'value' => '<a href="x">Jane Photographer</a>' ) ) ) ) ) ) ) ) );
	}
	if ( false !== strpos( $url, 'upload.wikimedia.org' ) ) {
		return array( 'code' => 200, 'body' => $png );
	}
	return array( 'code' => 404, 'body' => '' );
};
SF_Options::update( array( 'player_photos' => array(), 'auto_player_photos' => 1 ) );
delete_option( 'sf_player_auto' );
SF_Players::$fetched = 0;
$path = SF_Players::photo( 'Oliver Hartley' );
ok( $path && is_readable( $path ), 'a freely licensed photo is found and saved' );
ok( 'Photo: Jane Photographer, CC BY-SA 4.0, via Wikimedia Commons' === SF_Players::credit( 'Oliver Hartley' ), 'the credit line names the photographer and licence: ' . SF_Players::credit( 'Oliver Hartley' ) );
$n = count( $wiki_calls );
SF_Players::photo( 'Oliver Hartley' );
ok( count( $wiki_calls ) === $n, 'a saved photo is not looked up again' );
SF_Players::$fetched = 0;
ok( null === SF_Players::photo( 'Nonfree Player' ), 'a photo that is not on Commons (non-free) is not used' );
ok( null === SF_Players::photo( 'Nolic Player' ), 'a non-commercial licence is refused' );
$n = count( $wiki_calls );
SF_Players::photo( 'Nolic Player' );
ok( count( $wiki_calls ) === $n, 'a player with no usable photo is not looked up again for 30 days' );
ok( ! SF_PlayerPhotos::licence_ok( 'CC BY-ND 2.0' ) && ! SF_PlayerPhotos::licence_ok( 'Fair use' ) && SF_PlayerPhotos::licence_ok( 'CC0' ) && SF_PlayerPhotos::licence_ok( 'Public domain' ), 'only reusable licences are accepted' );
ok( '' === ( SF_PlayerPhotos::credit_needed( 'CC0' ) ? 'x' : '' ), 'CC0 needs no credit' );
$rc = new ReflectionMethod( 'SF_Engine', 'credits' );
$rc->setAccessible( true );
ok( array( 'Photo: Jane Photographer, CC BY-SA 4.0, via Wikimedia Commons' ) === $rc->invoke( null, array( 'Oliver Hartley', 'Oliver Hartley', '' ) ), 'the post caption gets the credit line once for the player' );
SF_Options::update( array( 'player_photos' => array( 'oliver hartley' => 77 ) ) );
ok( '' === SF_Players::credit( 'Oliver Hartley' ), 'your own uploaded photo needs no credit line' );
SF_Options::update( array( 'player_photos' => array() ) );
SF_Players::block( SF_Players::key( 'Oliver Hartley' ) );
SF_Players::$fetched = 0;
ok( null === SF_Players::photo( 'Oliver Hartley' ) && '' === SF_Players::credit( 'Oliver Hartley' ), 'Stop using removes the photo and the credit' );
SF_Options::update( array( 'auto_player_photos' => 0 ) );
delete_option( 'sf_player_auto' );
$n = count( $wiki_calls );
ok( null === SF_Players::photo( 'Another Player' ) && count( $wiki_calls ) === $n, 'with the switch off nothing is looked up' );
SF_Options::update( array( 'auto_player_photos' => 1 ) );

require_once SF_DIR . 'includes/class-sf-admin.php';
echo "15. Clear reasons when nothing posts\n";
SF_Options::update( array( 'paused' => 1 ) );
$rows = SF_Health::problems();
$ids = array_column( $rows, 0 );
ok( in_array( 'paused', $ids, true ), 'a paused site is named as a problem' );
SF_Options::update( array( 'paused' => 0 ) );
$tm = SF_Health::explain_fb( '(#200) The permission(s) publish_actions are not available.' );
ok( false !== strpos( $tm, 'User token' ) && false !== strpos( $tm, 'me/accounts' ), 'publish_actions is explained as a wrong token: ' . $tm );
ok( false !== strpos( SF_Health::explain_fb( 'Error validating access token: Session has expired' ), 'expired' ), 'an expired token is explained' );
update_option( 'sf_next_check', time() - 3600 );
update_option( 'sf_last_tick', time() - 7200 );
$cron = array_values( array_filter( SF_Health::report(), function ( $r ) { return 'cron' === $r[0]; } ) );
ok( 'bad' === $cron[0][1] && false !== strpos( $cron[0][3], 'wp-cron.php' ), 'a missed schedule says what to run: ' . $cron[0][3] );
// A User token is refused: /me answers with a person, not the Page.
$GLOBALS['sf_http'] = function ( $method, $url ) {
	if ( false !== strpos( $url, '/me' ) ) { return sf_json( array( 'id' => '999' ) ); }
	return sf_json( array( 'name' => 'Cricket Sport', 'category' => 'Sports' ) );
};
$v = SF_Facebook::verify();
ok( is_wp_error( $v ) && 'sf_fb_usertoken' === $v->get_error_code(), 'a User token is refused with a clear message' );
$GLOBALS['sf_http'] = function ( $method, $url ) {
	return sf_json( array( 'name' => 'Mudassar' ) );
};
$v = SF_Facebook::verify();
ok( is_wp_error( $v ) && 'sf_fb_notpage' === $v->get_error_code(), 'a profile or app ID is refused (no category)' );
SF_Options::update( array( 'manual_enabled' => 0 ) );
$r = SF_Poster::manual_post( array( 'caption' => 'x' ) );
ok( ! $r['ok'] && false !== strpos( $r['message'], 'switched off' ), 'manual posts are refused while switched off' );
ok( ! isset( SF_Admin::tabs()['manual'] ), 'and the Manual post screen is hidden' );

echo "\n", $fail ? "$fail FAILED\n" : "All checks passed\n";
exit( $fail ? 1 : 0 );
