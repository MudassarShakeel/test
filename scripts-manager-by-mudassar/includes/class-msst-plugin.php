<?php
/**
 * Plugin bootstrap.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component together.
 */
final class MSST_Plugin {

	/**
	 * Singleton.
	 *
	 * @var MSST_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return MSST_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( 'MSST_Snippets', 'maybe_install' ) );
		MSST_Runner::init();
		if ( is_admin() ) {
			MSST_Admin::init();
		}
	}

	/**
	 * Activation: table, capability and safe-mode secret.
	 */
	public static function activate() {
		MSST_Snippets::install();
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( MSST_Security::CAP );
		}
		MSST_Security::ensure_secret();
		if ( false === get_option( 'msst_settings', false ) ) {
			add_option( 'msst_settings', MSST_Settings::defaults(), '', false );
		}
	}

	/**
	 * Deactivation: nothing is deleted.
	 */
	public static function deactivate() {
		MSST_Snippets::flush_cache();
	}
}
