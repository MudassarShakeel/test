<?php
/**
 * Branding links (with UTM tags).
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Author branding helpers.
 */
class MSST_Brand {

	const AUTHOR  = 'Mudassar Shakeel';
	const WEBSITE = 'https://mudassar.work/';
	const CONTACT = 'https://mudassar.work/contact/';

	/**
	 * Build a tagged link. No site data is added to the URL.
	 *
	 * @param string $target  website|contact.
	 * @param string $content Which button/link (utm_content).
	 * @return string
	 */
	public static function url( $target, $content ) {
		$base = 'contact' === $target ? self::CONTACT : self::WEBSITE;
		return add_query_arg(
			array(
				'utm_source'   => 'scripts-manager-by-mudassar',
				'utm_medium'   => 'wordpress-plugin',
				'utm_campaign' => 'plugin-' . $target,
				'utm_content'  => sanitize_key( $content ),
			),
			$base
		);
	}

	/**
	 * Anchor markup for a branded link.
	 *
	 * @param string $target  website|contact.
	 * @param string $content utm_content value.
	 * @param string $label   Link text.
	 * @param string $css_class CSS classes.
	 * @return string
	 */
	public static function link( $target, $content, $label, $css_class = '' ) {
		return sprintf(
			'<a href="%1$s" class="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
			esc_url( self::url( $target, $content ) ),
			esc_attr( $css_class ),
			esc_html( $label )
		);
	}
}
