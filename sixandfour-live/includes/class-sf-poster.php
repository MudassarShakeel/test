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

	/** Stop the picture folder from listing files or running PHP. */
	public static function protect_dir() {
		$base = self::dir()['path'];
		wp_mkdir_p( $base );
		$files = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\n<FilesMatch \"\\.(php|phtml|php[0-9]?|phar)$\">\nRequire all denied\n</FilesMatch>\n</IfModule>\n<IfModule !mod_authz_core.c>\n<FilesMatch \"\\.(php|phtml|php[0-9]?|phar)$\">\nOrder allow,deny\nDeny from all\n</FilesMatch>\n</IfModule>\n",
		);
		foreach ( $files as $name => $body ) {
			if ( ! is_file( $base . $name ) ) {
				file_put_contents( $base . $name, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
	}

	public static function image_path( $row ) {
		return '' === $row['image'] ? '' : self::dir()['path'] . $row['image'];
	}

	public static function image_url( $row ) {
		return '' === $row['image'] ? '' : self::dir()['url'] . $row['image'];
	}

	/** Start of today in the site's time zone, as a Unix time. */
	public static function day_start() {
		$dt = new DateTime( 'today', SF_Plugin::tz() );
		return $dt->getTimestamp();
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
		self::protect_dir();
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
		// Look past posts that are waiting on a rule, so they cannot block newer ones. Send at most three per run.
		$sent = 0;
		foreach ( SF_DB::due_posts( 30 ) as $row ) {
			if ( 'posted' === self::publish_one( $row ) && ++$sent >= 3 ) {
				break;
			}
		}
	}

	/** Why an approved post has not gone out yet, in plain words. */
	public static function wait_reason( array $row ) {
		if ( 'approved' !== $row['status'] ) {
			return '';
		}
		$o = SF_Options::all();
		if ( 'manual' !== $row['type'] ) {
			if ( SF_Options::get( 'paused' ) ) {
				return 'Posting is paused. Resume it on the Overview or the mobile console.';
			}
			if ( SF_DB::count_posted_since( self::day_start() ) >= (int) $o['max_posts_day'] ) {
				return 'The daily limit of ' . (int) $o['max_posts_day'] . ' posts was reached.';
			}
			if ( self::is_live_type( $row['type'] ) && '' !== $row['match_id'] && SF_DB::count_posted_since( time() - HOUR_IN_SECONDS, $row['match_id'] ) >= (int) $o['per_match_hour'] ) {
				return 'This match already had ' . (int) $o['per_match_hour'] . ' live posts in the last hour.';
			}
		}
		if ( ! empty( $row['scheduled_at'] ) && strtotime( $row['scheduled_at'] . ' UTC' ) > time() ) {
			return 'Scheduled for ' . wp_date( 'j M, H:i', strtotime( $row['scheduled_at'] . ' UTC' ) ) . '.';
		}
		return 'Ready. It goes out on the next scheduled run, within about 5 minutes. If it stays here, the site cron is not running. Use Post now.';
	}

	/** @return string posted|waiting|skipped|failed */
	public static function publish_one( array $row, $force = false ) {
		$o      = SF_Options::all();
		$manual = 'manual' === $row['type'] || $force;
		$id     = (int) $row['id'];

		if ( ! $manual ) {
			if ( SF_Options::get( 'paused' ) ) {
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

	/**
	 * Add a post you wrote yourself: your picture, your words. Used by the admin screen and the mobile console.
	 *
	 * @param array $in caption, attachment_id, file ($_FILES style), add_question, watermark, wm_pos, when (now|later), when_at.
	 * @return array [ ok, message ]
	 */
	public static function manual_post( array $in ) {
		if ( ! SF_Options::get( 'manual_enabled', 0 ) ) {
			return array( 'ok' => false, 'message' => 'Manual posts are switched off. Turn them on in Setup, under Posting.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		wp_raise_memory_limit( 'image' );
		$caption = isset( $in['caption'] ) ? sanitize_textarea_field( $in['caption'] ) : '';
		$src     = '';
		$att     = isset( $in['attachment_id'] ) ? absint( $in['attachment_id'] ) : 0;
		if ( $att && wp_attachment_is_image( $att ) ) {
			$src = get_attached_file( $att );
		} elseif ( ! empty( $in['file']['name'] ) ) {
			$up = wp_handle_upload(
				$in['file'],
				array(
					'test_form' => false,
					'mimes'     => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ),
				)
			);
			if ( isset( $up['error'] ) ) {
				return array( 'ok' => false, 'message' => $up['error'] );
			}
			$src = $up['file'];
		}
		if ( '' === $src && '' === $caption ) {
			return array( 'ok' => false, 'message' => 'Add a picture or some text.' );
		}
		if ( ! empty( $in['add_question'] ) ) {
			$lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) SF_Options::get( 'questions', '' ) ) ) ) );
			if ( $lines ) {
				$caption = trim( $caption . "\n" . $lines[ array_rand( $lines ) ] );
			}
		}
		$rel = '';
		if ( '' !== $src ) {
			if ( filesize( $src ) > 8 * MB_IN_BYTES ) {
				return array( 'ok' => false, 'message' => 'That picture is larger than 8 MB. Use a smaller file.' );
			}
			$sub  = 'manual/' . gmdate( 'Y-m' ) . '/';
			$name = 'post-' . wp_generate_password( 12, false ) . '.' . SF_Cards::output_extension( $src );
			$dest = self::dir()['path'] . $sub . $name;
			$pos  = ( isset( $in['wm_pos'] ) && in_array( $in['wm_pos'], array( 'tl', 'tr', 'bl', 'br' ), true ) ) ? $in['wm_pos'] : 'br';
			self::protect_dir();
			$cards = new SF_Cards();
			$res   = $cards->clean_image( $src, $dest, ! empty( $in['watermark'] ), $pos );
			if ( is_wp_error( $res ) ) {
				return array( 'ok' => false, 'message' => $res->get_error_message() );
			}
			$rel = $sub . $name;
		}
		$when = null;
		if ( isset( $in['when'] ) && 'later' === $in['when'] && ! empty( $in['when_at'] ) ) {
			$dt = date_create( $in['when_at'], wp_timezone() );
			if ( $dt && $dt->getTimestamp() > time() ) {
				$when = gmdate( 'Y-m-d H:i:s', $dt->getTimestamp() );
			}
		}
		$id = SF_DB::add_post(
			array(
				'type'         => 'manual',
				'dedupe'       => 'manual:' . wp_generate_password( 14, false ),
				'status'       => 'approved',
				'caption'      => $caption,
				'image'        => $rel,
				'scheduled_at' => $when,
			)
		);
		if ( ! $id ) {
			return array( 'ok' => false, 'message' => 'Could not add the post.' );
		}
		if ( null !== $when ) {
			return array( 'ok' => true, 'message' => 'Scheduled for ' . wp_date( 'j M, H:i', strtotime( $when . ' UTC' ) ) . '.', 'id' => $id );
		}
		$res = self::publish_one( SF_DB::get_post( $id ) );
		if ( 'posted' === $res ) {
			return array( 'ok' => true, 'message' => 'Posted to Facebook.', 'id' => $id );
		}
		$row = SF_DB::get_post( $id );
		return array( 'ok' => false, 'message' => 'Facebook did not accept it: ' . ( ! empty( $row['error'] ) ? $row['error'] : 'unknown reason' ) . ' It will retry a few times.', 'id' => $id );
	}
}
