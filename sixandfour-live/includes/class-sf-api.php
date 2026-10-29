<?php
/**
 * Cricket data client for CricketData.org (api.cricapi.com).
 *
 * All parsing of the provider's response is in this class and in normalize_*(), so a
 * different provider only needs a different version of this file.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_API {

	const STATS = 'sf_api_stats';

	/** Seconds a forced check reuses the list it just fetched. Tests set it to 0. */
	public static $reuse = 60;

	private static function base() {
		return apply_filters( 'sf_api_base', 'https://api.cricapi.com/v1/' );
	}

	/** The saved API keys, main first. Index 1 is the main key, 2 to 4 are backups. */
	public static function keys() {
		$o   = SF_Options::all();
		$out = array();
		foreach ( array( 1 => 'api_key', 2 => 'api_key2', 3 => 'api_key3', 4 => 'api_key4' ) as $i => $name ) {
			if ( ! empty( $o[ $name ] ) ) {
				$out[ $i ] = $o[ $name ];
			}
		}
		return $out;
	}

	private static function fresh_stats() {
		return array( 'day' => gmdate( 'Y-m-d' ), 'keys' => array() );
	}

	private static function raw_stats() {
		$s = get_option( self::STATS, array() );
		if ( is_array( $s ) && isset( $s['day'], $s['hits'] ) && ! isset( $s['keys'] ) ) { // Saved by version 1.1.0 or older.
			$s = array( 'day' => $s['day'], 'keys' => array( 1 => array( 'hits' => (int) $s['hits'], 'limit' => (int) $s['limit'] ) ) );
		}
		return ( is_array( $s ) && isset( $s['day'], $s['keys'] ) && gmdate( 'Y-m-d' ) === $s['day'] ) ? $s : self::fresh_stats();
	}

	private static function key_state( array $s, $i ) {
		$k = isset( $s['keys'][ $i ] ) ? $s['keys'][ $i ] : array();
		return array(
			'hits'  => isset( $k['hits'] ) ? (int) $k['hits'] : 0,
			'limit' => isset( $k['limit'] ) ? (int) $k['limit'] : (int) SF_Options::get( 'api_limit', 100 ),
			'dead'  => ! empty( $k['dead'] ),
		);
	}

	/** A key can be used while it is under 90% of its daily limit and has not been refused. */
	private static function usable( array $st, $reserve = 0.1 ) {
		return ! $st['dead'] && $st['hits'] < floor( $st['limit'] * ( 1 - $reserve ) );
	}

	private static function pick_key() {
		$s = self::raw_stats();
		foreach ( self::keys() as $i => $key ) {
			if ( self::usable( self::key_state( $s, $i ) ) ) {
				return array( $i, $key );
			}
		}
		return null;
	}

	/** Send a request. Uses the next backup key when one is used up. Returns the decoded JSON array or WP_Error. */
	private static function request( $endpoint, array $params = array() ) {
		if ( ! self::keys() ) {
			return new WP_Error( 'sf_api_key', 'Add your cricket API key in Setup.' );
		}
		for ( $try = 0; $try < 4; $try++ ) {
			$pick = self::pick_key();
			if ( ! $pick ) {
				return new WP_Error( 'sf_api_budget', 'The daily API limit is nearly used up on every key, so no more calls today.' );
			}
			list( $i, $key ) = $pick;
			$params['apikey'] = $key;
			$res              = wp_remote_get( add_query_arg( $params, self::base() . $endpoint ), array( 'timeout' => 20, 'redirection' => 2, 'sslverify' => true ) );
			if ( is_wp_error( $res ) ) {
				return new WP_Error( $res->get_error_code(), SF_Secrets::scrub( $res->get_error_message() ) );
			}
			$code = wp_remote_retrieve_response_code( $res );
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
				return new WP_Error( 'sf_api_http', 'The cricket API answered with status ' . $code . '.' );
			}
			self::track( $body, $i );
			if ( isset( $body['status'] ) && 'success' !== $body['status'] ) {
				$why = isset( $body['reason'] ) ? (string) $body['reason'] : 'unknown reason';
				if ( preg_match( '/limit|exceed|quota|too many/i', $why ) ) {
					self::mark_dead( $i );
					SF_Log::add( 'API key ' . $i . ' is used up for today. Trying the next key.', 'warning' );
					continue;
				}
				return new WP_Error( 'sf_api_fail', 'The cricket API refused the request: ' . SF_Secrets::scrub( $why ) );
			}
			return $body;
		}
		return new WP_Error( 'sf_api_budget', 'Every cricket API key is used up for today.' );
	}

	private static function mark_dead( $i ) {
		$s = self::raw_stats();
		$s['keys'][ $i ] = array_merge( self::key_state( $s, $i ), array( 'dead' => true ) );
		update_option( self::STATS, $s, false );
	}

	private static function track( array $body, $i ) {
		$s = self::raw_stats();
		$k = self::key_state( $s, $i );
		if ( isset( $body['info']['hitsToday'] ) ) {
			$k['hits'] = (int) $body['info']['hitsToday'];
		} else {
			$k['hits']++;
		}
		if ( isset( $body['info']['hitsLimit'] ) ) {
			$k['limit'] = (int) $body['info']['hitsLimit'];
		}
		if ( $k['hits'] >= $k['limit'] ) {
			$k['dead'] = true;
		}
		$s['keys'][ $i ] = $k;
		update_option( self::STATS, $s, false );
	}

	/** Calls used today and the daily total, added up over every saved key. */
	public static function stats() {
		$s     = self::raw_stats();
		$hits  = 0;
		$limit = 0;
		$keys  = self::keys();
		foreach ( $keys as $i => $key ) {
			$k      = self::key_state( $s, $i );
			$hits  += $k['hits'];
			$limit += $k['limit'];
		}
		if ( ! $keys ) {
			$limit = (int) SF_Options::get( 'api_limit', 100 );
		}
		return array( 'day' => $s['day'], 'hits' => $hits, 'limit' => $limit, 'keys' => count( $keys ) );
	}

	/** True while at least one key still has calls to spare. */
	public static function budget_ok() {
		return null !== self::pick_key();
	}

	/** Calls left before the 10% reserve, over every usable key. */
	public static function remaining() {
		$s   = self::raw_stats();
		$sum = 0;
		foreach ( self::keys() as $i => $key ) {
			$k = self::key_state( $s, $i );
			if ( ! $k['dead'] ) {
				$sum += max( 0, (int) floor( $k['limit'] * 0.9 ) - $k['hits'] );
			}
		}
		return $sum;
	}

	/* ------------------------------------------------------------------ */
	/* Which matches to cover                                              */
	/* ------------------------------------------------------------------ */

	public static function is_women( array $m ) {
		$text = $m['name'] . ' ' . $m['teams'][0]['name'] . ' ' . $m['teams'][1]['name'];
		return (bool) preg_match( '/\b(women|womens|woman|ladies)\b|\(W\)|\bW\s*$|\bW\s+vs\b|-W\b/i', $text );
	}

	private static function team_key( $name ) {
		$n = strtolower( trim( preg_replace( '/[^a-z ]/i', '', (string) $name ) ) );
		$alias = array( 'uae' => 'united arab emirates', 'usa' => 'united states of america', 'united states' => 'united states of america', 'us' => 'united states of america' );
		return isset( $alias[ $n ] ) ? $alias[ $n ] : $n;
	}

	/** Is this match one we should post about? Tournaments you follow always count, except women's matches. */
	public static function wanted( array $m ) {
		if ( SF_Options::get( 'skip_women', 1 ) && self::is_women( $m ) ) {
			return false;
		}
		// A tournament you follow is always covered. With "top teams" on, matches between those teams are covered too, in any series.
		$followed = SF_Options::get( 'followed', array() );
		if ( ! empty( $followed ) && isset( $followed[ $m['series_id'] ] ) ) {
			return true;
		}
		if ( ! SF_Options::get( 'top_only', 1 ) ) {
			return empty( $followed );
		}
		$top = array();
		foreach ( explode( "\n", (string) SF_Options::get( 'top_teams', '' ) ) as $t ) {
			if ( '' !== trim( $t ) ) {
				$top[ self::team_key( $t ) ] = true;
			}
		}
		foreach ( $m['teams'] as $t ) {
			if ( ! isset( $top[ self::team_key( $t['name'] ) ] ) ) {
				return false;
			}
		}
		return true;
	}

	/** All current and recent matches in one call. Cached for two minutes. */
	public static function current_matches( $fresh = false ) {
		$cached = get_transient( 'sf_current_matches' );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached;
		}
		// Pressing Check now twice in a row should not cost two calls.
		$last = get_option( 'sf_last_matches', array() );
		if ( $fresh && is_array( $last ) && isset( $last[0], $last[1] ) && time() - (int) $last[0] < self::$reuse ) {
			return $last[1];
		}
		$res = self::request( 'currentMatches', array( 'offset' => 0 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$list = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
		set_transient( 'sf_current_matches', $list, 2 * MINUTE_IN_SECONDS );
		update_option( 'sf_last_matches', array( time(), $list ), false );
		return $list;
	}

	/** The last matches list we saw, without any API call. */
	public static function last_matches() {
		$l = get_option( 'sf_last_matches', array() );
		return ( is_array( $l ) && isset( $l[1] ) ) ? array( (int) $l[0], $l[1] ) : array( 0, array() );
	}

	public static function scorecard( $match_id, $fresh = false ) {
		$key    = 'sf_sc_' . md5( $match_id );
		$cached = get_transient( $key );
		if ( ! $fresh && is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}
		$res = self::request( 'match_scorecard', array( 'id' => $match_id ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
		if ( ! empty( $data ) ) {
			set_transient( $key, $data, 5 * MINUTE_IN_SECONDS );
		}
		return $data;
	}

	public static function series_search( $q ) {
		$res = self::request( 'series', array( 'offset' => 0, 'search' => $q ) );
		return is_wp_error( $res ) ? $res : ( isset( $res['data'] ) ? $res['data'] : array() );
	}

	public static function series_points( $series_id ) {
		$res = self::request( 'series_points', array( 'id' => $series_id ) );
		return is_wp_error( $res ) ? $res : ( isset( $res['data'] ) ? $res['data'] : array() );
	}

	/** A cheap check that the key works. Costs one call. */
	public static function ping() {
		$res = self::request( 'currentMatches', array( 'offset' => 0 ) );
		return is_wp_error( $res ) ? $res : true;
	}

	/* ------------------------------------------------------------------ */
	/* Normalising                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Turn one provider match into the structure the engine uses.
	 *
	 * @return array
	 */
	public static function normalize_match( array $m ) {
		$teams = array();
		if ( ! empty( $m['teamInfo'] ) && is_array( $m['teamInfo'] ) ) {
			foreach ( $m['teamInfo'] as $t ) {
				$teams[] = array( 'name' => isset( $t['name'] ) ? (string) $t['name'] : '', 'short' => isset( $t['shortname'] ) ? (string) $t['shortname'] : '' );
			}
		} elseif ( ! empty( $m['teams'] ) && is_array( $m['teams'] ) ) {
			foreach ( $m['teams'] as $n ) {
				$teams[] = array( 'name' => (string) $n, 'short' => '' );
			}
		}
		$teams = array_slice( $teams, 0, 2 );
		while ( count( $teams ) < 2 ) {
			$teams[] = array( 'name' => 'TBC', 'short' => '' );
		}

		$innings = array();
		if ( ! empty( $m['score'] ) && is_array( $m['score'] ) ) {
			foreach ( $m['score'] as $s ) {
				$innings[] = array(
					'inning' => isset( $s['inning'] ) ? (string) $s['inning'] : '',
					'r'      => isset( $s['r'] ) ? (int) $s['r'] : 0,
					'w'      => isset( $s['w'] ) ? (int) $s['w'] : 0,
					'o'      => isset( $s['o'] ) ? (float) $s['o'] : 0.0,
				);
			}
		}
		// Latest innings per team, in batting order.
		$by_team = array();
		$order   = array();
		foreach ( $innings as $i ) {
			foreach ( $teams as $idx => $t ) {
				if ( '' !== $t['name'] && 0 === stripos( $i['inning'], $t['name'] ) ) {
					$by_team[ $idx ] = $i;
					if ( ! in_array( $idx, $order, true ) ) {
						$order[] = $idx;
					}
				}
			}
		}
		foreach ( array( 0, 1 ) as $idx ) {
			if ( ! in_array( $idx, $order, true ) ) {
				$order[] = $idx;
			}
		}
		// Every innings of each team, in order. A Test has up to two per side.
		$team_inns = array( 0 => array(), 1 => array() );
		foreach ( $innings as $i ) {
			foreach ( $teams as $idx => $t ) {
				if ( '' !== $t['name'] && 0 === stripos( $i['inning'], $t['name'] ) ) {
					$team_inns[ $idx ][] = $i;
				}
			}
		}

		$name   = isset( $m['name'] ) ? (string) $m['name'] : '';
		$suffix = '';
		if ( false !== strpos( $name, ',' ) ) {
			$suffix = trim( substr( $name, strpos( $name, ',' ) + 1 ) );
		}
		$venue = isset( $m['venue'] ) ? trim( explode( ',', (string) $m['venue'] )[0] ) : '';
		$type  = isset( $m['matchType'] ) ? strtoupper( (string) $m['matchType'] ) : '';
		$meta  = implode( ', ', array_filter( array( $type, $suffix, $venue ) ) );

		$ts = 0;
		if ( ! empty( $m['dateTimeGMT'] ) ) {
			$ts = (int) strtotime( $m['dateTimeGMT'] . ( false === stripos( $m['dateTimeGMT'], 'z' ) ? ' UTC' : '' ) );
		}

		return array(
			'id'        => isset( $m['id'] ) ? (string) $m['id'] : '',
			'name'      => $name,
			'status'    => isset( $m['status'] ) ? (string) $m['status'] : '',
			'series_id' => isset( $m['series_id'] ) ? (string) $m['series_id'] : '',
			'meta'      => $meta,
			'venue'     => $venue,
			'type'      => $type,
			'start'     => $ts,
			'started'   => ! empty( $m['matchStarted'] ),
			'ended'     => ! empty( $m['matchEnded'] ),
			'teams'     => $teams,
			'order'     => $order,
			'scores'    => $by_team,
			'innings'   => $innings,
			'team_inns' => $team_inns,
			'is_test'   => 'TEST' === $type,
		);
	}

	/** The day number when a Test match is at stumps (close of play), or 0. */
	public static function stumps_day( array $m ) {
		if ( empty( $m['is_test'] ) || $m['ended'] || ! $m['started'] ) {
			return 0;
		}
		if ( ! preg_match( '/\b(stumps|close of play|end of day)\b/i', $m['status'] ) ) {
			return 0;
		}
		if ( preg_match( '/day\s*(\d)/i', $m['status'], $x ) ) {
			return (int) $x[1];
		}
		return $m['start'] ? max( 1, min( 5, (int) floor( ( time() - $m['start'] ) / DAY_IN_SECONDS ) + 1 ) ) : 1;
	}

	/** Parse "England won by 6 wkts" style text. Returns winner name or '' and a type. */
	public static function parse_result( array $match ) {
		$s = $match['status'];
		foreach ( $match['teams'] as $t ) {
			if ( '' !== $t['name'] && preg_match( '/^' . preg_quote( $t['name'], '/' ) . '\s+won\b/i', $s ) ) {
				return array( $t['name'], 'win' );
			}
		}
		if ( preg_match( '/\b(drawn?)\b/i', $s ) ) {
			return array( '', 'draw' );
		}
		if ( preg_match( '/\b(tied|tie)\b/i', $s ) ) {
			return array( '', 'tie' );
		}
		if ( preg_match( '/\b(no result|abandoned|washed)\b/i', $s ) ) {
			return array( '', 'nr' );
		}
		return array( '', 'nr' );
	}

	/** Toss text such as "England won the toss and elected to bowl". Returns [team, decision] or null. */
	public static function parse_toss( array $match ) {
		$s = $match['status'];
		if ( preg_match( '/(.+?)\s+(?:have\s+)?won the toss and\s+(?:elected|chose|opted|decided)\s+to\s+(bat|bowl|field)/i', $s, $m ) ) {
			$decision = strtolower( $m[2] );
			return array( trim( $m[1] ), 'bat' === $decision ? 'bat' : 'bowl' );
		}
		return null;
	}

	/**
	 * Flatten a scorecard response into batters and bowlers.
	 *
	 * @return array [ 'batters' => [...], 'bowlers' => [...] ]
	 */
	public static function parse_scorecard( array $sc, array $match ) {
		$batters = array();
		$bowlers = array();
		if ( empty( $sc['scorecard'] ) || ! is_array( $sc['scorecard'] ) ) {
			return array( 'batters' => $batters, 'bowlers' => $bowlers );
		}
		foreach ( $sc['scorecard'] as $inn ) {
			$label = isset( $inn['inning'] ) ? (string) $inn['inning'] : '';
			$bat_team = '';
			foreach ( $match['teams'] as $t ) {
				if ( '' !== $t['name'] && 0 === stripos( $label, $t['name'] ) ) {
					$bat_team = $t['name'];
				}
			}
			$bowl_team = '';
			foreach ( $match['teams'] as $t ) {
				if ( $t['name'] !== $bat_team ) {
					$bowl_team = $t['name'];
				}
			}
			foreach ( ( isset( $inn['batting'] ) && is_array( $inn['batting'] ) ) ? $inn['batting'] : array() as $b ) {
				$id   = isset( $b['batsman']['id'] ) ? (string) $b['batsman']['id'] : ( isset( $b['batsman']['name'] ) ? md5( $b['batsman']['name'] ) : '' );
				$name = isset( $b['batsman']['name'] ) ? (string) $b['batsman']['name'] : '';
				if ( '' === $name ) {
					continue;
				}
				$runs  = isset( $b['r'] ) ? (int) $b['r'] : 0;
				$balls = isset( $b['b'] ) ? (int) $b['b'] : 0;
				$batters[] = array(
					'id'    => $id,
					'name'  => $name,
					'team'  => $bat_team,
					'r'     => $runs,
					'b'     => $balls,
					'4s'    => isset( $b['4s'] ) ? (int) $b['4s'] : 0,
					'6s'    => isset( $b['6s'] ) ? (int) $b['6s'] : 0,
					'sr'    => isset( $b['sr'] ) ? (float) $b['sr'] : ( $balls > 0 ? round( $runs * 100 / $balls, 1 ) : 0 ),
					'out'   => isset( $b['dismissal'] ) ? ( 'not out' !== strtolower( (string) $b['dismissal'] ) && '' !== (string) $b['dismissal'] ) : false,
				);
			}
			foreach ( ( isset( $inn['bowling'] ) && is_array( $inn['bowling'] ) ) ? $inn['bowling'] : array() as $b ) {
				$id   = isset( $b['bowler']['id'] ) ? (string) $b['bowler']['id'] : ( isset( $b['bowler']['name'] ) ? md5( $b['bowler']['name'] ) : '' );
				$name = isset( $b['bowler']['name'] ) ? (string) $b['bowler']['name'] : '';
				if ( '' === $name ) {
					continue;
				}
				$overs = isset( $b['o'] ) ? (float) $b['o'] : 0.0;
				$runs  = isset( $b['r'] ) ? (int) $b['r'] : 0;
				$bowlers[] = array(
					'id'    => $id,
					'name'  => $name,
					'team'  => $bowl_team,
					'o'     => $overs,
					'm'     => isset( $b['m'] ) ? (int) $b['m'] : 0,
					'r'     => $runs,
					'w'     => isset( $b['w'] ) ? (int) $b['w'] : 0,
					'eco'   => isset( $b['eco'] ) ? (float) $b['eco'] : ( $overs > 0 ? round( $runs / $overs, 2 ) : 0 ),
				);
			}
		}
		return array( 'batters' => $batters, 'bowlers' => $bowlers );
	}
}
