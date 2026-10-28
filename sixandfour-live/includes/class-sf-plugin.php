<?php
/**
 * Plugin set-up: scheduled jobs, search-engine hiding, and activation.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Plugin {

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'sf_tick', array( 'SF_Engine', 'tick' ) );
		add_action( 'sf_publish', array( 'SF_Poster', 'publish_due' ) );
		add_action( 'sf_daily', array( __CLASS__, 'run_daily' ) );
		add_action( 'sf_weekly', array( __CLASS__, 'run_weekly' ) );
		add_action( 'sf_monthly', array( __CLASS__, 'run_monthly' ) );

		// Hide this site from search engines with a noindex tag. robots.txt is left alone so Facebook can still read link previews.
		add_filter(
			'wp_robots',
			function ( $robots ) {
				if ( SF_Options::get( 'noindex', 1 ) ) {
					$robots['noindex']  = true;
					$robots['nofollow'] = true;
					unset( $robots['max-image-preview'] );
				}
				return $robots;
			}
		);

		if ( get_option( 'sf_db_version' ) !== SF_DB::VERSION ) {
			SF_DB::install();
		}
		SF_Options::protect();
		SF_Security::init();
		self::schedule();

		if ( is_admin() ) {
			SF_Admin::init();
		}
	}

	public static function cron_schedules( $s ) {
		$s['sf_five_minutes'] = array( 'interval' => 300, 'display' => 'Every 5 minutes (Six & Four Live)' );
		return $s;
	}

	/** Next time of day (and optionally weekday 1-7 or day of month) in the site's time zone. */
	public static function next_ts( $time, $dow = null, $dom = null ) {
		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );
		$h   = (int) substr( $time, 0, 2 );
		$m   = (int) substr( $time, 3, 2 );
		$c   = clone $now;
		$c->setTime( $h, $m, 0 );
		for ( $i = 0; $i < 70; $i++ ) {
			$ok_dow = null === $dow || (int) $c->format( 'N' ) === $dow;
			$ok_dom = null === $dom || (int) $c->format( 'j' ) === $dom;
			if ( $c > $now && $ok_dow && $ok_dom ) {
				return $c->getTimestamp();
			}
			$c->modify( '+1 day' );
			$c->setTime( $h, $m, 0 );
		}
		return time() + DAY_IN_SECONDS;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( 'sf_tick' ) ) {
			wp_schedule_event( time() + 60, 'sf_five_minutes', 'sf_tick' );
		}
		if ( ! wp_next_scheduled( 'sf_publish' ) ) {
			wp_schedule_event( time() + 90, 'sf_five_minutes', 'sf_publish' );
		}
		if ( ! wp_next_scheduled( 'sf_daily' ) ) {
			wp_schedule_single_event( self::next_ts( '08:00' ), 'sf_daily' );
		}
		if ( ! wp_next_scheduled( 'sf_weekly' ) ) {
			wp_schedule_single_event( self::next_ts( '09:00', 1 ), 'sf_weekly' );
		}
		if ( ! wp_next_scheduled( 'sf_monthly' ) ) {
			wp_schedule_single_event( self::next_ts( '09:00', null, 1 ), 'sf_monthly' );
		}
	}

	public static function run_daily() {
		wp_schedule_single_event( self::next_ts( '08:00' ), 'sf_daily' );
		SF_Engine::daily();
	}

	public static function run_weekly() {
		wp_schedule_single_event( self::next_ts( '09:00', 1 ), 'sf_weekly' );
		SF_Engine::weekly();
	}

	public static function run_monthly() {
		wp_schedule_single_event( self::next_ts( '09:00', null, 1 ), 'sf_monthly' );
		SF_Engine::monthly();
	}

	public static function activate() {
		// The five-minute schedule must exist before the first event is scheduled.
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		SF_DB::install();
		SF_Poster::protect_dir();
		SF_Options::protect();
		self::schedule();
	}

	public static function deactivate() {
		foreach ( array( 'sf_tick', 'sf_publish', 'sf_daily', 'sf_weekly', 'sf_monthly' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
