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

	const BASE  = 'https://api.cricapi.com/v1/';
	const STATS = 'sf_api_stats';

	/** Send a request. Returns the decoded JSON array or WP_Error. */
	private static function request( $endpoint, array $params = array() ) {
		$key = SF_Options::get( 'api_key' );
		if ( '' === $key ) {
			return new WP_Error( 'sf_api_key', 'Add your cricket API key in Setup.' );
		}
		if ( ! self::budget_ok() ) {
			return new WP_Error( 'sf_api_budget', 'The daily API limit is nearly used up, so no more calls today.' );
		}
		$params['apikey'] = $key;
		$res              = wp_remote_get( add_query_arg( $params, self::BASE . $endpoint ), array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'sf_api_http', 'The cricket API answered with status ' . $code . '.' );
		}
		self::track( $body );
		if ( isset( $body['status'] ) && 'success' !== $body['status'] ) {
			$why = isset( $body['reason'] ) ? $body['reason'] : 'unknown reason';
			return new WP_Error( 'sf_api_fail', 'The cricket API refused the request: ' . $why );
		}
		return $body;
	}

	private static function track( array $body ) {
		$s   = get_option( self::STATS, array() );
		$day = gmdate( 'Y-m-d' );
		if ( ! is_array( $s ) || ( isset( $s['day'] ) ? $s['day'] : '' ) !== $day ) {
			$s = array( 'day' => $day, 'hits' => 0, 'limit' => (int) SF_Options::get( 'api_limit', 100 ) );
		}
		if ( isset( $body['info']['hitsToday'] ) ) {
			$s['hits'] = (int) $body['info']['hitsToday'];
		} else {
			$s['hits']++;
		}
		if ( isset( $body['info']['hitsLimit'] ) ) {
			$s['limit'] = (int) $body['info']['hitsLimit'];
		}
		update_option( self::STATS, $s, false );
	}

	public static function stats() {
		$s = get_option( self::STATS, array() );
		if ( ! is_array( $s ) || ( isset( $s['day'] ) ? $s['day'] : '' ) !== gmdate( 'Y-m-d' ) ) {
			return array( 'day' => gmdate( 'Y-m-d' ), 'hits' => 0, 'limit' => (int) SF_Options::get( 'api_limit', 100 ) );
		}
		return $s;
	}

	/** Keep 10% of the daily calls in reserve. */
	public static function budget_ok( $reserve = 0.1 ) {
		$s = self::stats();
		return $s['hits'] < floor( $s['limit'] * ( 1 - $reserve ) );
	}

	public static function remaining() {
		$s = self::stats();
		return max( 0, (int) floor( $s['limit'] * 0.9 ) - $s['hits'] );
	}

	/** All current and recent matches in one call. Cached for two minutes. */
	public static function current_matches( $fresh = false ) {
		$cached = get_transient( 'sf_current_matches' );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached;
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
		);
	}

	/** Parse "England won by 6 wkts" style text. Returns winner name or '' and a type. */
	public static function parse_result( array $match ) {
		$s = $match['status'];
		foreach ( $match['teams'] as $t ) {
			if ( '' !== $t['name'] && preg_match( '/^' . preg_quote( $t['name'], '/' ) . '\s+won\b/i', $s ) ) {
				return array( $t['name'], 'win' );
			}
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
