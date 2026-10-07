<?php
/**
 * Renders every admin screen against a tiny fake WordPress and checks the HTML.
 * Run: php tests/render-views.php
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
define( 'ABSPATH', __DIR__ . '/' );
define( 'MSST_DIR', dirname( __DIR__ ) . '/' );
define( 'MSST_FILE', MSST_DIR . 'scripts-manager-by-mudassar.php' );
define( 'MSST_URL', 'http://x.test/wp-content/plugins/scripts-manager-by-mudassar/' );
define( 'MSST_SLUG', 'scripts-manager-by-mudassar' );
define( 'MSST_VERSION', '1.1.0' );
define( 'PHP_URL_PATH_', 5 );

// ---- fake WordPress -------------------------------------------------------
$GLOBALS['META']  = array();
$GLOBALS['POSTS'] = array();
$GLOBALS['OPT']   = array();
$GLOBALS['wp_version'] = '6.8';
function __( $s ) { return $s; }
function _e( $s ) { echo $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return addslashes( (string) $s ); }
function wp_kses( $s ) { return $s; }
function wp_kses_post( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function wp_slash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function add_query_arg( $a, $b = '', $c = '' ) { if ( is_array( $a ) ) { $url = $b; $q = $a; } else { $url = $c; $q = array( $a => $b ); } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $q ); }
function admin_url( $p = '' ) { return 'http://x.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'http://x.test' . $p; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=abc123'; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="abc123">'; }
function checked( $a, $b = true ) { if ( $a == $b ) { echo ' checked="checked"'; } }
function selected( $a, $b = true ) { if ( $a == $b ) { echo ' selected="selected"'; } }
function disabled( $a, $b = true ) { if ( $a == $b ) { echo ' disabled="disabled"'; } }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function wp_list_pluck( $l, $f ) { $o = array(); foreach ( $l as $k => $v ) { $o[ $k ] = $v[ $f ]; } return $o; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function size_format( $n ) { return $n . ' B'; }
function paginate_links() { return ''; }
function is_multisite() { return false; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function plugin_basename( $f ) { return basename( $f ); }
function wp_roles() { return (object) array( 'roles' => array( 'administrator' => array( 'name' => 'Administrator' ) ) ); }
function translate_user_role( $s ) { return $s; }
function get_post_types() { return array( 'post' => (object) array( 'labels' => (object) array( 'singular_name' => 'Post' ) ) ); }
function wp_generate_password() { return 'secretsecretsecretsecretsecret12'; }
function wp_salt() { return 'salt'; }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 1; }
function get_user_meta() { return ''; }
function current_user_can() { return true; }
class FakeUser { public $user_login = 'admin'; public $roles = array( 'administrator' ); function exists() { return true; } }
function wp_get_current_user() { return new FakeUser(); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_cache_get() { return false; }
function wp_cache_set() {}
function wp_cache_delete() {}
function get_post( $id ) { return isset( $GLOBALS['POSTS'][ $id ] ) ? (object) $GLOBALS['POSTS'][ $id ] : null; }
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['META'][ $id ][ $k ] ) ? $GLOBALS['META'][ $id ][ $k ] : ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['META'][ $id ][ $k ] = $v; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['META'][ $id ][ $k ] ); }
function wp_upload_dir() { return array( 'basedir' => sys_get_temp_dir() . '/msst-render-uploads' ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_delete_file( $f ) { unlink( $f ); }
class WP_Error { function __construct( $c = '', $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }
class WP_Query {
	public $posts = array(); public $found_posts = 0; public $max_num_pages = 1;
	function __construct( $args ) { $this->posts = array_keys( $GLOBALS['POSTS'] ); $this->found_posts = count( $this->posts ); }
}
class WP_Post {}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
// wp_cache etc. unused beyond this.

foreach ( glob( MSST_DIR . 'includes/class-msst-*.php' ) as $f ) {
	require_once $f;
}

// ---- seed data ------------------------------------------------------------
$GLOBALS['POSTS'][5] = array( 'ID' => 5, 'post_type' => 'msst_snippet', 'post_title' => 'Hide admin bar' );
$GLOBALS['META'][5]  = array( '_msst_type' => 'php', '_msst_code' => "add_filter('a','b');", '_msst_location' => 'everywhere', '_msst_param' => 1, '_msst_priority' => 10, '_msst_active' => 1, '_msst_conditions' => array( array( array( 'type' => 'page_type', 'op' => 'is', 'value' => 'front_page' ) ) ), '_msst_start' => 0, '_msst_end' => 0 );
$GLOBALS['META'][5]['_msst_sig'] = MSST_Security::sign( MSST_Snippets::payload( 5, 'php', "add_filter('a','b');" ) );
$GLOBALS['POSTS'][6] = array( 'ID' => 6, 'post_type' => 'msst_snippet', 'post_title' => 'Broken one' );
$GLOBALS['META'][6]  = array( '_msst_type' => 'html', '_msst_code' => '<b>x</b>', '_msst_location' => 'footer', '_msst_param' => 1, '_msst_priority' => 10, '_msst_active' => 0, '_msst_error' => 'boom happened', '_msst_start' => 0, '_msst_end' => 0 );
$GLOBALS['META'][6]['_msst_sig'] = MSST_Security::sign( MSST_Snippets::payload( 6, 'html', '<b>x</b>' ) );
MSST_Logger::error( 6, 'Broken one', "boom happened\nforged line" );
MSST_Logger::audit( 'Saved snippet' );
update_option( 'msst_revisions_dummy', 1 );
$GLOBALS['META'][5]['_msst_revisions'] = array( array( 'time' => 1700000000, 'user' => 'admin', 'type' => 'php', 'code' => "old();\nline2" ) );

$pass = 0; $fail = 0;
function ok( $c, $n ) { global $pass, $fail; if ( $c ) { ++$pass; echo "  ok   $n\n"; } else { ++$fail; echo "  FAIL $n\n"; } }

function render_tab( $tab, $get = array() ) {
	$_GET = array_merge( array( 'tab' => $tab ), $get );
	ob_start();
	$tabv = MSST_Admin::current_tab();
	include MSST_DIR . 'admin/views/header.php';
	$tab = $tabv;
	include MSST_DIR . 'admin/views/' . $tabv . '.php';
	include MSST_DIR . 'admin/views/footer.php';
	return ob_get_clean();
}

// MSST_Admin::PAGE etc. need the class; loaded above.
foreach ( array_keys( MSST_Admin::tabs() ) as $tab ) {
	$extra = in_array( $tab, array( 'edit', 'revisions' ), true ) ? array( 'snippet' => 5, 'compare' => 0 ) : array();
	try {
		$html = render_tab( $tab, $extra );
		ok( strlen( $html ) > 500 && false !== strpos( $html, 'Scripts Manager By Mudassar' ) && false !== strpos( $html, 'utm_source=scripts-manager-by-mudassar' ), "tab '$tab' renders with branding + UTM links" );
	} catch ( Throwable $e ) {
		while ( ob_get_level() ) { ob_end_clean(); }
		ok( false, "tab '$tab' threw: " . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine() );
	}
}
$edit = render_tab( 'edit', array( 'snippet' => 5 ) );
ok( false !== strpos( $edit, 'name="msst_pages"' ) && 1 === preg_match( '/value="some"\s+checked="checked"/', $edit ), 'edit: existing rules => "Only some pages" selected' );
ok( false !== strpos( $edit, 'name="msst_active" value="1"' ) && false !== strpos( $edit, 'Save and turn OFF' ), 'edit: active snippet offers Save changes / Save and turn OFF' );
$new = render_tab( 'edit' );
ok( false !== strpos( $new, 'Save and turn ON' ) && false !== strpos( $new, 'Save as OFF' ), 'edit: new snippet offers Save as OFF / Save and turn ON' );
$snip = render_tab( 'snippets' );
ok( false !== strpos( $snip, 'Turned off automatically' ) && false !== strpos( $snip, 'Fix it' ), 'snippets: broken snippet shows a plain-words warning and Fix it' );
ok( false !== strpos( $snip, 'action=msst_toggle' ) && false !== strpos( $snip, 'class="msst-toggle is-on"' ), 'snippets: toggle links carry the action' );
$logs = render_tab( 'logs' );
ok( false !== strpos( $logs, 'Error log file' ) && false !== strpos( $logs, 'action=msst_download_log' ), 'logs: error log file section with download link' );
ok( 1 === preg_match( '/ERROR snippet#6 &quot;Broken one&quot;: boom happened forged line/', $logs ), 'logs: file content shown, injected newline neutralised' );
$empty = array_sum( array_map( 'strlen', array() ) );

if ( in_array( '--dump', $argv, true ) ) {
	$out = __DIR__ . '/out';
	@mkdir( $out );
	foreach ( array_keys( MSST_Admin::tabs() ) as $tab ) {
		$extra = in_array( $tab, array( 'edit', 'revisions' ), true ) ? array( 'snippet' => 5, 'compare' => 0 ) : array();
		$body  = render_tab( $tab, $extra );
		$_GET  = array_merge( array( 'tab' => $tab ), $extra );
		$json  = json_encode( MSST_Admin::script_data() );
		file_put_contents( "$out/$tab.html", '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../../assets/admin.css"><style>body{margin:0;background:#f0f0f1;font:13px -apple-system,Arial,sans-serif;color:#1d2327}.wp-side{float:left;width:160px;min-height:100vh;background:#1d2327}#wpbody{margin-left:160px;padding:10px}</style></head><body><div class="wp-side"></div><div id="wpbody"><div class="wrap msst">' . $body . '</div></div><script>window.msstData=' . $json . ';</script><script src="../../assets/admin.js"></script></body></html>' );
	}
	file_put_contents( "$out/new.html", str_replace( '', '', file_get_contents( "$out/edit.html" ) ) );
}
echo "\n$pass passed, $fail failed\n";
// cleanup temp uploads
foreach ( (array) glob( sys_get_temp_dir() . '/msst-render-uploads/scripts-manager-logs/{,.}*', GLOB_BRACE ) as $f ) { if ( is_file( $f ) ) { unlink( $f ); } }
exit( $fail ? 1 : 0 );
