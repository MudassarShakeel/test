<?php
/**
 * Plugin settings.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Option access.
 */
class MSST_Settings {

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'php_enabled'         => 1,
			'auto_disable'        => 1,
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * Read settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( 'msst_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Save settings (booleans only).
	 *
	 * @param array $values Booleans keyed by setting name.
	 */
	public static function save( array $values ) {
		$clean = array();
		foreach ( self::defaults() as $key => $unused ) {
			$clean[ $key ] = empty( $values[ $key ] ) ? 0 : 1;
		}
		update_option( 'msst_settings', $clean, false );
	}
}
