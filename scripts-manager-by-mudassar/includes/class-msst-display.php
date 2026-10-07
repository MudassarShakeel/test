<?php
/**
 * Decides whether a snippet shows on the current request.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * "Site Display" and "Device Display" matching.
 */
class MSST_Display {

	/**
	 * Does the device setting allow this request?
	 *
	 * @param string $device all|desktop|mobile.
	 * @return bool
	 */
	public static function device_ok( $device ) {
		if ( 'mobile' === $device ) {
			return wp_is_mobile();
		}
		if ( 'desktop' === $device ) {
			return ! wp_is_mobile();
		}
		return true;
	}

	/**
	 * Does the "Site Display" choice match the current page? Needs the main query.
	 *
	 * @param array $snippet Snippet.
	 * @return bool
	 */
	public static function page_ok( array $snippet ) {
		$targets = $snippet['targets'];
		switch ( $snippet['display_on'] ) {
			case 'site_wide':
				if ( is_singular() ) {
					$id = (int) get_queried_object_id();
					if ( in_array( $id, $targets['ex_pages'], true ) || in_array( $id, $targets['ex_posts'], true ) ) {
						return false;
					}
				}
				return true;
			case 'pages':
				return $targets['pages'] && is_page( $targets['pages'] );
			case 'posts':
				return $targets['posts'] && is_single( $targets['posts'] );
			case 'categories':
				return $targets['categories'] && ( is_category( $targets['categories'] ) || ( is_singular() && has_category( $targets['categories'], get_queried_object_id() ) ) );
			case 'tags':
				return $targets['tags'] && ( is_tag( $targets['tags'] ) || ( is_singular() && has_tag( $targets['tags'], get_queried_object_id() ) ) );
			case 'post_types':
				return $targets['post_types'] && ( is_singular( $targets['post_types'] ) || is_post_type_archive( $targets['post_types'] ) );
			case 'home':
				return is_front_page() || is_home();
			case 'search':
				return is_search();
			case 'archives':
				return is_archive();
			case 'latest':
				return is_home();
		}
		return false; // "Shortcode Only" never shows automatically.
	}

	/**
	 * Does this snippet need the main query before it can be evaluated?
	 *
	 * @param array $snippet Snippet.
	 * @return bool
	 */
	public static function needs_query( array $snippet ) {
		return 'site_wide' !== $snippet['display_on'] || (bool) ( $snippet['targets']['ex_pages'] || $snippet['targets']['ex_posts'] );
	}
}
