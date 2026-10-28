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

	/** Absolute path of the saved photo for a player, or null. */
	public static function photo( $name ) {
		self::remember( $name );
		$map = SF_Options::get( 'player_photos', array() );
		$k   = self::key( $name );
		if ( empty( $map[ $k ] ) ) {
			return null;
		}
		$file = get_attached_file( (int) $map[ $k ] );
		return ( $file && is_readable( $file ) ) ? $file : null;
	}

	public static function known() {
		$known = get_option( 'sf_known_players', array() );
		return is_array( $known ) ? array_reverse( $known ) : array();
	}
}
