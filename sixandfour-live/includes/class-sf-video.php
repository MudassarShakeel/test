<?php
/**
 * The result video. The plugin cannot make video on shared hosting, so it sends the match data to a small
 * render service (see the video-service folder) and posts the finished MP4 to the Page.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Video {

	private static function base() {
		return rtrim( (string) SF_Options::get( 'video_url', '' ), '/' );
	}

	private static function headers() {
		return array( 'Authorization' => 'Bearer ' . SF_Options::get( 'video_key', '' ), 'Content-Type' => 'application/json' );
	}

	public static function configured() {
		return '' !== self::base() && '' !== SF_Options::get( 'video_key', '' );
	}

	/** "flags/eng.png" for a national team, or null. */
	private static function flag( $team ) {
		return ( 'flag' === $team['kind'] && ! empty( $team['path'] ) ) ? 'flags/' . basename( $team['path'] ) : null;
	}

	/** The match data the service draws. Returns null when the result has no clear winner. */
	public static function payload( array $m, array $p ) {
		if ( ! preg_match( '/^(.+?)\s+won by\s+(an innings and\s+)?(\d+)\s+(runs?|wickets?)/i', (string) $m['status'], $x ) ) {
			return null;
		}
		$winner = trim( $x[1] );
		$loser  = '';
		$teams  = array();
		foreach ( $m['order'] as $idx ) {
			$t         = SF_Teams::resolve( $m['teams'][ $idx ]['name'], $m['teams'][ $idx ]['short'] );
			$s         = isset( $m['scores'][ $idx ] ) ? $m['scores'][ $idx ] : null;
			$teams[]   = array(
				'name'     => $t['name'],
				'flag'     => self::flag( $t ),
				'initials' => $t['initials'],
				'score'    => $s ? SF_Engine::score_for_video( $m, $idx, $s ) : '',
				'overs'    => $s ? number_format( (float) $s['o'], 1, '.', '' ) . ' ov' : '',
				'won'      => 0 === strcasecmp( $t['name'], $winner ),
			);
			if ( 0 !== strcasecmp( $t['name'], $winner ) ) {
				$loser = $t['name'];
			}
		}
		$spare = preg_match( '/(\d+)\s*balls?\s*(?:remaining|left|to spare)/i', (string) $m['status'], $y ) ? $y[1] . ' balls to spare' : '';
		$bat   = SF_Engine::best_batter_for_video( $p );
		$bowl  = SF_Engine::best_bowler_for_video( $p );
		return array(
			'tournament' => SF_Options::series_name( $m['series_id'] ),
			'meta'       => $m['meta'],
			'winner'     => $winner,
			'loser'      => $loser,
			'margin'     => trim( $x[2] . $x[3] . ' ' . $x[4] ),
			'toSpare'    => $spare,
			'teams'      => $teams,
			'bat'        => $bat,
			'bowl'       => $bowl,
			'link'       => preg_replace( '#^https?://#', '', (string) SF_Options::get( 'fb_handle', 'facebook.com/cricketsportinfo' ) ),
			'voice'      => (bool) SF_Options::get( 'video_voice', 1 ),
			'music'      => (bool) SF_Options::get( 'video_music', 1 ),
		);
	}

	/** Ask the service to start a video. Returns the job id or WP_Error. */
	public static function submit( array $payload ) {
		if ( ! self::configured() ) {
			return new WP_Error( 'sf_video_setup', 'Add the video service address and key in Setup.' );
		}
		$res = wp_remote_post( self::base() . '/render', array( 'timeout' => 25, 'redirection' => 0, 'sslverify' => true, 'headers' => self::headers(), 'body' => wp_json_encode( $payload ) ) );
		return self::job_from( $res );
	}

	private static function job_from( $res ) {
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'sf_video_net', 'The video service could not be reached: ' . SF_Secrets::scrub( $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 401 === $code ) {
			return new WP_Error( 'sf_video_key', 'The video service refused the key. Check the key in Setup.' );
		}
		if ( 202 !== $code && 200 !== $code ) {
			return new WP_Error( 'sf_video_http', 'The video service answered with status ' . $code . ( is_array( $body ) && ! empty( $body['error'] ) ? ': ' . $body['error'] : '.' ) );
		}
		return ( is_array( $body ) && ! empty( $body['id'] ) ) ? (string) $body['id'] : new WP_Error( 'sf_video_bad', 'The video service sent no job id.' );
	}

	/** @return array|WP_Error [ status (queued|rendering|done|failed), url, error ] */
	public static function status( $job ) {
		$res = wp_remote_get( self::base() . '/status/' . rawurlencode( $job ), array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => true, 'headers' => self::headers() ) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'sf_video_net', 'The video service could not be reached: ' . SF_Secrets::scrub( $res->get_error_message() ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) || empty( $body['status'] ) ) {
			return new WP_Error( 'sf_video_bad', 'The video service sent an unexpected answer.' );
		}
		return array( 'status' => (string) $body['status'], 'url' => isset( $body['url'] ) ? (string) $body['url'] : '', 'error' => isset( $body['error'] ) ? (string) $body['error'] : '' );
	}

	/** Is the service up? Returns true or WP_Error. */
	public static function ping() {
		if ( ! self::configured() ) {
			return new WP_Error( 'sf_video_setup', 'Add the video service address and key in Setup.' );
		}
		$res = wp_remote_get( self::base() . '/health', array( 'timeout' => 15, 'redirection' => 0, 'sslverify' => true ) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'sf_video_net', 'The video service could not be reached: ' . SF_Secrets::scrub( $res->get_error_message() ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || empty( $body['ok'] ) ) {
			return new WP_Error( 'sf_video_http', 'The video service answered, but it is not ready.' );
		}
		return true;
	}
}
