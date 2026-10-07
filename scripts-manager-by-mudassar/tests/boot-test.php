<?php
/** Loads the real main plugin file like WordPress does, runs activation and fires the hooks. */
define( 'MSST_TEST_NO_CONSTANTS', true );
require __DIR__ . '/bootstrap.php';
$GLOBALS['H'] = array();
$GLOBALS['ACT'] = array();
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['H'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['H'][ $h ][] = $cb; }
function add_shortcode( $t, $cb ) { $GLOBALS['H']['shortcode_' . $t][] = $cb; }
function register_activation_hook( $f, $cb ) { $GLOBALS['ACT'][] = $cb; }
function register_deactivation_hook( $f, $cb ) {}
function is_admin() { return true; }
function get_role() { return new class { function add_cap( $c ) { $GLOBALS['CAP_ADDED'] = $c; } }; }
function add_menu_page( ...$a ) { $GLOBALS['MENU'][] = $a; return 'toplevel_page_' . $a[3]; }
function add_submenu_page( ...$a ) { $GLOBALS['MENU'][] = $a; return 'x'; }
function register_shutdown_function_x() {}
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }

require dirname( __DIR__ ) . '/scripts-manager-by-mudassar.php';
ok( defined( 'MSST_VERSION' ), 'main file loads' );
foreach ( $GLOBALS['ACT'] as $cb ) { call_user_func( $cb ); }
ok( 'msst_manage_snippets' === ( $GLOBALS['CAP_ADDED'] ?? '' ), 'activation grants the capability to administrators' );
ok( '1' === get_option( 'msst_db_version' ) && false !== strpos( $GLOBALS['DBDELTA'] ?? '', 'CREATE TABLE wp_msst_snippets' ), 'activation creates the snippets table' );
ok( strlen( (string) get_option( 'msst_safe_secret' ) ) >= 24, 'activation creates the safe-mode secret' );
foreach ( array( 'plugins_loaded', 'init', 'admin_menu' ) as $hook ) {
	foreach ( $GLOBALS['H'][ $hook ] ?? array() as $cb ) { call_user_func( $cb ); }
}
$GLOBALS['MENU'] = $GLOBALS['MENU'] ?? array();
$slugs = array_map( function ( $m ) { return $m[ 3 ] ?? ''; }, $GLOBALS['MENU'] );
ok( in_array( 'scripts-manager', $slugs, true ), 'top-level menu "Scripts Manager" is registered' );
foreach ( array( 'scripts-manager', 'scripts-manager-add', 'scripts-manager-tools', 'scripts-manager-settings' ) as $s ) {
	ok( in_array( $s, array_map( function ( $m ) { return $m[ 4 ] ?? $m[ 3 ] ?? ''; }, $GLOBALS['MENU'] ), true ) || in_array( $s, $slugs, true ), "menu page: $s" );
}
$missing = array();
foreach ( $GLOBALS['H'] as $hook => $cbs ) {
	foreach ( $cbs as $cb ) {
		if ( is_array( $cb ) && ! is_callable( $cb ) ) { $missing[] = "$hook -> " . ( is_object( $cb[0] ) ? get_class( $cb[0] ) : $cb[0] ) . '::' . $cb[1]; }
	}
}
ok( ! $missing, 'every hook callback exists' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
foreach ( array( 'save_snippet', 'toggle', 'delete', 'duplicate', 'bulk', 'export', 'import', 'save_settings', 'regen_secret', 'test_mode' ) as $h ) {
	ok( ! empty( $GLOBALS['H'][ 'admin_post_msst_' . $h ] ), "admin-post handler: $h" );
	ok( empty( $GLOBALS['H'][ 'admin_post_nopriv_msst_' . $h ] ), "no logged-out handler: $h" );
}
finish();
