<?php
/**
 * Plugin Name:       Six & Four Live
 * Plugin URI:        https://c.mudassar.work
 * Description:       Posts cricket scores, toss, results, match summaries, milestones, polls and rankings to your Facebook Page as clean score-card pictures. You can also add your own pictures and posts by hand.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mudassar
 * Author URI:        https://mudassar.work
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sixandfour-live
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SF_VERSION', '1.0.0' );
define( 'SF_FILE', __FILE__ );
define( 'SF_DIR', plugin_dir_path( __FILE__ ) );
define( 'SF_URL', plugin_dir_url( __FILE__ ) );

require_once SF_DIR . 'includes/class-sf-options.php';
require_once SF_DIR . 'includes/class-sf-db.php';
require_once SF_DIR . 'includes/class-sf-api.php';
require_once SF_DIR . 'includes/class-sf-facebook.php';
require_once SF_DIR . 'includes/class-sf-teams.php';
require_once SF_DIR . 'includes/class-sf-cards.php';
require_once SF_DIR . 'includes/class-sf-samples.php';
require_once SF_DIR . 'includes/class-sf-poster.php';
require_once SF_DIR . 'includes/class-sf-engine.php';
require_once SF_DIR . 'includes/class-sf-plugin.php';

if ( is_admin() ) {
	require_once SF_DIR . 'includes/class-sf-admin.php';
}

register_activation_hook( __FILE__, array( 'SF_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SF_Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'SF_Plugin', 'init' ) );
