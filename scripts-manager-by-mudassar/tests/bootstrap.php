<?php
/**
 * Tiny stand-in for WordPress used by the tests. The database is a real SQLite
 * connection, so the plugin's SQL really runs. Not part of the plugin zip.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$tmp = sys_get_temp_dir() . '/msst-wp-' . getmypid() . '/';
@mkdir( $tmp . 'wp-admin/includes', 0777, true );
file_put_contents( $tmp . 'wp-admin/includes/upgrade.php', "<?php\nfunction dbDelta( \$sql ) { \$GLOBALS['DBDELTA'] = \$sql; return array(); }\n" );
define( 'ABSPATH', $tmp );
if ( ! defined( 'MSST_TEST_NO_CONSTANTS' ) ) {
	// The boot test lets the real main plugin file define these.
	define( 'MSST_DIR', dirname( __DIR__ ) . '/' );
	define( 'MSST_FILE', MSST_DIR . 'scripts-manager-by-mudassar.php' );
	define( 'MSST_URL', 'http://x.test/wp-content/plugins/scripts-manager-by-mudassar/' );
	define( 'MSST_SLUG', 'scripts-manager-by-mudassar' );
	define( 'MSST_VERSION', '2.0.0' );
}
define( 'ARRAY_A', 'ARRAY_A' );
register_shutdown_function(
	static function () use ( $tmp ) {
		@unlink( $tmp . 'wp-admin/includes/upgrade.php' );
		@rmdir( $tmp . 'wp-admin/includes' );
		@rmdir( $tmp . 'wp-admin' );
		@rmdir( $tmp );
	}
);

$GLOBALS['OPT']  = array();
$GLOBALS['Q']    = array(); // Fake "current page" for conditional tags.
$GLOBALS['USER'] = array( 'unfiltered_html' => true, 'msst_manage_snippets' => true );
$GLOBALS['wp_version'] = '6.8';

/* ---- escaping, sanitising, i18n ---- */
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses_post( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $s ); }
function wp_unslash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error { public $m; function __construct( $c = '', $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }

/* ---- urls ---- */
function add_query_arg( $a, $b = '', $c = '' ) { if ( is_array( $a ) ) { $url = $b; $q = $a; } else { $url = $c; $q = array( $a => $b ); } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $q ); }
function admin_url( $p = '' ) { return 'http://x.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'http://x.test' . $p; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=abc123'; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="abc123">'; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://x.test/wp-content/plugins/scripts-manager-by-mudassar/'; }
function paginate_links() { return ''; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function checked( $a, $b = true ) { if ( $a == $b ) { echo ' checked="checked"'; } }
function selected( $a, $b = true ) { if ( $a == $b ) { echo ' selected="selected"'; } }
function disabled( $a, $b = true ) { if ( $a == $b ) { echo ' disabled="disabled"'; } }

/* ---- options, cache, users ---- */
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function add_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['OPT'][ $k ] ); return true; }
function set_transient( $k, $v ) { $GLOBALS['OPT']['_t_' . $k] = $v; }
function get_transient( $k ) { return get_option( '_t_' . $k, false ); }
function delete_transient( $k ) { delete_option( '_t_' . $k ); }
function wp_cache_get( $k, $g = '' ) { return $GLOBALS['CACHE'][ $g . $k ] ?? false; }
function wp_cache_set( $k, $v, $g = '' ) { $GLOBALS['CACHE'][ $g . $k ] = $v; }
function wp_cache_delete( $k, $g = '' ) { unset( $GLOBALS['CACHE'][ $g . $k ] ); }
function wp_salt() { return 'test-salt-123'; }
function wp_generate_password() { return str_repeat( 'k', 32 ); }
function current_user_can( $c ) { return ! empty( $GLOBALS['USER'][ $c ] ); }
function is_multisite() { return false; }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 1; }
function get_user_meta() { return ''; }
function current_time() { return gmdate( 'Y-m-d H:i:s' ); }
function size_format( $n ) { return $n . ' B'; }

/* ---- content lookups for the form ---- */
function get_posts( $a ) { return $a['post_type'] === 'page' ? array( 10, 11 ) : array( 20, 21 ); }
function get_the_title( $id ) { return 'Title ' . $id; }
function get_terms( $a ) { return array( (object) array( 'term_id' => 3, 'name' => 'News' ) ); }
function get_post_types( $a = array(), $o = 'names' ) {
	$types = array( 'post' => 'Post', 'page' => 'Page', 'attachment' => 'Media' );
	if ( 'objects' === $o ) { $out = array(); foreach ( $types as $k => $l ) { $out[ $k ] = (object) array( 'labels' => (object) array( 'singular_name' => $l ) ); } return $out; }
	return array_combine( array_keys( $types ), array_keys( $types ) );
}

/* ---- conditional tags driven by $GLOBALS['Q'] ---- */
function msst_q( $k ) { return ! empty( $GLOBALS['Q'][ $k ] ); }
function msst_in( $key, $needle ) { $list = (array) ( $GLOBALS['Q'][ $key ] ?? array() ); if ( is_array( $needle ) ) { return (bool) array_intersect( $needle, $list ); } return in_array( $needle, $list, true ); }
function is_singular( $t = '' ) { if ( ! msst_q( 'singular' ) ) { return false; } return '' === $t || msst_in( 'type', $t ); }
function is_page( $ids = '' ) { return msst_q( 'page' ) && ( '' === $ids || msst_in( 'id', $ids ) ); }
function is_single( $ids = '' ) { return msst_q( 'single' ) && ( '' === $ids || msst_in( 'id', $ids ) ); }
function is_category( $ids = '' ) { return msst_q( 'category' ) && ( '' === $ids || msst_in( 'term', $ids ) ); }
function is_tag( $ids = '' ) { return msst_q( 'tag' ) && ( '' === $ids || msst_in( 'term', $ids ) ); }
function has_category( $ids, $post = null ) { return msst_in( 'has_cat', $ids ); }
function has_tag( $ids, $post = null ) { return msst_in( 'has_tag', $ids ); }
function is_post_type_archive( $t = '' ) { return msst_q( 'pt_archive' ) && msst_in( 'type', $t ); }
function is_front_page() { return msst_q( 'front' ); }
function is_home() { return msst_q( 'home' ); }
function is_search() { return msst_q( 'search' ); }
function is_archive() { return msst_q( 'archive' ); }
function get_queried_object_id() { return (int) ( $GLOBALS['Q']['qid'] ?? 0 ); }
function wp_is_mobile() { return msst_q( 'mobile' ); }

/* ---- SQLite-backed $wpdb ---- */
class FakeWpdb {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $pdo;
	function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec( 'CREATE TABLE wp_msst_snippets (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL DEFAULT "", type TEXT, code TEXT, display_on TEXT, location TEXT, device TEXT, targets TEXT, status INTEGER DEFAULT 0, error TEXT, sig TEXT DEFAULT "", created TEXT, updated TEXT)' );
	}
	function get_charset_collate() { return ''; }
	function esc_like( $t ) { return addcslashes( $t, '_%\\' ); }
	function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		$i = 0;
		return preg_replace_callback( '/%(i|s|d)/', function ( $m ) use ( &$i, $args ) {
			$v = $args[ $i++ ];
			if ( 'i' === $m[1] ) { return '"' . str_replace( '"', '""', (string) $v ) . '"'; }
			if ( 'd' === $m[1] ) { return (string) (int) $v; }
			return $this->pdo->quote( (string) $v );
		}, $sql );
	}
	function get_row( $sql, $o = null ) { $r = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC ); return $r ? $r : null; }
	function get_results( $sql, $o = null ) { return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC ); }
	function get_var( $sql ) { $r = $this->pdo->query( $sql )->fetchColumn(); return false === $r ? null : $r; }
	function insert( $table, $data ) {
		$cols = array_keys( $data );
		$st   = $this->pdo->prepare( 'INSERT INTO ' . $table . ' (' . implode( ',', $cols ) . ') VALUES (' . implode( ',', array_fill( 0, count( $cols ), '?' ) ) . ')' );
		$st->execute( array_values( $data ) );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}
	function update( $table, $data, $where ) {
		$set = implode( ',', array_map( function ( $c ) { return "$c=?"; }, array_keys( $data ) ) );
		$w   = implode( ' AND ', array_map( function ( $c ) { return "$c=?"; }, array_keys( $where ) ) );
		$this->pdo->prepare( "UPDATE $table SET $set WHERE $w" )->execute( array_merge( array_values( $data ), array_values( $where ) ) );
		return 1;
	}
	function delete( $table, $where ) {
		$w = implode( ' AND ', array_map( function ( $c ) { return "$c=?"; }, array_keys( $where ) ) );
		$this->pdo->prepare( "DELETE FROM $table WHERE $w" )->execute( array_values( $where ) );
		return 1;
	}
	function query( $sql ) { return $this->pdo->exec( $sql ); }
}
$GLOBALS['wpdb'] = new FakeWpdb();

foreach ( glob( dirname( __DIR__ ) . '/includes/class-msst-*.php' ) as $f ) {
	require_once $f;
}

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function ok( $cond, $name ) {
	if ( $cond ) { ++$GLOBALS['pass']; echo "  ok   $name\n"; } else { ++$GLOBALS['fail']; echo "  FAIL $name\n"; }
}
function finish() { echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n"; exit( $GLOBALS['fail'] ? 1 : 0 ); }
