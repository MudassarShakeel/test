<?php
/**
 * Plugin Name:       Scripts Manager By Mudassar
 * Plugin URI:        https://mudassar.work/
 * Description:       Add HTML, CSS, JavaScript and PHP snippets to your site with a simple list and form: choose where, which pages, which devices, then turn it ON. Includes export/import and a Safe Mode.
 * Version:           2.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Mudassar Shakeel
 * Author URI:        https://mudassar.work/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       scripts-manager-by-mudassar
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

// Another copy (for example the old "Mudassar Snippet Studio" folder) is already active.
if ( defined( 'MSST_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p><strong>Scripts Manager By Mudassar:</strong> another copy of this plugin is active. Please deactivate and delete the older copy, then activate this one. Your snippets are kept.</p></div>';
		}
	);
	return;
}

define( 'MSST_VERSION', '2.0.2' );
define( 'MSST_FILE', __FILE__ );
define( 'MSST_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSST_URL', plugin_dir_url( __FILE__ ) );
define( 'MSST_SLUG', 'scripts-manager-by-mudassar' );

// Maps MSST_Foo_Bar to includes/class-msst-foo-bar.php. A closure (not a named function) so it can never clash with another copy of the plugin.
spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'MSST_' ) ) {
			return;
		}
		$file = MSST_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'MSST_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MSST_Plugin', 'deactivate' ) );

MSST_Plugin::instance();
