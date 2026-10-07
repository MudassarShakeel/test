<?php
/**
 * Plugin Name:       Mudassar Snippet Studio
 * Plugin URI:        https://mudassar.work/
 * Description:       Safely manage header/footer scripts and PHP, JavaScript, CSS and HTML code snippets with smart conditional logic, revisions, scheduling and an audit log.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mudassar Shakeel
 * Author URI:        https://mudassar.work/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mudassar-snippet-studio
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

define( 'MSST_VERSION', '1.0.0' );
define( 'MSST_FILE', __FILE__ );
define( 'MSST_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSST_URL', plugin_dir_url( __FILE__ ) );
define( 'MSST_SLUG', 'mudassar-snippet-studio' );

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
