<?php
/**
 * Player photos you upload yourself.
 *
 * Photos of players are owned by photographers and agencies, so the plugin never fetches them from
 * the web. You add the ones you own or have permission to use, and the cards show them.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Players {

	public static function key( $name ) {
		$k = strtolower( trim( preg_replace( '/[^\p{L}\s\'-]/u', '', (string) $name ) ) );
		return trim( preg_replace( '/\s+/', ' ', $k ) );
	}

	/** Keep the names the cards have shown, for the Players screen. */
	public static function remember( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return;
		}
		$known = get_option( 'sf_known_players', array() );
		$known = is_array( $known ) ? $known : array();
		if ( ! in_array( $name, $known, true ) ) {
			$known[] = $name;
			update_option( 'sf_known_players', array_slice( $known, -300 ), false );
		}
	}

	const AUTO = 'sf_player_auto';

	/** Lookups made in this request. Tests reset it. */
	public static $fetched = 0;

	private static function auto_all() {
		$a = get_option( self::AUTO, array() );
		return is_array( $a ) ? $a : array();
	}

	/** The entry the plugin found on Wikimedia Commons for a player, or null. */
	public static function auto_entry( $name ) {
		$a = self::auto_all();
		$k = self::key( $name );
		return isset( $a[ $k ] ) ? $a[ $k ] : null;
	}

	/** Absolute path of the photo for a player: your own upload first, then a freely licensed one from Commons. */
	public static function photo( $name ) {
		self::remember( $name );
		$map = SF_Options::get( 'player_photos', array() );
		$k   = self::key( $name );
		if ( ! empty( $map[ $k ] ) ) {
			$file = get_attached_file( (int) $map[ $k ] );
			if ( $file && is_readable( $file ) ) {
				return $file;
			}
		}
		return self::auto_photo( $name );
	}

	private static function auto_photo( $name ) {
		if ( ! SF_Options::get( 'auto_player_photos', 1 ) ) {
			return null;
		}
		$k   = self::key( $name );
		$all = self::auto_all();
		$e   = isset( $all[ $k ] ) ? $all[ $k ] : null;
		if ( $e && 'ok' === $e['status'] ) {
			$path = SF_Poster::dir()['path'] . $e['file'];
			if ( is_readable( $path ) ) {
				return $path;
			}
		}
		if ( $e && ( 'blocked' === $e['status'] || ( 'none' === $e['status'] && time() - (int) $e['at'] < 30 * DAY_IN_SECONDS ) ) ) {
			return null;
		}
		if ( self::$fetched >= 3 ) {   // A few lookups per run keeps cards quick.
			return null;
		}
		self::$fetched++;
		$found = SF_PlayerPhotos::find( $name );
		if ( ! is_wp_error( $found ) ) {
			$rel = SF_PlayerPhotos::save( $k, $found['url'] );
			if ( ! is_wp_error( $rel ) ) {
				$all[ $k ] = array( 'status' => 'ok', 'name' => $name, 'file' => $rel, 'credit' => $found['credit'], 'licence' => $found['licence'], 'page' => $found['page'], 'at' => time() );
				update_option( self::AUTO, $all, false );
				SF_Log::add( 'Found a free photo of ' . $name . ' on Wikimedia Commons (' . $found['licence'] . ').' );
				return SF_Poster::dir()['path'] . $rel;
			}
		}
		$all[ $k ] = array( 'status' => 'none', 'name' => $name, 'at' => time() );
		update_option( self::AUTO, $all, false );
		return null;
	}

	/** Credit line to add to a post that shows this player's automatic photo. Empty for your own photos and for licences that need none. */
	public static function credit( $name ) {
		$map = SF_Options::get( 'player_photos', array() );
		if ( ! empty( $map[ self::key( $name ) ] ) ) {
			return '';
		}
		$e = self::auto_entry( $name );
		return ( $e && 'ok' === $e['status'] ) ? (string) $e['credit'] : '';
	}

	/** Stop using an automatic photo, and delete the file. */
	public static function block( $key ) {
		$all = self::auto_all();
		if ( isset( $all[ $key ] ) ) {
			if ( ! empty( $all[ $key ]['file'] ) ) {
				@unlink( SF_Poster::dir()['path'] . $all[ $key ]['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			$all[ $key ] = array( 'status' => 'blocked', 'name' => isset( $all[ $key ]['name'] ) ? $all[ $key ]['name'] : $key, 'at' => time() );
			update_option( self::AUTO, $all, false );
		}
	}

	public static function auto_list() {
		return array_filter( self::auto_all(), function ( $e ) {
			return 'ok' === $e['status'];
		} );
	}

	public static function known() {
		$known = get_option( 'sf_known_players', array() );
		return is_array( $known ) ? array_reverse( $known ) : array();
	}
}
