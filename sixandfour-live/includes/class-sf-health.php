<?php
/**
 * "Why is it not posting?" Plain-language checks with what to do about each one.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Health {

	/** A Facebook error turned into words and a next step. */
	public static function explain_fb( $msg ) {
		$m = (string) $msg;
		if ( preg_match( '/publish_actions|nonexisting field|does not exist|cannot be loaded|missing permissions|unsupported (get|post) request/i', $m ) ) {
			return 'Facebook will not post with this token or Page ID. It is usually a User token or an App ID pasted where the Page token and Page ID belong. In the Graph API Explorer run me/accounts, then copy the id and access_token of your Page into Setup.';
		}
		if ( preg_match( '/expired|session has expired|access token|OAuth|invalid|revoked/i', $m ) ) {
			return 'The Page access token has expired or is not valid. Make a new Page token (Graph API Explorer, me/accounts) and paste it in Setup.';
		}
		if ( preg_match( '/permission/i', $m ) ) {
			return 'The token is missing a permission. It needs pages_manage_posts and pages_read_engagement. Generate the token again with both ticked.';
		}
		if ( preg_match( '/rate|too many|limit/i', $m ) ) {
			return 'Facebook is limiting how fast the Page posts. Posts will go out again shortly. Lower "Posts per day" in Setup if it keeps happening.';
		}
		return $m;
	}

	/** The exact cron path, with a hint when WordPress is in a sub folder. */
	public static function cron_help() {
		$file = ABSPATH . 'wp-cron.php';
		$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ), '/' ) : '';
		$sub  = '' !== $root && rtrim( ABSPATH, '/' ) !== $root;
		return 'In Hostinger, Advanced, Cron Jobs: choose PHP and every 5 minutes. ' . ( $sub ? 'WordPress is in a sub folder, so use the exact path below, not public_html/wp-cron.php. ' : '' ) . 'Command to run (copy it without any full stop at the end): ' . $file;
	}

	/**
	 * @param bool $deep Also asks Facebook and the cricket API now. Costs one cricket call.
	 * @return array Rows of [ id, status (ok|warn|bad), title, message ], worst first.
	 */
	public static function report( $deep = false ) {
		$rows = array();
		$add  = function ( $id, $status, $title, $msg ) use ( &$rows ) {
			$rows[] = array( $id, $status, $title, $msg );
		};
		$o = SF_Options::all();

		// Setup.
		$missing = array();
		foreach ( array( 'fb_page_id' => 'the Facebook Page ID', 'fb_token' => 'the Page access token', 'api_key' => 'the cricket API key' ) as $k => $label ) {
			if ( '' === $o[ $k ] ) {
				$missing[] = $label;
			}
		}
		if ( $missing ) {
			$add( 'setup', 'bad', 'Setup is not finished', 'Nothing can post until you add ' . implode( ', ', $missing ) . ' in Setup.' );
		}
		if ( ! function_exists( 'imagettftext' ) ) {
			$add( 'gd', 'bad', 'Cards cannot be drawn', 'PHP GD with FreeType is missing on this server, so score card pictures cannot be made. Ask Hostinger to enable the GD extension.' );
		}
		if ( $o['paused'] ) {
			$add( 'paused', 'bad', 'Posting is paused', 'The Pause switch is on, so nothing is posted. Press Resume on the Overview or in the mobile console.' );
		}
		if ( ! array_filter( $o['jobs'] ) ) {
			$add( 'jobs', 'warn', 'Every post type is switched off', 'Turn on at least one on the Engage screen.' );
		}

		// Facebook.
		if ( '' !== $o['fb_page_id'] && '' !== $o['fb_token'] ) {
			if ( $deep ) {
				SF_Facebook::verify();
			}
			$fb = get_option( 'sf_fb_status', array() );
			if ( ! is_array( $fb ) || empty( $fb ) ) {
				$add( 'fb', 'warn', 'Facebook not tested yet', 'Press Run full check, or Test connection in Setup.' );
			} elseif ( empty( $fb['ok'] ) ) {
				$add( 'fb', 'bad', 'Facebook is not accepting the token', self::explain_fb( isset( $fb['message'] ) ? $fb['message'] : '' ) );
			} else {
				$add( 'fb', 'ok', 'Facebook connected', isset( $fb['name'] ) ? 'Posting to ' . $fb['name'] . '.' : 'Connected.' );
			}
		}

		// Cricket data.
		if ( '' !== $o['api_key'] ) {
			if ( $deep ) {
				$ping = SF_API::ping();
				if ( is_wp_error( $ping ) ) {
					update_option( 'sf_last_api_error', array( time(), $ping->get_error_message() ), false );
				}
			}
			$err = get_option( 'sf_last_api_error', array() );
			$s   = SF_API::stats();
			if ( ! SF_API::budget_ok() ) {
				$add( 'api', 'bad', 'Cricket API calls are used up', 'All ' . (int) $s['limit'] . ' calls for today are spent, so no new scores can be read until tomorrow (UTC). Add a backup key in Setup or switch to a paid plan.' );
			} elseif ( is_array( $err ) && ! empty( $err[0] ) && time() - (int) $err[0] < 2 * HOUR_IN_SECONDS ) {
				$add( 'api', 'bad', 'The cricket API is not answering', $err[1] . ' Check the key in Setup (Test key).' );
			} else {
				$add( 'api', 'ok', 'Cricket data working', (int) $s['hits'] . ' of ' . (int) $s['limit'] . ' calls used today.' );
			}
		}

		// The schedule.
		$last = (int) get_option( 'sf_last_tick', 0 );
		$due  = (int) get_option( 'sf_next_check', 0 );
		if ( ! $last ) {
			$add( 'cron', 'bad', 'No match check has ever run', 'WordPress cron has not run the plugin yet. ' . self::cron_help() );
		} elseif ( $due && time() - $due > 10 * MINUTE_IN_SECONDS ) {
			$add( 'cron', 'bad', 'The scheduled job is not running', 'A match check was due ' . human_time_diff( $due ) . ' ago and nothing ran it. ' . self::cron_help() );
		} elseif ( time() - $last > 4 * HOUR_IN_SECONDS ) {
			$add( 'cron', 'warn', 'No recent match check', 'The last check was ' . human_time_diff( $last ) . ' ago. ' . self::cron_help() );
		} else {
			$add( 'cron', 'ok', 'Scheduled checks are running', 'The last match check was ' . human_time_diff( $last ) . ' ago.' );
		}

		// The day start post.
		$daily = get_option( 'sf_last_daily', array() );
		if ( SF_Options::job( 'fixtures' ) && is_array( $daily ) && ! empty( $daily[0] ) && SF_Plugin::date( 'Y-m-d', (int) $daily[0] ) === SF_Plugin::date( 'Y-m-d' ) && 0 !== strpos( (string) $daily[1], "Today's post" ) ) {
			$add( 'daily', 'warn', "Today's matches post was not made", (string) $daily[1] );
		}

		// Posts that are stuck or failed.
		$waiting = SF_DB::posts_by_status( 'approved', 5 );
		if ( $waiting ) {
			$why = SF_Poster::wait_reason( $waiting[0] );
			$add( 'queue', 'warn', count( $waiting ) . ' post' . ( 1 === count( $waiting ) ? ' is' : 's are' ) . ' waiting', $why );
		}
		$failed = SF_DB::posts_by_status( 'failed', 5 );
		if ( $failed ) {
			$add( 'failed', 'bad', count( $failed ) . ' post' . ( 1 === count( $failed ) ? '' : 's' ) . ' failed', self::explain_fb( (string) $failed[0]['error'] ) . ' Fix that, then press Try again in the Post queue.' );
		}

		// Matches.
		list( $seen, $covered ) = SF_Engine::coverage();
		if ( $last && 0 === $seen && '' !== $o['api_key'] ) {
			$add( 'matches', 'warn', 'The cricket API returned no matches', 'It may be a day without matches, or the key may not have access. Press Run full check.' );
		} elseif ( $seen > 0 && 0 === count( $covered ) ) {
			$add( 'matches', 'warn', 'No match is covered', $seen . ' matches were found but all were skipped (women\'s, or outside your coverage choice). Change Which matches in Setup.' );
		}

		$order = array( 'bad' => 0, 'warn' => 1, 'ok' => 2 );
		usort( $rows, function ( $a, $b ) use ( $order ) {
			return $order[ $a[1] ] <=> $order[ $b[1] ];
		} );
		return $rows;
	}

	/** Only the rows that need attention. */
	public static function problems( $deep = false ) {
		return array_values( array_filter( self::report( $deep ), function ( $r ) {
			return 'ok' !== $r[1];
		} ) );
	}
}
