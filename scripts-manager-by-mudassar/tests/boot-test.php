<?php
/** Loads the real main plugin file in a fake WP, runs activation and fires hooks. php tests/boot-test.php */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['H'] = array(); $GLOBALS['OPT'] = array(); $GLOBALS['ACT'] = array();
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['H'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['H'][ $h ][] = $cb; }
function add_shortcode( $t, $cb ) { $GLOBALS['H']['shortcode_' . $t][] = $cb; }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://x.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function register_activation_hook( $f, $cb ) { $GLOBALS['ACT'][] = $cb; }
function register_deactivation_hook( $f, $cb ) { $GLOBALS['DEACT'][] = $cb; }
function is_admin() { return true; }
function get_role() { return new class { function add_cap() { echo "cap added\n"; } }; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function add_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function wp_generate_password() { return str_repeat( 'a', 32 ); }
function wp_cache_delete() {}
function register_post_type() { echo "cpt registered\n"; }
function __( $s ) { return $s; }
function add_options_page() { echo "menu added\n"; }
function add_meta_box() {}
function get_post_types() { return array( 'post' ); }
function wp_roles() { return (object) array( 'roles' => array() ); }
function wp_list_pluck( $l, $f ) { return array_map( function ( $v ) use ( $f ) { return $v[ $f ]; }, $l ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function absint( $n ) { return abs( (int) $n ); }
function get_post() { return null; }
function wp_enqueue_style() {} function wp_enqueue_script() {} function wp_localize_script() {} function wp_enqueue_code_editor() { return array(); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function get_post_type_object() { return null; }
function is_user_logged_in() { return false; }
function get_user_meta() { return ''; }
function register_shutdown_function_x() {}
function wp_cache_get() { return false; }
function wp_cache_set() {}
function is_singular() { return false; }
function in_the_loop() { return false; }
function is_main_query() { return false; }
class WP_Query { public $posts = array(); function __construct() {} }

require dirname( __DIR__ ) . '/scripts-manager-by-mudassar.php';
echo "main file loaded OK. version=" . MSST_VERSION . "\n";
foreach ( $GLOBALS['ACT'] as $cb ) { call_user_func( $cb ); }
echo "activation OK\n";
foreach ( array( 'init', 'admin_menu' ) as $hook ) {
	foreach ( $GLOBALS['H'][ $hook ] ?? array() as $cb ) { call_user_func( $cb ); }
}
foreach ( $GLOBALS['H'] as $hook => $cbs ) {
	foreach ( $cbs as $cb ) {
		if ( is_array( $cb ) && ! is_callable( $cb ) ) { echo "NOT CALLABLE: $hook -> " . ( is_object( $cb[0] ) ? get_class( $cb[0] ) : $cb[0] ) . '::' . $cb[1] . "\n"; $bad = true; }
	}
}
echo empty( $bad ) ? "all hook callbacks exist\n" : "MISSING CALLBACKS\n";
