<?php
/**
 * Team names, flags, your own uploaded logos, and initials badges.
 *
 * Official board and league logos are trademarks and are not bundled. A flag is used for a
 * national team, and your own upload replaces it for any team.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Teams {

	/** Lower-case team name to flag file (assets/flags/{code}.png). */
	private static $flags = array(
		'england'                  => 'eng',
		'australia'                => 'aus',
		'india'                    => 'ind',
		'pakistan'                 => 'pak',
		'new zealand'              => 'nzl',
		'south africa'             => 'rsa',
		'sri lanka'                => 'sri',
		'bangladesh'               => 'ban',
		'afghanistan'              => 'afg',
		'ireland'                  => 'ire',
		'zimbabwe'                 => 'zim',
		'netherlands'              => 'ned',
		'scotland'                 => 'sco',
		'united states of america' => 'usa',
		'united states'            => 'usa',
		'usa'                      => 'usa',
		'canada'                   => 'can',
		'united arab emirates'     => 'uae',
		'uae'                      => 'uae',
		'nepal'                    => 'nep',
		'oman'                     => 'oma',
		'namibia'                  => 'nam',
		'papua new guinea'         => 'png',
	);

	/** The national teams the plugin has a flag for, for the Teams screen. */
	public static function flag_names() {
		return array(
			'England', 'Australia', 'India', 'Pakistan', 'New Zealand', 'South Africa', 'Sri Lanka', 'Bangladesh',
			'Afghanistan', 'Ireland', 'Zimbabwe', 'Netherlands', 'Scotland', 'USA', 'Canada', 'UAE', 'Nepal', 'Oman', 'Namibia', 'Papua New Guinea',
		);
	}

	private static function key( $name ) {
		$k = strtolower( trim( (string) $name ) );
		$k = preg_replace( '/\s+(women|womens|women\'s|men|u-?19|a)$/i', '', $k );
		return trim( $k );
	}

	public static function remember( $name ) {
		if ( '' === $name || 'TBC' === $name ) {
			return;
		}
		$known = get_option( 'sf_known_teams', array() );
		$known = is_array( $known ) ? $known : array();
		if ( ! in_array( $name, $known, true ) ) {
			$known[] = $name;
			update_option( 'sf_known_teams', array_slice( $known, -200 ), false );
		}
	}

	public static function initials( $name, $short = '' ) {
		$short = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $short ) );
		if ( '' !== $short && strlen( $short ) <= 3 ) {
			return $short;
		}
		$words = preg_split( '/\s+/', trim( (string) $name ), -1, PREG_SPLIT_NO_EMPTY );
		$out   = '';
		foreach ( $words as $w ) {
			$out .= strtoupper( mb_substr( $w, 0, 1 ) );
			if ( mb_strlen( $out ) >= 3 ) {
				break;
			}
		}
		return '' === $out ? '?' : $out;
	}

	/**
	 * Resolve how to draw a team.
	 *
	 * @return array [ name, kind (flag|logo|badge), path, initials ]
	 */
	public static function resolve( $name, $short = '' ) {
		$name = (string) $name;
		self::remember( $name );
		$k    = self::key( $name );
		$logos = SF_Options::get( 'team_logos', array() );
		if ( isset( $logos[ $k ] ) && $logos[ $k ] ) {
			$file = get_attached_file( (int) $logos[ $k ] );
			if ( $file && is_readable( $file ) ) {
				return array( 'name' => $name, 'kind' => 'logo', 'path' => $file, 'initials' => self::initials( $name, $short ) );
			}
		}
		if ( isset( self::$flags[ $k ] ) ) {
			$path = SF_DIR . 'assets/flags/' . self::$flags[ $k ] . '.png';
			if ( is_readable( $path ) ) {
				return array( 'name' => $name, 'kind' => 'flag', 'path' => $path, 'initials' => self::initials( $name, $short ) );
			}
		}
		return array( 'name' => $name, 'kind' => 'badge', 'path' => null, 'initials' => self::initials( $name, $short ) );
	}

	public static function key_for( $name ) {
		return self::key( $name );
	}
}
