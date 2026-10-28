<?php
/**
 * Made-up sample data for every card type. Used by the Score cards screen and by tools/render-samples.php.
 * The teams, players and scores are invented. Nothing here is real match data.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Samples {

	public static function types() {
		return array(
			'live'     => 'Live score',
			'toss'     => 'Toss',
			'result'   => 'Result',
			'summary'  => 'Match summary',
			'digest'   => 'Scoreboard',
			'fixtures' => "Today's matches",
			'century'  => 'Century',
			'fivefor'  => 'Five-wicket haul',
			'poll'     => 'Pick a side',
			'potm'     => 'Top performer',
			'table'    => 'Points table',
			'ranking'  => 'Monthly ranking',
		);
	}

	public static function all() {
		$fl = function ( $name, $code, $score = '', $ov = '' ) {
			return array( 'name' => $name, 'kind' => 'flag', 'path' => SF_DIR . 'assets/flags/' . $code . '.png', 'initials' => strtoupper( $code ), 'score' => $score, 'ov' => $ov );
		};
		$bd = function ( $name, $ini, $score = '', $ov = '' ) {
			return array( 'name' => $name, 'kind' => 'badge', 'path' => null, 'initials' => $ini, 'score' => $score, 'ov' => $ov );
		};
		$ft = array( 'foot_left' => 'facebook.com/cricketsportinfo', 'foot_right' => 'Sample data' );
		$T   = 'Harbour Cup 2026';
		$eng = $fl( 'England', 'eng' );
		$aus = $fl( 'Australia', 'aus' );

		$cards = array();
		$cards['live']   = array( 'tournament' => $T, 'meta' => 'T20, Match 14, Harbour Oval', 'teams' => array( $fl( 'Australia', 'aus', '168/7', '20.0 ov' ), $fl( 'England', 'eng', '142/3', '15.2 ov' ) ),
			'status' => array( array( 'England need ', false ), array( '27 runs', true ), array( ' from ', false ), array( '28 balls', true ) ) ) + $ft;
		$cards['toss']   = array( 'tournament' => $T, 'meta' => 'T20, Match 14, Harbour Oval', 'teams' => array( $aus, $eng ),
			'status' => array( array( 'Australia', true ), array( ' won the toss and chose to bat first', false ) ) ) + $ft;
		$cards['result'] = array( 'tournament' => $T, 'meta' => 'T20, Match 14, Harbour Oval', 'teams' => array( $fl( 'Australia', 'aus', '168/7', '20.0 ov' ), $fl( 'England', 'eng', '171/4', '18.3 ov' ) ),
			'status' => array( array( 'England', true ), array( ' won by ', false ), array( '6 wickets', true ), array( ' with 9 balls to spare', false ) ) ) + $ft;
		$cards['summary'] = array( 'tournament' => $T, 'meta' => 'T20, Match 14, Harbour Oval, 9 balls to spare', 'headline' => 'England won by 6 wickets',
			'teams' => array( $fl( 'Australia', 'aus', '168/7', '20.0 ov' ), $fl( 'England', 'eng', '171/4', '18.3 ov' ) ),
			'perf' => array(
				'bat'  => array( 'name' => 'Oliver Hartley', 'team' => $eng, 'stat' => '78*', 'small' => '(46)', 'detail' => '7 fours, 3 sixes, strike rate 169.6' ),
				'bowl' => array( 'name' => 'Sam Whitlock', 'team' => $eng, 'stat' => '3/24', 'small' => '(4 ov)', 'detail' => 'Economy 6.00, 1 maiden' ),
			),
			'also' => array( array( 'name' => 'Jack Merriman', 'team' => $aus, 'stat' => '61 (38)' ), array( 'name' => 'Liam Corbett', 'team' => $aus, 'stat' => '2/29 (4 ov)' ) ),
			'stats' => array(
				array( 'Toss', array( array( 'Australia chose to ', false ), array( 'bat', true ) ) ),
				array( 'Run rate', array( array( 'AUS 8.40, ENG ', false ), array( '9.24', true ) ) ),
				array( 'Player of the match', array( array( 'Oliver Hartley', false ) ) ),
			) ) + $ft;
		$cards['digest'] = array( 'tournament' => '', 'meta' => 'Thursday 29 October, 10 matches today', 'rows' => array(
			array( 'st' => 'LIVE', 'a' => $eng, 'b' => $aus, 'score' => '142/3', 'sub' => '15.2 ov' ),
			array( 'st' => 'LIVE', 'a' => $fl( 'New Zealand', 'nzl' ), 'b' => $fl( 'South Africa', 'rsa' ), 'score' => '88/2', 'sub' => '10.0 ov' ),
			array( 'st' => 'FT', 'a' => $fl( 'Sri Lanka', 'sri' ), 'b' => $fl( 'Bangladesh', 'ban' ), 'score' => '', 'sub' => 'Sri Lanka by 5 wickets' ),
			array( 'st' => 'LIVE', 'a' => $bd( 'Desert Riders', 'DR' ), 'b' => $bd( 'City Falcons', 'CF' ), 'score' => '54/1', 'sub' => '6.1 ov' ),
			array( 'st' => '12:30', 'a' => $bd( 'River Kings', 'RK' ), 'b' => $bd( 'Hill Eagles', 'HE' ), 'score' => '', 'sub' => 'Dune Trophy' ),
			array( 'st' => '13:00', 'a' => $bd( 'Lake Lions', 'LL' ), 'b' => $bd( 'Port Sharks', 'PS' ), 'score' => '', 'sub' => 'Lakeside League' ),
			array( 'st' => '14:30', 'a' => $bd( 'Metro Blazers', 'MB' ), 'b' => $bd( 'Valley Titans', 'VT' ), 'score' => '', 'sub' => 'Lakeside League' ),
		), 'status' => array( array( '3 more matches later today. Full list on the Page.', false ) ) ) + $ft;
		$cards['fixtures'] = array( 'tournament' => $T, 'meta' => 'Thursday 29 October', 'rows' => array(
			array( 'st' => '10:00', 'a' => $eng, 'b' => $aus, 'sub' => 'Harbour Oval' ),
			array( 'st' => '14:30', 'a' => $fl( 'New Zealand', 'nzl' ), 'b' => $fl( 'South Africa', 'rsa' ), 'sub' => 'Dune Park' ),
			array( 'st' => '19:00', 'a' => $fl( 'Sri Lanka', 'sri' ), 'b' => $fl( 'Bangladesh', 'ban' ), 'sub' => 'Bridge Road' ),
		) ) + $ft;
		$cards['century'] = array( 'tournament' => $T, 'meta' => 'T20, Match 11, Dune Park', 'name' => 'Tomas Everly', 'mono' => 'TE', 'team' => $fl( 'New Zealand', 'nzl' ),
			'stat' => '100*', 'small' => '(52 balls)', 'chips' => array( '8 fours', '5 sixes', 'Strike rate 192.3' ),
			'status' => array( array( 'Fastest hundred', true ), array( ' of ' . $T, false ) ), 'foot_left' => 'Great knock? Tell us in the comments', 'foot_right' => 'Sample data' );
		$cards['fivefor'] = array( 'tournament' => $T, 'meta' => 'T20, Match 12, Bridge Road', 'name' => 'Danny Kerrigan', 'mono' => 'DK', 'team' => $fl( 'South Africa', 'rsa' ),
			'stat' => '5/18', 'small' => '(4 overs)', 'chips' => array( 'Economy 4.50', '2 maidens', '14 dot balls' ),
			'status' => array( array( 'Best figures', true ), array( ' of ' . $T, false ) ), 'foot_left' => 'Spell of the day? Tell us in the comments', 'foot_right' => 'Sample data' );
		$cards['potm'] = array( 'tournament' => $T, 'meta' => 'T20, Match 14, Harbour Oval', 'pill' => 'PLAYER OF THE MATCH', 'name' => 'Oliver Hartley', 'mono' => 'OH', 'team' => $eng,
			'stat' => '78*', 'small' => '(46 balls)', 'chips' => array( '7 fours', '3 sixes', 'Strike rate 169.6' ),
			'status' => array( array( 'England won by ', false ), array( '6 wickets', true ), array( ', 9 balls to spare', false ) ), 'foot_left' => 'Agree? Tell us your player of the day', 'foot_right' => 'Sample data' );
		$cards['poll'] = array( 'tournament' => $T, 'meta' => 'T20, Match 14, first ball at 10:00', 'teams' => array( $eng, $aus ),
			'status' => array( array( 'Vote with a reaction, then tell us your ', false ), array( 'score prediction', true ), array( ' in the comments', false ) ), 'foot_left' => 'Which side are you on?', 'foot_right' => 'Sample data' );
		$rows = array();
		foreach ( array( array( 'England', 'eng', 4, 3, 1, '+0.842', 6 ), array( 'Australia', 'aus', 4, 3, 1, '+0.311', 6 ), array( 'India', 'ind', 4, 2, 2, '+0.254', 4 ), array( 'New Zealand', 'nzl', 4, 2, 2, '+0.127', 4 ), array( 'Pakistan', 'pak', 4, 2, 2, '+0.044', 4 ), array( 'South Africa', 'rsa', 4, 2, 2, '-0.215', 4 ), array( 'Sri Lanka', 'sri', 4, 1, 3, '-0.403', 2 ), array( 'Bangladesh', 'ban', 4, 1, 3, '-0.662', 2 ) ) as $r ) {
			$rows[] = array( 'team' => $fl( $r[0], $r[1] ), 'vals' => array( (string) $r[2], (string) $r[3], (string) $r[4], $r[5], (string) $r[6] ) );
		}
		$cards['table'] = array( 'tournament' => $T, 'meta' => 'after week 2', 'pill' => 'WEEK 2', 'title' => 'Points table', 'cols' => array( 'P', 'W', 'L', 'NRR', 'Pts' ), 'rows' => $rows,
			'status' => array( array( 'Top four', true ), array( ' reach the semi-finals', false ) ), 'foot_left' => 'Who tops the table next week? Comment your pick', 'foot_right' => 'Sample data' );
		$rk = array();
		foreach ( array( array( 'England', 'eng', 9, 7, 2, '78%', 14 ), array( 'Australia', 'aus', 9, 6, 3, '67%', 12 ), array( 'India', 'ind', 8, 5, 3, '63%', 10 ), array( 'New Zealand', 'nzl', 8, 5, 3, '63%', 10 ), array( 'South Africa', 'rsa', 9, 4, 5, '44%', 8 ), array( 'Pakistan', 'pak', 8, 3, 5, '38%', 6 ) ) as $r ) {
			$rk[] = array( 'team' => $fl( $r[0], $r[1] ), 'vals' => array( (string) $r[2], (string) $r[3], (string) $r[4], $r[5], (string) $r[6] ) );
		}
		$cards['ranking'] = array( 'tournament' => '', 'meta' => 'October 2026, from matches tracked on this Page', 'pill' => 'OCTOBER', 'title' => 'Monthly team ranking', 'cols' => array( 'P', 'W', 'L', 'Win', 'Pts' ), 'rows' => $rk,
			'status' => array( array( 'England', true ), array( ' finish the month on top', false ) ), 'foot_left' => 'Who finishes top in November? Comment your pick', 'foot_right' => 'Sample data' );


		return $cards;
	}
}
