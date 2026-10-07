<?php
/**
 * Plugin settings and global header/footer storage.
 *
 * @package MudassarSnippetStudio
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
	 * Save settings from sanitized values.
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

	/**
	 * Global header/body/footer code and integration IDs.
	 *
	 * @return array
	 */
	public static function get_global() {
		$saved = get_option( 'msst_global', array() );
		$saved = is_array( $saved ) ? $saved : array();
		return wp_parse_args(
			$saved,
			array(
				'header' => '',
				'body'   => '',
				'footer' => '',
				'ga4'    => '',
				'gtm'    => '',
				'meta'   => '',
				'tiktok' => '',
				'sig'    => '',
			)
		);
	}

	/**
	 * Persist global code. The raw code is signed so tampering in the database is detected.
	 *
	 * @param array $data Global data.
	 */
	public static function save_global( array $data ) {
		$data['sig'] = MSST_Security::sign( 'global|' . $data['header'] . '|' . $data['body'] . '|' . $data['footer'] );
		update_option( 'msst_global', $data, false );
	}

	/**
	 * Validate an integration ID against a strict pattern.
	 *
	 * @param string $kind  ga4|gtm|meta|tiktok.
	 * @param string $value Raw value.
	 * @return string Clean ID or empty string.
	 */
	public static function clean_integration_id( $kind, $value ) {
		$value    = strtoupper( trim( (string) $value ) );
		$patterns = array(
			'ga4'    => '/^G-[A-Z0-9]{4,14}$/',
			'gtm'    => '/^GTM-[A-Z0-9]{4,10}$/',
			'meta'   => '/^[0-9]{5,20}$/',
			'tiktok' => '/^[A-Z0-9]{8,30}$/',
		);
		if ( isset( $patterns[ $kind ] ) && preg_match( $patterns[ $kind ], $value ) ) {
			return $value;
		}
		return '';
	}
}
