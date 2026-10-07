<?php
/**
 * Plugin Name:       Scripts Manager By Mudassar
 * Plugin URI:        https://mudassar.work/
 * Description:       Safely add header/footer scripts and PHP, JavaScript, CSS and HTML snippets to your site – with simple step-by-step screens, conditional logic, history, scheduling and a clear problem log.
 * Version:           1.1.0
 * Requires at least: 6.0
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

define( 'MSST_VERSION', '1.1.0' );
define( 'MSST_FILE', __FILE__ );
define( 'MSST_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSST_URL', plugin_dir_url( __FILE__ ) );
define( 'MSST_SLUG', 'scripts-manager-by-mudassar' );

/**
 * Maps MSST_Foo_Bar to includes/class-msst-foo-bar.php.
 *
 * @param string $class_name Class name.
 */
function msst_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'MSST_' ) ) {
		return;
	}
	$file = MSST_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'msst_autoload' );

register_activation_hook( __FILE__, array( 'MSST_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MSST_Plugin', 'deactivate' ) );

MSST_Plugin::instance();
