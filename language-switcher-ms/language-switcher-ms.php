<?php
/**
 * Plugin Name:       Language Switcher by Mudassar
 * Plugin URI:        https://mudassar.work/
 * Description:       Lightweight Google Translate language switcher with custom styles, size controls, colors, fonts, and keyword exclusion.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Mudassar Shakeel
 * Author URI:        https://mudassar.work/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       language-switcher-ms
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LS_MS_VERSION', '1.4.0');
define('LS_MS_FILE', __FILE__);
define('LS_MS_DIR', plugin_dir_path(__FILE__));
define('LS_MS_URL', plugin_dir_url(__FILE__));

require_once LS_MS_DIR . 'includes/class-ls-ms-languages.php';
require_once LS_MS_DIR . 'includes/class-ls-ms-settings.php';
require_once LS_MS_DIR . 'includes/class-ls-ms-frontend.php';

function ls_ms_init() {
    $settings = new LS_MS_Settings();
    new LS_MS_Frontend($settings);
}
add_action('plugins_loaded', 'ls_ms_init');

/**
 * Add a "Settings" link on the Plugins screen.
 */
function ls_ms_action_links($links) {
    if (current_user_can('manage_options')) {
        $url = admin_url('options-general.php?page=language-switcher-ms');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'language-switcher-ms') . '</a>');
    }
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ls_ms_action_links');
