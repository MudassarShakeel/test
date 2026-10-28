<?php
/**
 * Settings storage and a small activity log.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Options {

	const KEY = 'sf_options';

	public static function defaults() {
		return array(
			'mode'              => 'auto', // auto or approve.
			'fb_page_id'        => '',
			'fb_token'          => '',
			'fb_handle'         => 'facebook.com/sixandfourlive',
			'api_key'           => '',
			'api_limit'         => 100,
			'max_posts_day'     => 15,
			'quiet_start'       => '01:00',
			'quiet_end'         => '06:00',
			'live_cards'        => 2,
			'per_match_hour'    => 2,
			'digest'            => 1,
			'digest_every'      => 30,
			'polls_per_day'     => 2,
			'poll_hours_before' => 2,
			'followed'          => array(), // series id => name. Empty means follow everything.
			'jobs'              => array(
				'fixtures' => 1,
				'toss'     => 1,
				'live'     => 1,
				'wicket'   => 1,
				'result'   => 1,
				'summary'  => 1,
				'potm'     => 1,
				'poll'     => 1,
				'table'    => 1,
				'ranking'  => 1,
				'pinned'   => 1,
				'milestones' => 1,
			),
			'milestones'        => array(
				'century' => 1,
				'fivefor' => 1,
				'record'  => 1,
				'fifty'   => 0,
			),
			'questions'         => "Who is your player of the day?\nWhat is your score prediction?\nGreat knock or lucky day? Tell us.\nWho tops the table next week?\nWhich match are you watching?",
			'noindex'           => 1,
			'team_logos'        => array(), // lower-case team name => attachment id.
			'pinned_post_id'    => '',
		);
	}

	public static function all() {
		$o = get_option( self::KEY, array() );
		$d = self::defaults();
		$o = is_array( $o ) ? $o : array();
		$m = array_merge( $d, $o );
		foreach ( array( 'jobs', 'milestones' ) as $k ) {
			$m[ $k ] = array_merge( $d[ $k ], isset( $o[ $k ] ) && is_array( $o[ $k ] ) ? $o[ $k ] : array() );
		}
		return $m;
	}

	public static function get( $key, $default = null ) {
		$o = self::all();
		return array_key_exists( $key, $o ) ? $o[ $key ] : $default;
	}

	public static function update( array $new ) {
		$o = self::all();
		foreach ( $new as $k => $v ) {
			$o[ $k ] = $v;
		}
		update_option( self::KEY, $o, false );
	}

	public static function job( $name ) {
		$o = self::all();
		return ! empty( $o['jobs'][ $name ] );
	}

	/** True when the plugin has what it needs to read scores and post. */
	public static function ready() {
		$o = self::all();
		return '' !== $o['api_key'] && '' !== $o['fb_page_id'] && '' !== $o['fb_token'];
	}

	public static function is_followed( $series_id ) {
		$f = self::get( 'followed', array() );
		return empty( $f ) || isset( $f[ $series_id ] );
	}

	public static function series_name( $series_id ) {
		$f = self::get( 'followed', array() );
		return isset( $f[ $series_id ] ) ? $f[ $series_id ] : '';
	}
}

class SF_Log {

	const KEY = 'sf_log';

	public static function add( $message, $level = 'info' ) {
		$log   = get_option( self::KEY, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array( time(), $level, (string) $message );
		$log   = array_slice( $log, -80 );
		update_option( self::KEY, $log, false );
	}

	public static function recent( $n = 20 ) {
		$log = get_option( self::KEY, array() );
		$log = is_array( $log ) ? $log : array();
		return array_reverse( array_slice( $log, -$n ) );
	}
}
