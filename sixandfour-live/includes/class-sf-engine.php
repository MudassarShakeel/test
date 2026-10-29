<?php
/**
 * The brain: reads matches, spots events, builds card data and captions, and queues posts.
 *
 * Runs from WP-Cron (see SF_Plugin). Nothing here posts directly. It queues, and
 * SF_Poster applies the posting rules and sends.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Engine {

	/* ------------------------------------------------------------------ */
	/* Small helpers                                                       */
	/* ------------------------------------------------------------------ */

	private static function tag() {
		return apply_filters( 'sf_hashtags', '#CricketSport #Cricket' );
	}

	private static function question( $seed = '' ) {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) SF_Options::get( 'questions', '' ) ) ) ) );
		if ( empty( $lines ) ) {
			return '';
		}
		return $lines[ abs( crc32( $seed ) ) % count( $lines ) ];
	}

	private static function caption( $headline, $lines = array(), $question_seed = null ) {
		$out = array( $headline );
		foreach ( $lines as $l ) {
			if ( '' !== $l ) {
				$out[] = $l;
			}
		}
		if ( null !== $question_seed ) {
			$q = self::question( $question_seed );
			if ( '' !== $q ) {
				$out[] = $q;
			}
		}
		$out[] = self::tag();
		return implode( "\n", $out );
	}

	private static function foot( $left = '' ) {
		$handle = (string) SF_Options::get( 'fb_handle', '' );
		return array( 'foot_left' => '' !== $left ? $left : $handle, 'foot_right' => '' !== $left ? $handle : '' );
	}

	private static function overs( $o ) {
		return number_format( (float) $o, 1, '.', '' );
	}

	/** Overs in cricket notation (15.2 means 15 overs and 2 balls) to balls. */
	private static function balls( $o ) {
		$whole = (int) floor( $o );
		$part  = (int) round( ( $o - $whole ) * 10 );
		return $whole * 6 + min( 5, $part );
	}

	private static function total_overs( $type ) {
		$map = array( 'T20' => 20, 'T10' => 10, 'ODI' => 50, 'HUNDRED' => 16 );
		return isset( $map[ $type ] ) ? $map[ $type ] : 0;
	}

	private static function local_time( $ts ) {
		return wp_date( 'H:i', $ts );
	}

	private static function tour( $m ) {
		return SF_Options::series_name( $m['series_id'] );
	}

	private static function short_result( $status ) {
		$status = preg_replace( '/\bwkts?\b/i', 'wickets', $status );
		return trim( $status );
	}

	/** A team's score as text. A Test shows every innings, such as 312 & 145/3. */
	private static function score_text( $m, $idx, $s ) {
		if ( ! empty( $m['is_test'] ) && ! empty( $m['team_inns'][ $idx ] ) ) {
			$parts = array();
			foreach ( $m['team_inns'][ $idx ] as $i ) {
				$parts[] = $i['w'] >= 10 ? (string) $i['r'] : $i['r'] . '/' . $i['w'];
			}
			return implode( ' & ', $parts );
		}
		return $s['r'] . '/' . $s['w'];
	}

	/** Team rows in batting order with scores, for the score cards. */
	private static function team_rows( $m, $scores = true ) {
		$rows = array();
		foreach ( $m['order'] as $idx ) {
			$t   = $m['teams'][ $idx ];
			$r   = SF_Teams::resolve( $t['name'], $t['short'] );
			$s   = isset( $m['scores'][ $idx ] ) ? $m['scores'][ $idx ] : null;
			$r['score'] = ( $scores && $s ) ? self::score_text( $m, $idx, $s ) : '';
			$r['ov']    = ( $scores && $s ) ? self::overs( $s['o'] ) . ' ov' : '';
			$rows[]     = $r;
		}
		return $rows;
	}

	/** Status bar segments for a match in progress. */
	private static function live_status( $m ) {
		$ord = $m['order'];
		$a   = isset( $m['scores'][ $ord[0] ] ) ? $m['scores'][ $ord[0] ] : null;
		$b   = isset( $m['scores'][ $ord[1] ] ) ? $m['scores'][ $ord[1] ] : null;
		$na  = $m['teams'][ $ord[0] ]['name'];
		$nb  = $m['teams'][ $ord[1] ]['name'];
		if ( $a && $b ) {
			$need = $a['r'] + 1 - $b['r'];
			$tot  = self::total_overs( $m['type'] );
			if ( $need > 0 && $tot > 0 ) {
				$left = $tot * 6 - self::balls( $b['o'] );
				if ( $left > 0 ) {
					return array( array( $nb . ' need ', false ), array( $need . ' runs', true ), array( ' from ', false ), array( $left . ' balls', true ) );
				}
			}
			if ( $need > 0 ) {
				return array( array( $nb . ' need ', false ), array( $need . ' runs', true ), array( ' to win', false ) );
			}
		}
		if ( $a && ! $b ) {
			return array( array( $na . ' are ', false ), array( $a['r'] . '/' . $a['w'], true ), array( ' after ' . self::overs( $a['o'] ) . ' overs', false ) );
		}
		if ( $b ) {
			return array( array( $nb . ' are ', false ), array( $b['r'] . '/' . $b['w'], true ), array( ' after ' . self::overs( $b['o'] ) . ' overs', false ) );
		}
		return array( array( self::short_result( $m['status'] ), false ) );
	}

	/** Status bar segments for a finished match. */
	private static function result_status( $m ) {
		$s = self::short_result( $m['status'] );
		if ( preg_match( '/^(.+?) won by (an innings and )?(\d+) (runs?|wickets?)(.*)$/i', $s, $x ) ) {
			return array( array( $x[1], true ), array( ' won by ', false ), array( $x[2] . $x[3] . ' ' . $x[4], true ), array( $x[5], false ) );
		}
		if ( preg_match( '/\b(drawn?|tied?)\b/i', $s ) ) {
			return array( array( $s, true ) );
		}
		return array( array( $s, false ) );
	}

	private static function card_base( $m ) {
		return array(
			'tournament' => self::tour( $m ),
			'meta'       => $m['meta'],
			'teams'      => self::team_rows( $m ),
		) + self::foot();
	}

	/* ------------------------------------------------------------------ */
	/* The main loop                                                       */
	/* ------------------------------------------------------------------ */

	/** Minutes until the next score check, spread so the daily API limit lasts. */
	private static function interval( $busy ) {
		if ( ! $busy ) {
			return 20;
		}
		$left_min = max( 1, ( strtotime( 'tomorrow 00:00 UTC' ) - time() ) / 60 );
		$calls    = max( 1, SF_API::remaining() );
		return (int) max( 5, min( 30, ceil( $left_min / $calls ) ) );
	}

	/** Post today's matches on the first check after 08:00 if the 08:00 job did not run (a quiet site may skip it). Tests set this to false. */
	public static $catch_up = true;

	private static function catch_up_daily() {
		$today = wp_date( 'Y-m-d' );
		if ( ! self::$catch_up || get_option( 'sf_daily_done', '' ) === $today || (int) wp_date( 'G' ) < 8 || (int) wp_date( 'G' ) >= 21 || ! SF_Options::job( 'fixtures' ) ) {
			return;
		}
		update_option( 'sf_daily_done', $today, false );
		SF_Log::add( "The 8 am job had not run, so today's matches are posted now." );
		self::daily();
	}

	public static function tick( $force = false ) {
		if ( ! SF_Options::ready() || SF_Options::get( 'paused' ) ) {
			return;
		}
		if ( ! $force && time() < (int) get_option( 'sf_next_check', 0 ) ) {
			SF_Poster::publish_due();
			return;
		}
		if ( ! $force && SF_Poster::in_quiet_hours() ) {
			update_option( 'sf_next_check', time() + 30 * MINUTE_IN_SECONDS, false );
			SF_Poster::publish_due();
			return;
		}
		$list = SF_API::current_matches( $force );
		if ( is_wp_error( $list ) ) {
			SF_Log::add( $list->get_error_message(), 'error' );
			update_option( 'sf_next_check', time() + 10 * MINUTE_IN_SECONDS, false );
			SF_Poster::publish_due();
			return;
		}

		self::catch_up_daily();

		$matches = array();
		foreach ( $list as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$m = SF_API::normalize_match( $raw );
			if ( '' === $m['id'] || ! SF_API::wanted( $m ) ) {
				continue;
			}
			foreach ( $m['teams'] as $t ) {
				SF_Teams::remember( $t['name'] );
			}
			$matches[] = $m;
		}

		$o     = SF_Options::all();
		$state = get_option( 'sf_state', array() );
		$state = is_array( $state ) ? $state : array();
		$live  = array_values( array_filter( $matches, function ( $m ) {
			return $m['started'] && ! $m['ended'];
		} ) );
		$followed_order = array_keys( (array) $o['followed'] );
		usort( $live, function ( $a, $b ) use ( $followed_order ) {
			$ia = array_search( $a['series_id'], $followed_order, true );
			$ib = array_search( $b['series_id'], $followed_order, true );
			$ia = false === $ia ? 999 : $ia;
			$ib = false === $ib ? 999 : $ib;
			return $ia === $ib ? $a['start'] <=> $b['start'] : $ia <=> $ib;
		} );
		$card_ids = array_slice( array_column( $live, 'id' ), 0, max( 1, (int) $o['live_cards'] ) );

		foreach ( $matches as $m ) {
			self::handle_match( $m, $state, in_array( $m['id'], $card_ids, true ), $o );
		}
		self::maybe_digest( $matches, $live, $card_ids, $o );
		self::update_pinned( $matches );

		// Forget old matches.
		foreach ( $state as $id => $st ) {
			if ( isset( $st['seen'] ) && time() - $st['seen'] > 3 * DAY_IN_SECONDS ) {
				unset( $state[ $id ] );
			}
		}
		update_option( 'sf_state', $state, false );

		// Check often only around live matches. Otherwise sleep until the next one is close.
		$next_start = 0;
		foreach ( $matches as $m ) {
			if ( ! $m['started'] && $m['start'] > time() && ( ! $next_start || $m['start'] < $next_start ) ) {
				$next_start = $m['start'];
			}
		}
		if ( ! empty( $live ) || ( $next_start && $next_start - time() <= 3600 ) ) {
			$wait = self::interval( true ) * MINUTE_IN_SECONDS;
		} elseif ( $next_start ) {
			$wait = max( 20 * MINUTE_IN_SECONDS, min( 2 * HOUR_IN_SECONDS, $next_start - time() - 45 * MINUTE_IN_SECONDS ) );
		} else {
			$wait = 90 * MINUTE_IN_SECONDS;
		}
		update_option( 'sf_next_check', time() + $wait - 30, false );
		update_option( 'sf_last_tick', time(), false );
		SF_Poster::publish_due();
	}

	private static function handle_match( $m, &$state, $is_card_match, $o ) {
		$id  = $m['id'];
		$new = ! isset( $state[ $id ] );
		$st  = $new ? array( 'inn' => count( $m['innings'] ), 'w' => 0, 'sc_at' => 0, 'toss' => false, 'poll' => false, 'done' => $m['ended'], 'seen' => time() ) : $state[ $id ];
		$st['seen'] = time();

		$cur  = null;
		foreach ( array_reverse( $m['order'] ) as $idx ) {
			if ( isset( $m['scores'][ $idx ] ) ) {
				$cur = $m['scores'][ $idx ];
				break;
			}
		}
		$cur_w = $cur ? $cur['w'] : 0;

		// A match first seen already finished is only recorded for the monthly ranking.
		if ( $new && $m['ended'] ) {
			self::store_result( $m );
			$st['done']  = true;
			$state[ $id ] = $st;
			return;
		}

		if ( ! $m['started'] ) {
			if ( ! $st['toss'] && SF_Options::job( 'toss' ) ) {
				$toss = SF_API::parse_toss( $m );
				if ( $toss ) {
					self::post_toss( $m, $toss );
					$st['toss'] = true;
				}
			}
			if ( ! $st['poll'] && SF_Options::job( 'poll' ) && $m['start'] > time() && $m['start'] - time() <= (int) $o['poll_hours_before'] * HOUR_IN_SECONDS ) {
				if ( self::polls_today() < (int) $o['polls_per_day'] ) {
					self::post_poll( $m );
					$st['poll'] = true;
				}
			}
			$state[ $id ] = $st;
			return;
		}

		if ( ! $m['ended'] ) {
			if ( $new ) {
				$st['w'] = $cur_w;
			}
			if ( ! empty( $m['is_test'] ) ) {
				// A Test lasts up to five days. One post at stumps each day, not a post for every wicket.
				$day = SF_API::stumps_day( $m );
				if ( $day && SF_Options::job( 'stumps' ) && empty( $st['days'][ $day ] ) ) {
					self::post_stumps( $m, $day );
					$st['days'][ $day ] = 1;
				}
			} elseif ( $is_card_match && SF_Options::job( 'live' ) ) {
				if ( count( $m['innings'] ) > $st['inn'] && $st['inn'] > 0 ) {
					self::post_innings_break( $m, $st['inn'] );
				} elseif ( SF_Options::job( 'wicket' ) && count( $m['innings'] ) === $st['inn'] && $cur_w > $st['w'] ) {
					self::post_wicket( $m, $cur_w );
				}
			}
			$st['inn'] = count( $m['innings'] );
			$st['w']   = ( count( $m['innings'] ) === $st['inn'] ) ? $cur_w : 0;

			if ( $is_card_match && SF_Options::job( 'milestones' ) && time() - $st['sc_at'] >= 15 * MINUTE_IN_SECONDS && SF_API::budget_ok() ) {
				$sc = SF_API::scorecard( $id );
				if ( ! is_wp_error( $sc ) ) {
					self::scan_milestones( $m, $sc );
				}
				$st['sc_at'] = time();
			}
			$state[ $id ] = $st;
			return;
		}

		// Finished.
		if ( ! $st['done'] ) {
			self::finish_match( $m );
			$st['done'] = true;
		}
		$state[ $id ] = $st;
	}

	private static function polls_today() {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', SF_Poster::day_start() );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . SF_DB::posts() . " WHERE type = 'poll' AND created_at >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/* ------------------------------------------------------------------ */
	/* Match events                                                        */
	/* ------------------------------------------------------------------ */

	private static function post_toss( $m, $toss ) {
		$d             = self::card_base( $m );
		$d['teams']    = array( SF_Teams::resolve( $m['teams'][0]['name'], $m['teams'][0]['short'] ), SF_Teams::resolve( $m['teams'][1]['name'], $m['teams'][1]['short'] ) );
		$d['status']   = array( array( $toss[0], true ), array( ' won the toss and chose to ' . ( 'bat' === $toss[1] ? 'bat' : 'bowl' ) . ' first', false ) );
		$cap           = self::caption( ( ! empty( $m['is_test'] ) ? 'TEST MATCH, DAY 1 | ' : 'TOSS | ' ) . trim( self::tour( $m ) . ': ' . $toss[0] . ' have won the toss and chosen to ' . $toss[1] . ' first.', ': ' ), array(), null );
		SF_Poster::queue( 'toss', 'toss:' . $m['id'], $d, $cap, array( 'match_id' => $m['id'], 'series_id' => $m['series_id'] ) );
	}

	private static function post_poll( $m ) {
		$a  = SF_Teams::resolve( $m['teams'][0]['name'], $m['teams'][0]['short'] );
		$b  = SF_Teams::resolve( $m['teams'][1]['name'], $m['teams'][1]['short'] );
		$d  = array(
			'tournament' => self::tour( $m ),
			'meta'       => trim( $m['meta'] . ', first ball at ' . self::local_time( $m['start'] ), ', ' ),
			'teams'      => array( $a, $b ),
			'status'     => array( array( 'Vote with a reaction, then tell us your ', false ), array( 'score prediction', true ), array( ' in the comments', false ) ),
		) + self::foot( 'Which side are you on?' );
		$cap = self::caption( 'WHO WINS? | ' . $a['name'] . ' v ' . $b['name'] . ( self::tour( $m ) ? ', ' . self::tour( $m ) : '' ) . ', first ball at ' . self::local_time( $m['start'] ) . '.', array( 'Like for ' . $a['name'] . ', Love for ' . $b['name'] . '. Then tell us your score prediction in the comments.' ) );
		SF_Poster::queue( 'poll', 'poll:' . $m['id'], $d, $cap, array( 'match_id' => $m['id'], 'series_id' => $m['series_id'] ) );
	}

	private static function post_wicket( $m, $w ) {
		$d   = self::card_base( $m );
		$d['status'] = self::live_status( $m );
		$cap = self::caption( 'WICKET | ' . self::headline_score( $m ), array(), null );
		SF_Poster::queue( 'live', 'wk:' . $m['id'] . ':' . count( $m['innings'] ) . ':' . $w, $d, $cap, array( 'match_id' => $m['id'], 'series_id' => $m['series_id'] ) );
	}

	private static function post_innings_break( $m, $inn ) {
		$d   = self::card_base( $m );
		$ord = $m['order'];
		$a   = isset( $m['scores'][ $ord[0] ] ) ? $m['scores'][ $ord[0] ] : null;
		if ( $a ) {
			$d['status'] = array( array( $m['teams'][ $ord[0] ]['name'], true ), array( ' finished on ' . $a['r'] . '/' . $a['w'] . '. ' . $m['teams'][ $ord[1] ]['name'] . ' need ', false ), array( ( $a['r'] + 1 ) . ' runs', true ), array( ' to win', false ) );
		} else {
			$d['status'] = self::live_status( $m );
		}
		$cap = self::caption( 'INNINGS BREAK | ' . self::headline_score( $m ), array(), null );
		SF_Poster::queue( 'live', 'inn:' . $m['id'] . ':' . $inn, $d, $cap, array( 'match_id' => $m['id'], 'series_id' => $m['series_id'] ) );
	}

	/** The end-of-day summary of a Test match. */
	private static function post_stumps( $m, $day ) {
		$d           = self::card_base( $m );
		$d['pill']   = 'STUMPS DAY ' . $day;
		$rest        = trim( preg_replace( '/^.*?(stumps|close of play|end of day)\s*[-:,]?\s*/i', '', $m['status'] ) );
		if ( preg_match( '/^(.+?)\s+(lead|trail)\s+by\s+(\d+ runs?)(.*)$/i', $rest, $x ) ) {
			$d['status'] = array( array( $x[1], true ), array( ' ' . strtolower( $x[2] ) . ' by ', false ), array( $x[3], true ), array( $x[4], false ) );
		} else {
			$d['status'] = array( array( '' !== $rest ? $rest : 'Stumps on day ' . $day, false ) );
		}
		$cap = self::caption( 'STUMPS, DAY ' . $day . ' | ' . self::headline_score( $m ), array( '' !== $rest ? ucfirst( $rest ) . '.' : '' ), 'stumps:' . $m['id'] . ':' . $day );
		SF_Poster::queue( 'stumps', 'stumps:' . $m['id'] . ':' . $day, $d, $cap, array( 'match_id' => $m['id'], 'series_id' => $m['series_id'] ) );
	}

	private static function headline_score( $m ) {
		$parts = array();
		foreach ( $m['order'] as $idx ) {
			if ( isset( $m['scores'][ $idx ] ) ) {
				$s       = $m['scores'][ $idx ];
				$parts[] = $m['teams'][ $idx ]['name'] . ' ' . self::score_text( $m, $idx, $s ) . ( empty( $m['is_test'] ) ? ' (' . self::overs( $s['o'] ) . ')' : '' );
			}
		}
		$t = self::tour( $m );
		return ( $t ? $t . ': ' : '' ) . implode( ', ', $parts ) . '.';
	}

	/* ------------------------------------------------------------------ */
	/* Finished matches                                                    */
	/* ------------------------------------------------------------------ */

	private static function store_result( $m ) {
		list( $winner, $type ) = SF_API::parse_result( $m );
		SF_DB::add_result(
			array(
				'match_id'    => $m['id'],
				'series_id'   => $m['series_id'],
				'series_name' => self::tour( $m ),
				'played_on'   => wp_date( 'Y-m-d', $m['start'] ? $m['start'] : time() ),
				'team_a'      => $m['teams'][0]['name'],
				'team_b'      => $m['teams'][1]['name'],
				'winner'      => $winner,
				'result_type' => $type,
			)
		);
	}

	private static function finish_match( $m ) {
		self::store_result( $m );
		$extra = array( 'match_id' => $m['id'], 'series_id' => $m['series_id'] );

		if ( SF_Options::job( 'result' ) ) {
			$d           = self::card_base( $m );
			$d['status'] = self::result_status( $m );
			$cap         = self::caption( 'RESULT | ' . ( self::tour( $m ) ? self::tour( $m ) . ': ' : '' ) . self::short_result( $m['status'] ) . '.', array(), null );
			SF_Poster::queue( 'result', 'result:' . $m['id'], $d, $cap, $extra );
		}
		if ( ! SF_API::budget_ok() ) {
			return;
		}
		$sc = SF_API::scorecard( $m['id'], true );
		if ( is_wp_error( $sc ) ) {
			SF_Log::add( 'No scorecard for ' . $m['name'] . ': ' . $sc->get_error_message(), 'warning' );
			return;
		}
		$p = SF_API::parse_scorecard( $sc, $m );
		if ( empty( $p['batters'] ) && empty( $p['bowlers'] ) ) {
			SF_Log::add( 'The scorecard for ' . $m['name'] . ' had no player data, so no summary was made.', 'warning' );
			return;
		}
		if ( SF_Options::job( 'milestones' ) ) {
			self::scan_milestones( $m, $sc );
		}
		if ( SF_Options::job( 'summary' ) ) {
			self::post_summary( $m, $p, $extra );
		}
		if ( SF_Options::job( 'potm' ) ) {
			self::post_potm( $m, $p, $extra );
		}
	}

	private static function best_batter( $list, $team = null, $exclude = array() ) {
		$best = null;
		foreach ( $list as $b ) {
			if ( ( null !== $team && $b['team'] !== $team ) || in_array( $b['id'], $exclude, true ) ) {
				continue;
			}
			if ( ! $best || $b['r'] > $best['r'] || ( $b['r'] === $best['r'] && $b['b'] < $best['b'] ) ) {
				$best = $b;
			}
		}
		return $best;
	}

	private static function best_bowler( $list, $team = null, $exclude = array() ) {
		$best = null;
		foreach ( $list as $b ) {
			if ( ( null !== $team && $b['team'] !== $team ) || in_array( $b['id'], $exclude, true ) ) {
				continue;
			}
			if ( ! $best || $b['w'] > $best['w'] || ( $b['w'] === $best['w'] && $b['r'] < $best['r'] ) ) {
				$best = $b;
			}
		}
		return $best;
	}

	private static function run_rate( $s ) {
		$balls = self::balls( $s['o'] );
		return $balls > 0 ? round( $s['r'] * 6 / $balls, 2 ) : 0;
	}

	private static function post_summary( $m, $p, $extra ) {
		$bat  = self::best_batter( $p['batters'] );
		$bowl = self::best_bowler( $p['bowlers'] );
		$team = function ( $name ) use ( $m ) {
			foreach ( $m['teams'] as $t ) {
				if ( $t['name'] === $name ) {
					return SF_Teams::resolve( $t['name'], $t['short'] );
				}
			}
			return SF_Teams::resolve( $name );
		};
		$perf = array();
		if ( $bat && $bat['r'] > 0 ) {
			$perf['bat'] = array(
				'name'   => $bat['name'],
				'team'   => $team( $bat['team'] ),
				'stat'   => $bat['r'] . ( $bat['out'] ? '' : '*' ),
				'small'  => '(' . $bat['b'] . ')',
				'detail' => $bat['4s'] . ' fours, ' . $bat['6s'] . ' sixes, strike rate ' . number_format( $bat['sr'], 1 ),
			);
		}
		if ( $bowl && $bowl['w'] > 0 ) {
			$perf['bowl'] = array(
				'name'   => $bowl['name'],
				'team'   => $team( $bowl['team'] ),
				'stat'   => $bowl['w'] . '/' . $bowl['r'],
				'small'  => '(' . self::overs( $bowl['o'] ) . ' ov)',
				'detail' => 'Economy ' . number_format( $bowl['eco'], 2 ) . ', ' . $bowl['m'] . ( 1 === $bowl['m'] ? ' maiden' : ' maidens' ),
			);
		}
		$ex   = array_filter( array( $bat ? $bat['id'] : null, $bowl ? $bowl['id'] : null ) );
		$also = array();
		$other_bat  = self::best_batter( $p['batters'], $bat ? ( $bat['team'] === $m['teams'][0]['name'] ? $m['teams'][1]['name'] : $m['teams'][0]['name'] ) : null, $ex );
		$other_bowl = self::best_bowler( $p['bowlers'], $bowl ? ( $bowl['team'] === $m['teams'][0]['name'] ? $m['teams'][1]['name'] : $m['teams'][0]['name'] ) : null, $ex );
		if ( $other_bat && $other_bat['r'] > 0 ) {
			$also[] = array( 'name' => $other_bat['name'], 'team' => $team( $other_bat['team'] ), 'stat' => $other_bat['r'] . ' (' . $other_bat['b'] . ')' );
			$ex[]   = $other_bat['id'];
		}
		if ( $other_bowl && $other_bowl['w'] > 0 ) {
			$also[] = array( 'name' => $other_bowl['name'], 'team' => $team( $other_bowl['team'] ), 'stat' => $other_bowl['w'] . '/' . $other_bowl['r'] . ' (' . self::overs( $other_bowl['o'] ) . ' ov)' );
		}
		$third = self::best_batter( $p['batters'], null, $ex );
		if ( $third && $third['r'] >= 20 && count( $also ) < 3 ) {
			$also[] = array( 'name' => $third['name'], 'team' => $team( $third['team'] ), 'stat' => $third['r'] . ' (' . $third['b'] . ')' );
		}

		// Key numbers.
		$stats = array();
		$toss  = SF_API::parse_toss( $m );
		$stats[] = $toss ? array( 'Toss', array( array( $toss[0] . ' chose to ', false ), array( $toss[1], true ) ) ) : array( 'Venue', array( array( '' !== $m['venue'] ? $m['venue'] : 'Not available', false ) ) );
		$rr = array();
		list( $winner ) = SF_API::parse_result( $m );
		foreach ( $m['order'] as $idx ) {
			if ( isset( $m['scores'][ $idx ] ) ) {
				$rr[] = array( strtoupper( SF_Teams::initials( $m['teams'][ $idx ]['name'], $m['teams'][ $idx ]['short'] ) ), number_format( self::run_rate( $m['scores'][ $idx ] ), 2 ), $m['teams'][ $idx ]['name'] === $winner );
			}
		}
		$segs = array();
		foreach ( $rr as $i => $r ) {
			$segs[] = array( ( $i > 0 ? ', ' : '' ) . $r[0] . ' ', false );
			$segs[] = array( $r[1], $r[2] );
		}
		$stats[] = array( 'Run rate', $segs ? $segs : array( array( 'Not available', false ) ) );
		$top     = self::top_performer( $p, $winner );
		$stats[] = array( 'Top performer', array( array( $top ? $top['name'] : 'Not available', false ) ) );

		$d = array(
			'tournament' => self::tour( $m ),
			'meta'       => $m['meta'],
			'headline'   => self::short_result( $m['status'] ),
			'teams'      => self::team_rows( $m ),
			'perf'       => $perf,
			'also'       => $also,
			'stats'      => $stats,
		) + self::foot();
		$lines = array();
		if ( isset( $perf['bat'] ) ) {
			$lines[] = 'Top scorer: ' . $perf['bat']['name'] . ' ' . $perf['bat']['stat'] . ' ' . $perf['bat']['small'];
		}
		if ( isset( $perf['bowl'] ) ) {
			$lines[] = 'Top wicket taker: ' . $perf['bowl']['name'] . ' ' . $perf['bowl']['stat'] . ' ' . $perf['bowl']['small'];
		}
		$cap = self::caption( 'FULL TIME | ' . ( self::tour( $m ) ? self::tour( $m ) . ': ' : '' ) . self::short_result( $m['status'] ) . '.', $lines, 'sum:' . $m['id'] );
		SF_Poster::queue( 'summary', 'summary:' . $m['id'], $d, $cap, $extra + array( 'delay' => 3 * MINUTE_IN_SECONDS ) );
	}

	/** Pick one standout player with a simple points score: runs plus 25 per wicket. */
	private static function top_performer( $p, $winner = '' ) {
		$score = array();
		foreach ( $p['batters'] as $b ) {
			$k = $b['id'];
			$score[ $k ] = isset( $score[ $k ] ) ? $score[ $k ] : array( 'name' => $b['name'], 'team' => $b['team'], 'pts' => 0, 'bat' => null, 'bowl' => null );
			$score[ $k ]['pts'] += $b['r'];
			$score[ $k ]['bat']  = $b;
		}
		foreach ( $p['bowlers'] as $b ) {
			$k = $b['id'];
			$score[ $k ] = isset( $score[ $k ] ) ? $score[ $k ] : array( 'name' => $b['name'], 'team' => $b['team'], 'pts' => 0, 'bat' => null, 'bowl' => null );
			$score[ $k ]['pts'] += 25 * $b['w'];
			$score[ $k ]['bowl'] = $b;
		}
		// When there is a winner, the standout player comes from the winning side.
		if ( '' !== $winner ) {
			$from_winner = array_filter( $score, function ( $s ) use ( $winner ) {
				return $s['team'] === $winner && $s['pts'] > 0;
			} );
			if ( ! empty( $from_winner ) ) {
				$score = $from_winner;
			}
		}
		$best = null;
		foreach ( $score as $s ) {
			if ( ! $best || $s['pts'] > $best['pts'] ) {
				$best = $s;
			}
		}
		return $best;
	}

	private static function post_potm( $m, $p, $extra ) {
		list( $winner ) = SF_API::parse_result( $m );
		$top = self::top_performer( $p, $winner );
		if ( ! $top || $top['pts'] <= 0 ) {
			return;
		}
		$team = SF_Teams::resolve( $top['team'] );
		$bat_pts  = $top['bat'] ? $top['bat']['r'] : 0;
		$bowl_pts = $top['bowl'] ? 25 * $top['bowl']['w'] : 0;
		if ( $bat_pts >= $bowl_pts ) {
			$b     = $top['bat'];
			$stat  = $b['r'] . ( $b['out'] ? '' : '*' );
			$small = '(' . $b['b'] . ' balls)';
			$chips = array( $b['4s'] . ' fours', $b['6s'] . ' sixes', 'Strike rate ' . number_format( $b['sr'], 1 ) );
			$cap   = $top['name'] . ', ' . $stat . ' off ' . $b['b'] . ' balls';
		} else {
			$b     = $top['bowl'];
			$stat  = $b['w'] . '/' . $b['r'];
			$small = '(' . self::overs( $b['o'] ) . ' overs)';
			$chips = array( 'Economy ' . number_format( $b['eco'], 2 ), $b['m'] . ( 1 === $b['m'] ? ' maiden' : ' maidens' ), $b['w'] . ( 1 === $b['w'] ? ' wicket' : ' wickets' ) );
			$cap   = $top['name'] . ', ' . $stat . ' in ' . self::overs( $b['o'] ) . ' overs';
		}
		$d = array(
			'tournament' => self::tour( $m ),
			'meta'       => $m['meta'],
			'pill'       => 'TOP PERFORMER',
			'name'       => $top['name'],
			'mono'       => strtoupper( mb_substr( preg_replace( '/[^A-Za-z ]/', '', $top['name'] ), 0, 1 ) . ( preg_match( '/\s(\S)\S*$/', $top['name'], $x ) ? $x[1] : '' ) ),
			'team'       => $team,
			'stat'       => $stat,
			'small'      => $small,
			'chips'      => $chips,
			'status'     => self::result_status( $m ),
		) + self::foot( 'Agree? Tell us your player of the day' );
		$text = self::caption( 'TOP PERFORMER | ' . $cap . ' in ' . self::short_result( $m['status'] ) . '.', array(), 'potm:' . $m['id'] );
		SF_Poster::queue( 'potm', 'potm:' . $m['id'], $d, $text, $extra + array( 'delay' => 18 * MINUTE_IN_SECONDS ) );
	}

	/* ------------------------------------------------------------------ */
	/* Milestones and records                                              */
	/* ------------------------------------------------------------------ */

	private static function record_beaten( $series, $key, $value, $lower_is_better ) {
		$all  = get_option( 'sf_records', array() );
		$all  = is_array( $all ) ? $all : array();
		$prev = isset( $all[ $series ][ $key ] ) ? $all[ $series ][ $key ] : null;
		$beat = null !== $prev && ( $lower_is_better ? $value < $prev : $value > $prev );
		if ( null === $prev || $beat ) {
			$all[ $series ][ $key ] = $value;
			update_option( 'sf_records', $all, false );
		}
		return $beat;
	}

	private static function scan_milestones( $m, $sc ) {
		$o  = SF_Options::all();
		$p  = SF_API::parse_scorecard( $sc, $m );
		$mi = $o['milestones'];
		$extra = array( 'match_id' => $m['id'], 'series_id' => $m['series_id'] );
		$tour  = self::tour( $m );
		$of    = '' !== $tour ? ' of ' . $tour : '';
		$series_key = '' !== $m['series_id'] ? $m['series_id'] : 'all';

		foreach ( $p['batters'] as $b ) {
			if ( empty( $mi['century'] ) || $b['r'] < 100 ) {
				continue;
			}
			$dedupe = 'century:' . $m['id'] . ':' . $b['id'];
			if ( SF_DB::exists( $dedupe ) ) {
				continue;
			}
			$record = ! empty( $mi['record'] ) && $b['b'] > 0 && self::record_beaten( $series_key, 'fast100', $b['b'], true );
			$team   = SF_Teams::resolve( $b['team'] );
			$status = $record ? array( array( 'Fastest hundred', true ), array( $of, false ) ) : array( array( $b['team'] . ' batter ', false ), array( $b['name'], true ), array( ' reaches three figures', false ) );
			$d = array(
				'tournament' => $tour,
				'meta'       => $m['meta'],
				'name'       => $b['name'],
				'mono'       => strtoupper( mb_substr( $b['name'], 0, 1 ) . ( preg_match( '/\s(\S)\S*$/', $b['name'], $x ) ? $x[1] : '' ) ),
				'team'       => $team,
				'stat'       => $b['r'] . ( $b['out'] ? '' : '*' ),
				'small'      => '(' . $b['b'] . ' balls)',
				'chips'      => array( $b['4s'] . ' fours', $b['6s'] . ' sixes', 'Strike rate ' . number_format( $b['sr'], 1 ) ),
				'status'     => $status,
			) + self::foot( 'Great knock? Tell us in the comments' );
			$cap = self::caption( 'CENTURY | ' . $b['name'] . ' reaches ' . $b['r'] . ' off ' . $b['b'] . ' balls' . ( $record ? ', the fastest hundred' . $of : '' ) . '.', array(), 'c:' . $b['id'] );
			SF_Poster::queue( 'century', $dedupe, $d, $cap, $extra );
		}

		foreach ( $p['bowlers'] as $b ) {
			if ( empty( $mi['fivefor'] ) || $b['w'] < 5 ) {
				continue;
			}
			$dedupe = 'fivefor:' . $m['id'] . ':' . $b['id'];
			if ( SF_DB::exists( $dedupe ) ) {
				continue;
			}
			$fig    = $b['w'] * 1000 - $b['r'];
			$record = ! empty( $mi['record'] ) && self::record_beaten( $series_key, 'bestfig', $fig, false );
			$team   = SF_Teams::resolve( $b['team'] );
			$status = $record ? array( array( 'Best figures', true ), array( $of, false ) ) : array( array( $b['name'], true ), array( ' takes five wickets', false ) );
			$d = array(
				'tournament' => $tour,
				'meta'       => $m['meta'],
				'name'       => $b['name'],
				'mono'       => strtoupper( mb_substr( $b['name'], 0, 1 ) . ( preg_match( '/\s(\S)\S*$/', $b['name'], $x ) ? $x[1] : '' ) ),
				'team'       => $team,
				'stat'       => $b['w'] . '/' . $b['r'],
				'small'      => '(' . self::overs( $b['o'] ) . ' overs)',
				'chips'      => array( 'Economy ' . number_format( $b['eco'], 2 ), $b['m'] . ( 1 === $b['m'] ? ' maiden' : ' maidens' ), $b['w'] . ' wickets' ),
				'status'     => $status,
			) + self::foot( 'Spell of the day? Tell us in the comments' );
			$cap = self::caption( 'FIVE-WICKET HAUL | ' . $b['name'] . ' takes ' . $b['w'] . '/' . $b['r'] . ' in ' . self::overs( $b['o'] ) . ' overs' . ( $record ? ', the best figures' . $of : '' ) . '.', array(), 'f:' . $b['id'] );
			SF_Poster::queue( 'fivefor', $dedupe, $d, $cap, $extra );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Daily, weekly and monthly jobs                                      */
	/* ------------------------------------------------------------------ */

	/** All matches seen in the last list that start today in the site's time zone. */
	private static function todays_matches() {
		$list = SF_API::last_matches();
		$out  = array();
		$today = wp_date( 'Y-m-d' );
		foreach ( $list[1] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$m = SF_API::normalize_match( $raw );
			if ( '' === $m['id'] || ! SF_API::wanted( $m ) || ! $m['start'] ) {
				continue;
			}
			if ( wp_date( 'Y-m-d', $m['start'] ) === $today ) {
				$out[] = $m;
			}
		}
		usort( $out, function ( $a, $b ) {
			return $a['start'] <=> $b['start'];
		} );
		return $out;
	}

	/** Count the matches in the last list and how many the plugin covers. Returns [seen, covered list, why skipped]. */
	public static function coverage() {
		$list    = SF_API::last_matches();
		$seen    = 0;
		$covered = array();
		$skip    = array( 'women' => 0, 'other' => 0 );
		foreach ( $list[1] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$m = SF_API::normalize_match( $raw );
			if ( '' === $m['id'] ) {
				continue;
			}
			$seen++;
			if ( SF_API::wanted( $m ) ) {
				$covered[] = $m;
			} elseif ( SF_API::is_women( $m ) ) {
				$skip['women']++;
			} else {
				$skip['other']++;
			}
		}
		return array( $seen, $covered, $skip );
	}

	/** Post today's matches. Returns a plain sentence that says what happened, also when nothing was posted. */
	public static function daily() {
		if ( ! SF_Options::ready() ) {
			return 'Finish Setup first.';
		}
		update_option( 'sf_daily_done', wp_date( 'Y-m-d' ), false );
		$list = SF_API::current_matches( true );
		if ( is_wp_error( $list ) ) {
			SF_Log::add( 'Daily fixtures: ' . $list->get_error_message(), 'error' );
			return 'The cricket API did not answer: ' . $list->get_error_message();
		}
		if ( ! SF_Options::job( 'fixtures' ) ) {
			SF_Log::add( "Daily fixtures: the Today's matches job is switched off on the Engage screen." );
			return "The Today's matches job is switched off on the Engage screen.";
		}
		list( $seen, , $skip ) = self::coverage();
		$today = self::todays_matches();
		if ( empty( $today ) ) {
			$why = $seen . ' matches in the API list; none start today in your covered matches (' . $skip['women'] . " women's and " . $skip['other'] . ' outside your tournaments or top teams were skipped).';
			SF_Log::add( 'Daily fixtures: ' . $why );
			return 'No post made. ' . $why;
		}
		$rows = array();
		foreach ( array_slice( $today, 0, 3 ) as $m ) {
			$rows[] = array(
				'st'  => self::local_time( $m['start'] ),
				'a'   => SF_Teams::resolve( $m['teams'][0]['name'], $m['teams'][0]['short'] ),
				'b'   => SF_Teams::resolve( $m['teams'][1]['name'], $m['teams'][1]['short'] ),
				'sub' => $m['venue'],
			);
		}
		$d = array(
			'tournament' => '',
			'meta'       => wp_date( 'l j F' ) . ', ' . count( $today ) . ( 1 === count( $today ) ? ' match' : ' matches' ) . ' today',
			'rows'       => $rows,
		) + self::foot();
		if ( count( $today ) > 3 ) {
			$d['status'] = array( array( ( count( $today ) - 3 ) . ' more matches today. ', true ), array( 'Full list in the pinned post.', false ) );
		}
		$first = $today[0];
		$cap   = self::caption( "TODAY | " . count( $today ) . ( 1 === count( $today ) ? ' match' : ' matches' ) . ' on the schedule. First ball at ' . self::local_time( $first['start'] ) . '.', array(), 'day:' . wp_date( 'Y-m-d' ) );
		$id = SF_Poster::queue( 'fixtures', 'fixtures:' . wp_date( 'Y-m-d' ), $d, $cap );
		self::update_pinned( $today, true );
		SF_Poster::publish_due();
		SF_Log::add( "Daily fixtures: queued today's post for " . count( $today ) . ' matches.' );
		return "Today's post for " . count( $today ) . ( 1 === count( $today ) ? ' match' : ' matches' ) . ' is queued. If it is not on Facebook yet, check the Post queue.';
	}

	public static function weekly() {
		if ( ! SF_Options::ready() || ! SF_Options::job( 'table' ) ) {
			return;
		}
		$followed = SF_Options::get( 'followed', array() );
		$n = 0;
		foreach ( $followed as $sid => $name ) {
			if ( $n >= 2 || ! SF_API::budget_ok() ) {
				break;
			}
			$pts = SF_API::series_points( $sid );
			if ( is_wp_error( $pts ) || empty( $pts ) ) {
				continue;
			}
			$rows = array();
			foreach ( $pts as $r ) {
				if ( empty( $r['teamname'] ) ) {
					continue;
				}
				$rows[] = array(
					'name' => $r['teamname'],
					'short' => isset( $r['shortname'] ) ? $r['shortname'] : '',
					'p'    => isset( $r['matches'] ) ? (int) $r['matches'] : 0,
					'w'    => isset( $r['wins'] ) ? (int) $r['wins'] : 0,
					'l'    => isset( $r['loss'] ) ? (int) $r['loss'] : 0,
					'nr'   => isset( $r['nr'] ) ? (int) $r['nr'] : 0,
					'pts'  => isset( $r['points'] ) ? (int) $r['points'] : 0,
				);
			}
			usort( $rows, function ( $a, $b ) {
				return $b['pts'] <=> $a['pts'] ?: $b['w'] <=> $a['w'];
			} );
			$card_rows = array();
			foreach ( array_slice( $rows, 0, 8 ) as $r ) {
				$card_rows[] = array( 'team' => SF_Teams::resolve( $r['name'], $r['short'] ), 'vals' => array( (string) $r['p'], (string) $r['w'], (string) $r['l'], (string) $r['nr'], (string) $r['pts'] ) );
			}
			$lead = $rows[0];
			$d = array(
				'tournament' => $name,
				'meta'       => 'week of ' . wp_date( 'j F' ),
				'pill'       => 'WEEKLY',
				'title'      => 'Points table',
				'cols'       => array( 'P', 'W', 'L', 'NR', 'Pts' ),
				'rows'       => $card_rows,
				'status'     => array( array( $lead['name'], true ), array( ' lead on ' . $lead['pts'] . ' points', false ) ),
			) + self::foot( 'Who tops the table next week? Comment your pick' );
			$cap = self::caption( 'POINTS TABLE | ' . $name . ': ' . $lead['name'] . ' lead on ' . $lead['pts'] . ' points.', array(), 'tab:' . $sid );
			SF_Poster::queue( 'table', 'table:' . $sid . ':' . wp_date( 'o-W' ), $d, $cap, array( 'series_id' => $sid ) );
			$n++;
		}
		SF_Poster::publish_due();
	}

	/**
	 * Monthly team ranking, worked out from the results this plugin saw. It is not an official ranking.
	 * Two points for a win, one for a tie or no result.
	 */
	public static function monthly() {
		if ( ! SF_Options::job( 'ranking' ) ) {
			return;
		}
		$tz    = wp_timezone();
		$first = new DateTime( 'first day of last month', $tz );
		$last  = new DateTime( 'last day of last month', $tz );
		$rows  = SF_DB::results_between( $first->format( 'Y-m-d' ), $last->format( 'Y-m-d' ) );
		$data  = self::build_ranking( $rows, $first->format( 'F Y' ) );
		if ( ! $data ) {
			SF_Log::add( 'Monthly ranking skipped: fewer than 6 matches were tracked last month.', 'info' );
			return;
		}
		$cap = self::caption( 'MONTHLY TEAM RANKING | ' . $first->format( 'F Y' ) . '. ' . $data[2] . ' finish on top.', array( 'Worked out from the matches we covered this month: 2 points for a win, 1 for a tie or no result.' ), 'rank:' . $first->format( 'Y-m' ) );
		SF_Poster::queue( 'ranking', 'ranking:' . $first->format( 'Y-m' ), $data[0], $cap );
		SF_Poster::publish_due();
	}

	/** Build ranking card data from result rows. Returns [data, rows, leader] or null. */
	public static function build_ranking( array $results, $month_label ) {
		if ( count( $results ) < 6 ) {
			return null;
		}
		$t = array();
		$touch = function ( $name ) use ( &$t ) {
			if ( ! isset( $t[ $name ] ) ) {
				$t[ $name ] = array( 'p' => 0, 'w' => 0, 'l' => 0, 'pts' => 0 );
			}
		};
		foreach ( $results as $r ) {
			foreach ( array( $r['team_a'], $r['team_b'] ) as $n ) {
				$touch( $n );
				$t[ $n ]['p']++;
			}
			if ( 'win' === $r['result_type'] && '' !== $r['winner'] ) {
				$loser = $r['winner'] === $r['team_a'] ? $r['team_b'] : $r['team_a'];
				$t[ $r['winner'] ]['w']++;
				$t[ $r['winner'] ]['pts'] += 2;
				$t[ $loser ]['l']++;
			} else {
				$t[ $r['team_a'] ]['pts']++;
				$t[ $r['team_b'] ]['pts']++;
			}
		}
		$list = array();
		foreach ( $t as $name => $v ) {
			$v['name'] = $name;
			$v['win']  = $v['p'] > 0 ? round( $v['w'] * 100 / $v['p'] ) : 0;
			$list[]    = $v;
		}
		usort( $list, function ( $a, $b ) {
			return $b['pts'] <=> $a['pts'] ?: $b['win'] <=> $a['win'] ?: $b['w'] <=> $a['w'] ?: strcmp( $a['name'], $b['name'] );
		} );
		$card_rows = array();
		foreach ( array_slice( $list, 0, 8 ) as $r ) {
			$card_rows[] = array( 'team' => SF_Teams::resolve( $r['name'] ), 'vals' => array( (string) $r['p'], (string) $r['w'], (string) $r['l'], $r['win'] . '%', (string) $r['pts'] ) );
		}
		$d = array(
			'tournament' => '',
			'meta'       => $month_label . ', from matches tracked on this Page',
			'pill'       => strtoupper( explode( ' ', $month_label )[0] ),
			'title'      => 'Monthly team ranking',
			'cols'       => array( 'P', 'W', 'L', 'Win', 'Pts' ),
			'rows'       => $card_rows,
			'status'     => array( array( $list[0]['name'], true ), array( ' finish the month on top', false ) ),
		) + self::foot( 'Who finishes top next month? Comment your pick' );
		return array( $d, $list, $list[0]['name'] );
	}

	/* ------------------------------------------------------------------ */
	/* Scoreboard digest and the pinned post                               */
	/* ------------------------------------------------------------------ */

	private static function maybe_digest( $matches, $live, $card_ids, $o ) {
		if ( empty( $o['digest'] ) ) {
			return;
		}
		$extra_live = array_filter( $live, function ( $m ) use ( $card_ids ) {
			return ! in_array( $m['id'], $card_ids, true );
		} );
		if ( count( $live ) < 3 && empty( $extra_live ) ) {
			return;
		}
		$every = max( 10, (int) $o['digest_every'] ) * MINUTE_IN_SECONDS;
		if ( time() - (int) get_option( 'sf_last_digest', 0 ) < $every ) {
			return;
		}
		$rows = array();
		$push = function ( $m, $st ) use ( &$rows ) {
			$cur = null;
			foreach ( array_reverse( $m['order'] ) as $idx ) {
				if ( isset( $m['scores'][ $idx ] ) ) {
					$cur = $m['scores'][ $idx ];
					break;
				}
			}
			$row = array(
				'st'    => $st,
				'a'     => SF_Teams::resolve( $m['teams'][0]['name'], $m['teams'][0]['short'] ),
				'b'     => SF_Teams::resolve( $m['teams'][1]['name'], $m['teams'][1]['short'] ),
				'score' => '',
				'sub'   => '',
			);
			if ( 'LIVE' === $st && $cur ) {
				$row['score'] = $cur['r'] . '/' . $cur['w'];
				$row['sub']   = self::overs( $cur['o'] ) . ' ov';
			} elseif ( 'FT' === $st ) {
				$row['sub'] = self::short_result( $m['status'] );
			} else {
				$row['sub'] = self::tour( $m ) ? self::tour( $m ) : $m['venue'];
			}
			$rows[] = $row;
		};
		foreach ( $live as $m ) {
			$push( $m, 'LIVE' );
		}
		$upcoming = array_filter( $matches, function ( $m ) {
			return ! $m['started'] && $m['start'] && wp_date( 'Y-m-d', $m['start'] ) === wp_date( 'Y-m-d' );
		} );
		usort( $upcoming, function ( $a, $b ) {
			return $a['start'] <=> $b['start'];
		} );
		foreach ( $upcoming as $m ) {
			$push( $m, self::local_time( $m['start'] ) );
		}
		$ended = array_filter( $matches, function ( $m ) {
			return $m['ended'];
		} );
		foreach ( array_slice( array_values( $ended ), 0, 2 ) as $m ) {
			$push( $m, 'FT' );
		}
		$total = count( $rows );
		$d     = array(
			'tournament' => '',
			'meta'       => wp_date( 'l j F' ) . ', ' . $total . ' matches',
			'rows'       => array_slice( $rows, 0, 9 ),
		) + self::foot();
		if ( $total > 9 ) {
			$d['status'] = array( array( ( $total - 9 ) . ' more matches. ', true ), array( 'Full list in the pinned post.', false ) );
		}
		$lines = array();
		foreach ( array_slice( $live, 0, 4 ) as $m ) {
			$lines[] = trim( self::headline_score( $m ), '.' );
		}
		$cap = self::caption( 'SCOREBOARD | ' . count( $live ) . ' matches live now.', $lines, 'digest:' . wp_date( 'Y-m-d H' ) );
		SF_Poster::queue( 'digest', 'digest:' . wp_date( 'Y-m-d-H-i' ), $d, $cap );
		update_option( 'sf_last_digest', time(), false );
	}

	private static function pinned_text( $matches ) {
		$lines = array( "Today's matches (updated " . wp_date( 'H:i' ) . ')', '' );
		$n     = 0;
		foreach ( $matches as $m ) {
			if ( $n >= 12 ) {
				break;
			}
			$what = $m['ended'] ? self::short_result( $m['status'] ) : ( $m['started'] ? self::live_text( $m ) : 'starts soon' );
			if ( ! $m['started'] ) {
				$what = 'starts at ' . self::local_time( $m['start'] );
			}
			$lines[] = self::local_time( $m['start'] ) . ' ' . $m['teams'][0]['name'] . ' v ' . $m['teams'][1]['name'] . ': ' . $what;
			$n++;
		}
		if ( count( $matches ) > 12 ) {
			$lines[] = '';
			$lines[] = ( count( $matches ) - 12 ) . ' more matches today.';
		}
		$q = self::question( 'pin:' . wp_date( 'Y-m-d' ) );
		if ( '' !== $q ) {
			$lines[] = '';
			$lines[] = $q . ' Tell us in the comments.';
		}
		return implode( "\n", $lines );
	}

	private static function live_text( $m ) {
		$parts = array();
		foreach ( $m['order'] as $idx ) {
			if ( isset( $m['scores'][ $idx ] ) ) {
				$s       = $m['scores'][ $idx ];
				$parts[] = SF_Teams::initials( $m['teams'][ $idx ]['name'], $m['teams'][ $idx ]['short'] ) . ' ' . $s['r'] . '/' . $s['w'] . ' (' . self::overs( $s['o'] ) . ' ov)';
			}
		}
		return empty( $parts ) ? 'live' : implode( ', ', $parts ) . ', live';
	}

	/** Edit the pinned post if it exists and the text changed. */
	private static function update_pinned( $matches, $force = false ) {
		$id = SF_Options::get( 'pinned_post_id', '' );
		if ( '' === $id || ! SF_Options::job( 'pinned' ) ) {
			return;
		}
		$today = array_values( array_filter( $matches, function ( $m ) {
			return $m['start'] && wp_date( 'Y-m-d', $m['start'] ) === wp_date( 'Y-m-d' );
		} ) );
		usort( $today, function ( $a, $b ) {
			return $a['start'] <=> $b['start'];
		} );
		if ( empty( $today ) ) {
			return;
		}
		$text = self::pinned_text( $today );
		$hash = sha1( preg_replace( '/\(updated \d\d:\d\d\)/', '', $text ) );
		if ( ! $force && get_option( 'sf_pinned_hash' ) === $hash ) {
			return;
		}
		$res = SF_Facebook::edit( $id, $text );
		if ( is_wp_error( $res ) ) {
			SF_Log::add( 'Could not edit the pinned post: ' . $res->get_error_message(), 'error' );
			return;
		}
		update_option( 'sf_pinned_hash', $hash, false );
	}

	/** Create the text post you then pin on your Page. Returns the Facebook post id or WP_Error. */
	public static function create_pinned() {
		$matches = self::todays_matches();
		$text    = empty( $matches ) ? "Today's matches\n\nThe list will appear here when matches are found." : self::pinned_text( $matches );
		$res     = SF_Facebook::feed( $text );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		SF_Options::update( array( 'pinned_post_id' => (string) $res ) );
		delete_option( 'sf_pinned_hash' );
		return $res;
	}
}
