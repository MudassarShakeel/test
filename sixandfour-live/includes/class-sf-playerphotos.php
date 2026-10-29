<?php
/**
 * Finds a freely licensed photo of a player on Wikimedia Commons.
 *
 * Only photos whose licence allows reuse are accepted (Creative Commons BY, BY-SA, CC0 or public domain).
 * Photos from news sites, Cricinfo or search engines are never fetched. Where a licence asks for a credit,
 * the credit line is kept so it can be added to the post.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_PlayerPhotos {

	private static function wiki_base() {
		return apply_filters( 'sf_wikipedia_api', 'https://en.wikipedia.org/w/api.php' );
	}

	private static function commons_base() {
		return apply_filters( 'sf_commons_api', 'https://commons.wikimedia.org/w/api.php' );
	}

	/** Wikimedia asks every client to say who it is. */
	private static function args() {
		return array(
			'timeout'     => 15,
			'redirection' => 2,
			'sslverify'   => true,
			'user-agent'  => 'CricketSportLive/' . ( defined( 'SF_VERSION' ) ? SF_VERSION : '1' ) . ' (' . home_url( '/' ) . '; WordPress plugin; reads one lead photo per player)',
		);
	}

	private static function get_json( $url ) {
		$res = wp_remote_get( $url, self::args() );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$j = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $j ) ? $j : null;
	}

	/** A licence that allows reuse. Returns true for BY, BY-SA, CC0 and public domain, false for NC, ND and anything unclear. */
	public static function licence_ok( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name || preg_match( '/-N[CD]\b|\bN[CD]\b|fair use|non-free|copyrighted/i', $name ) ) {
			return false;
		}
		return (bool) preg_match( '/^(CC[ -]BY|CC0|CC Zero|Public domain|PD)/i', $name );
	}

	/** Public domain and CC0 need no credit. The Creative Commons attribution licences do. */
	public static function credit_needed( $name ) {
		return ! preg_match( '/^(CC0|CC Zero|Public domain|PD)/i', trim( (string) $name ) );
	}

	private static function same_person( $title, $name ) {
		$t = strtolower( preg_replace( '/\s*\(.*?\)\s*/', ' ', $title ) );
		foreach ( preg_split( '/\s+/', strtolower( trim( $name ) ), -1, PREG_SPLIT_NO_EMPTY ) as $w ) {
			if ( false === strpos( $t, $w ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Look a player up. Returns [ url, credit, licence, page ] for a usable photo, or WP_Error.
	 */
	public static function find( $name ) {
		$name = trim( preg_replace( '/[^\p{L}\s\'.-]/u', '', (string) $name ) );
		if ( mb_strlen( $name ) < 5 || false === strpos( $name, ' ' ) ) {
			return new WP_Error( 'sf_pp_name', 'Needs a full name.' );
		}
		// 1. Find the cricketer's article.
		$s = self::get_json( add_query_arg( array( 'action' => 'query', 'list' => 'search', 'srsearch' => $name . ' cricketer', 'srlimit' => 4, 'format' => 'json', 'formatversion' => 2 ), self::wiki_base() ) );
		if ( ! $s || empty( $s['query']['search'] ) ) {
			return new WP_Error( 'sf_pp_none', 'No article found.' );
		}
		$page = null;
		foreach ( $s['query']['search'] as $hit ) {
			if ( ! self::same_person( $hit['title'], $name ) ) {
				continue;
			}
			$p = self::get_json( add_query_arg( array( 'action' => 'query', 'titles' => $hit['title'], 'redirects' => 1, 'prop' => 'pageimages|pageprops', 'piprop' => 'name', 'ppprop' => 'wikibase-shortdesc', 'format' => 'json', 'formatversion' => 2 ), self::wiki_base() ) );
			$pg = ( $p && ! empty( $p['query']['pages'][0] ) ) ? $p['query']['pages'][0] : null;
			$desc = $pg && isset( $pg['pageprops']['wikibase-shortdesc'] ) ? $pg['pageprops']['wikibase-shortdesc'] : '';
			if ( $pg && preg_match( '/cricket/i', $desc ) && ! empty( $pg['pageimage'] ) ) {
				$page = $pg;
				break;
			}
		}
		if ( ! $page ) {
			return new WP_Error( 'sf_pp_none', 'No matching cricketer with a photo.' );
		}
		// 2. The photo must be on Commons, where every file has a licence.
		$file = 'File:' . $page['pageimage'];
		$c    = self::get_json( add_query_arg( array( 'action' => 'query', 'titles' => $file, 'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata|mime', 'iiurlwidth' => 800, 'format' => 'json', 'formatversion' => 2 ), self::commons_base() ) );
		$cp   = ( $c && ! empty( $c['query']['pages'][0] ) ) ? $c['query']['pages'][0] : null;
		if ( ! $cp || ! empty( $cp['missing'] ) || empty( $cp['imageinfo'][0] ) ) {
			return new WP_Error( 'sf_pp_nonfree', 'The article photo is not on Commons.' );
		}
		$ii   = $cp['imageinfo'][0];
		$meta = isset( $ii['extmetadata'] ) ? $ii['extmetadata'] : array();
		$lic  = isset( $meta['LicenseShortName']['value'] ) ? $meta['LicenseShortName']['value'] : '';
		if ( ! self::licence_ok( $lic ) || empty( $ii['thumburl'] ) || ! preg_match( '#^https://upload\.wikimedia\.org/#', $ii['thumburl'] ) || ! preg_match( '#^image/(jpeg|png)$#', isset( $ii['mime'] ) ? $ii['mime'] : '' ) ) {
			return new WP_Error( 'sf_pp_licence', 'The photo licence does not allow reuse.' );
		}
		$artist = isset( $meta['Artist']['value'] ) ? trim( wp_strip_all_tags( html_entity_decode( $meta['Artist']['value'], ENT_QUOTES ) ) ) : '';
		$artist = '' !== $artist ? mb_substr( preg_replace( '/\s+/', ' ', $artist ), 0, 80 ) : 'Wikimedia Commons contributor';
		$credit = self::credit_needed( $lic ) ? 'Photo: ' . $artist . ', ' . $lic . ', via Wikimedia Commons' : '';
		return array( 'url' => $ii['thumburl'], 'credit' => $credit, 'licence' => $lic, 'page' => 'https://commons.wikimedia.org/wiki/' . rawurlencode( str_replace( ' ', '_', $file ) ) );
	}

	/** Download the photo and save a clean copy in the plugin's picture folder. Returns the relative path or WP_Error. */
	public static function save( $key, $url ) {
		$res = wp_remote_get( $url, self::args() + array( 'limit_response_size' => 6 * MB_IN_BYTES ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'sf_pp_dl', 'The photo could not be downloaded.' );
		}
		$body = wp_remote_retrieve_body( $res );
		$tmp  = tempnam( sys_get_temp_dir(), 'sfpp' );
		file_put_contents( $tmp, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$ext  = SF_Cards::output_extension( $tmp );
		$sub  = 'players/';
		$name = md5( $key ) . '.' . $ext;
		$dest = SF_Poster::dir()['path'] . $sub . $name;
		SF_Poster::protect_dir();
		$cards = new SF_Cards();
		$out   = $cards->clean_image( $tmp, $dest, false, 'br' );   // Re-drawn, so hidden data and code are removed.
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return is_wp_error( $out ) ? $out : $sub . $name;
	}
}
