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
		add_action( 'init', array( 'MSST_Snippets', 'register_post_type' ), 0 );
		MSST_Runner::init();
		MSST_Page_Code::init();
		if ( is_admin() ) {
			MSST_Admin::init();
		}
	}

	/**
	 * Activation: capability and safe-mode secret.
	 */
	public static function activate() {
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
		wp_cache_delete( 'active', 'msst' );
	}
}
