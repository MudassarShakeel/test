<?php
/**
 * The post queue: draws cards, applies the posting rules, and sends to Facebook.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Poster {

	/** Post types that go stale quickly and are skipped if they wait too long. */
	private static function is_live_type( $type ) {
		return in_array( $type, array( 'live', 'wicket', 'digest', 'toss', 'poll', 'century', 'fivefor' ), true );
	}

	public static function dir() {
		$u = wp_upload_dir();
		return array( 'path' => trailingslashit( $u['basedir'] ) . 'sixandfour-live/', 'url' => trailingslashit( $u['baseurl'] ) . 'sixandfour-live/' );
	}

	public static function image_path( $row ) {
		return '' === $row['image'] ? '' : self::dir()['path'] . $row['image'];
	}

	public static function image_url( $row ) {
		return '' === $row['image'] ? '' : self::dir()['url'] . $row['image'];
	}

	/** Start of today in the site's time zone, as a Unix time. */
	public static function day_start() {
		$dt = new DateTime( 'today', wp_timezone() );
		return $dt->getTimestamp();
	}

	public static function in_quiet_hours() {
		$o = SF_Options::all();
		$s = $o['quiet_start'];
		$e = $o['quiet_end'];
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $s ) || ! preg_match( '/^\d{2}:\d{2}$/', $e ) || $s === $e ) {
			return false;
		}
		$now = wp_date( 'H:i' );
		return $s < $e ? ( $now >= $s && $now < $e ) : ( $now >= $s || $now < $e );
	}

	/**
	 * Draw the card and add it to the queue.
	 *
	 * @param string $type    Card type.
	 * @param string $dedupe  A key that is the same for the same event.
	 * @param array  $data    Card data for SF_Cards.
	 * @param string $caption Facebook caption.
	 * @param array  $extra   match_id, series_id, delay (seconds).
	 * @return int The queue id, or 0 when this event was queued before or the card failed.
	 */
	public static function queue( $type, $dedupe, array $data, $caption, array $extra = array() ) {
		if ( SF_DB::exists( $dedupe ) ) {
			return 0;
		}
		$sub  = gmdate( 'Y-m' ) . '/';
		$name = $type . '-' . substr( md5( $dedupe ), 0, 12 ) . '.png';
		$dir  = self::dir()['path'] . 'cards/' . $sub;
		$cards = new SF_Cards();
		$res   = $cards->render( $type, $data, $dir . $name );
		if ( is_wp_error( $res ) ) {
			SF_Log::add( 'Could not draw a ' . $type . ' card: ' . $res->get_error_message(), 'error' );
			return 0;
		}
		$mode   = SF_Options::get( 'mode', 'auto' );
		$delay  = isset( $extra['delay'] ) ? (int) $extra['delay'] : 0;
		$row    = array(
			'type'      => $type,
			'dedupe'    => $dedupe,
			'match_id'  => isset( $extra['match_id'] ) ? $extra['match_id'] : '',
			'series_id' => isset( $extra['series_id'] ) ? $extra['series_id'] : '',
			'status'    => 'auto' === $mode ? 'approved' : 'pending',
			'caption'   => $caption,
			'image'     => 'cards/' . $sub . $name,
		);
		if ( $delay > 0 ) {
			$row['scheduled_at'] = gmdate( 'Y-m-d H:i:s', time() + $delay );
		}
		return SF_DB::add_post( $row );
	}

	/** A text-only post. */
	public static function queue_text( $type, $dedupe, $caption, array $extra = array() ) {
		if ( SF_DB::exists( $dedupe ) ) {
			return 0;
		}
		$mode = SF_Options::get( 'mode', 'auto' );
		return SF_DB::add_post(
			array(
				'type'      => $type,
				'dedupe'    => $dedupe,
				'match_id'  => isset( $extra['match_id'] ) ? $extra['match_id'] : '',
				'status'    => 'auto' === $mode ? 'approved' : 'pending',
				'caption'   => $caption,
			)
		);
	}

	public static function approve( $id ) {
		$row = SF_DB::get_post( $id );
		if ( $row && in_array( $row['status'], array( 'pending', 'failed', 'skipped' ), true ) ) {
			SF_DB::update_post( $id, array( 'status' => 'approved', 'error' => null, 'attempts' => 0, 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
			return true;
		}
		return false;
	}

	public static function skip( $id ) {
		$row = SF_DB::get_post( $id );
		if ( $row && in_array( $row['status'], array( 'pending', 'approved' ), true ) ) {
			SF_DB::update_post( $id, array( 'status' => 'skipped', 'error' => 'Skipped by you.' ) );
			return true;
		}
		return false;
	}

	/** Send what is due, within the posting rules. */
	public static function publish_due() {
		foreach ( SF_DB::due_posts( 3 ) as $row ) {
			self::publish_one( $row );
		}
	}

	/** @return string posted|waiting|skipped|failed */
	public static function publish_one( array $row ) {
		$o      = SF_Options::all();
		$manual = 'manual' === $row['type'];
		$id     = (int) $row['id'];

		if ( ! $manual ) {
			if ( self::in_quiet_hours() ) {
				return 'waiting';
			}
			$age = time() - (int) strtotime( $row['created_at'] . ' UTC' );
			if ( self::is_live_type( $row['type'] ) && $age > 90 * MINUTE_IN_SECONDS ) {
				SF_DB::update_post( $id, array( 'status' => 'skipped', 'error' => 'Skipped because it was too old to be useful.' ) );
				return 'skipped';
			}
			if ( SF_DB::count_posted_since( self::day_start() ) >= (int) $o['max_posts_day'] ) {
				if ( self::is_live_type( $row['type'] ) ) {
					SF_DB::update_post( $id, array( 'status' => 'skipped', 'error' => 'Skipped because the daily post limit was reached.' ) );
					return 'skipped';
				}
				return 'waiting';
			}
			if ( self::is_live_type( $row['type'] ) && '' !== $row['match_id'] && SF_DB::count_posted_since( time() - HOUR_IN_SECONDS, $row['match_id'] ) >= (int) $o['per_match_hour'] ) {
				return 'waiting';
			}
		}

		$path = self::image_path( $row );
		$res  = '' !== $path ? SF_Facebook::photo( $path, $row['caption'] ) : SF_Facebook::feed( $row['caption'] );
		if ( is_wp_error( $res ) ) {
			$attempts = (int) $row['attempts'] + 1;
			$fatal    = in_array( $res->get_error_code(), array( 'sf_fb_190', 'sf_fb_setup', 'sf_fb_file' ), true );
			if ( $fatal || $attempts >= 3 ) {
				SF_DB::update_post( $id, array( 'status' => 'failed', 'attempts' => $attempts, 'error' => $res->get_error_message() ) );
				SF_Log::add( 'Post ' . $id . ' failed: ' . $res->get_error_message(), 'error' );
				return 'failed';
			}
			SF_DB::update_post( $id, array( 'attempts' => $attempts, 'error' => $res->get_error_message(), 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS ) ) );
			return 'waiting';
		}
		SF_DB::update_post( $id, array( 'status' => 'posted', 'fb_post_id' => (string) $res, 'posted_at' => gmdate( 'Y-m-d H:i:s' ), 'error' => null ) );
		return 'posted';
	}
}
