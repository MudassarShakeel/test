<?php
/**
 * Minimal stand-ins for the WordPress functions this plugin uses, so the engine can be tested
 * from the command line with PHP, GD and SQLite. This is a test aid only. It is not loaded by the plugin.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SF_DIR', dirname( __DIR__ ) . '/' );
define( 'SF_URL', 'https://example.test/wp-content/plugins/sixandfour-live/' );
define( 'SF_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['sf_opts']       = array();
$GLOBALS['sf_transients'] = array();
$GLOBALS['sf_http']       = null; // callable( string $method, string $url, array $args ): array
$GLOBALS['sf_http_log']   = array();

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_code() {
		return $this->code;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['sf_opts'] ) ? $GLOBALS['sf_opts'][ $k ] : $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['sf_opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['sf_opts'][ $k ] );
	return true;
}
function get_transient( $k ) {
	$t = isset( $GLOBALS['sf_transients'][ $k ] ) ? $GLOBALS['sf_transients'][ $k ] : null;
	return ( $t && $t[1] > time() ) ? $t[0] : false;
}
function set_transient( $k, $v, $ttl ) {
	$GLOBALS['sf_transients'][ $k ] = array( $v, time() + $ttl );
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['sf_transients'][ $k ] );
	return true;
}
function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}
function is_ssl() {
	return true;
}
function home_url( $p = '' ) {
	return 'https://c.example.test' . $p;
}
function get_user_by( $f, $v ) {
	return false;
}
function apply_filters( $tag, $value ) {
	return $value;
}
function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function wp_generate_password( $n = 12, $special = true ) {
	return substr( bin2hex( random_bytes( $n ) ), 0, $n );
}
function wp_timezone() {
	return new DateTimeZone( 'UTC' );
}
function wp_date( $format, $ts = null, $tz = null ) {
	$d = new DateTime( '@' . ( null === $ts ? time() : $ts ) );
	$d->setTimezone( $tz ? $tz : wp_timezone() );
	return $d->format( $format );
}
function wp_mkdir_p( $d ) {
	return is_dir( $d ) || mkdir( $d, 0755, true );
}
function wp_upload_dir() {
	$base = sys_get_temp_dir() . '/sf-test-uploads';
	return array( 'basedir' => $base, 'baseurl' => 'https://example.test/uploads' );
}
function trailingslashit( $s ) {
	return rtrim( $s, '/' ) . '/';
}
function wp_check_filetype( $f ) {
	return array( 'ext' => 'png', 'type' => 'image/png' );
}
function get_attached_file( $id ) {
	return isset( $GLOBALS['sf_attached'][ $id ] ) ? $GLOBALS['sf_attached'][ $id ] : false;
}
function wp_remote_get( $url, $args = array() ) {
	return sf_http( 'GET', $url, $args );
}
function wp_remote_post( $url, $args = array() ) {
	return sf_http( 'POST', $url, $args );
}
function sf_http( $method, $url, $args ) {
	$GLOBALS['sf_http_log'][] = array( $method, $url );
	return call_user_func( $GLOBALS['sf_http'], $method, $url, $args );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}
function sf_json( $data, $code = 200 ) {
	return array( 'code' => $code, 'body' => json_encode( $data ) );
}

/** A tiny $wpdb that runs the plugin's SQL on SQLite. */
class SF_Test_WPDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	private $pdo;
	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec( 'CREATE TABLE wp_sf_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, dedupe TEXT UNIQUE, match_id TEXT DEFAULT "", series_id TEXT DEFAULT "", status TEXT DEFAULT "pending", caption TEXT, image TEXT DEFAULT "", fb_post_id TEXT DEFAULT "", error TEXT NULL, attempts INTEGER DEFAULT 0, scheduled_at TEXT NULL, created_at TEXT, posted_at TEXT NULL)' );
		$this->pdo->exec( 'CREATE TABLE wp_sf_results (id INTEGER PRIMARY KEY AUTOINCREMENT, match_id TEXT UNIQUE, series_id TEXT, series_name TEXT, played_on TEXT, team_a TEXT, team_b TEXT, winner TEXT, result_type TEXT)' );
	}
	public function get_charset_collate() {
		return '';
	}
	public function prepare( $sql, ...$args ) {
		$i = 0;
		return preg_replace_callback( '/%[sd]/', function ( $m ) use ( &$i, $args ) {
			$v = $args[ $i++ ];
			return '%d' === $m[0] ? (int) $v : $this->pdo->quote( (string) $v );
		}, $sql );
	}
	public function get_var( $sql ) {
		$r = $this->pdo->query( $sql )->fetchColumn();
		return false === $r ? null : $r;
	}
	public function get_row( $sql, $mode = null ) {
		$r = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC );
		return false === $r ? null : $r;
	}
	public function get_results( $sql, $mode = null ) {
		return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
	}
	public function insert( $table, $data ) {
		$cols = array_keys( $data );
		$st   = $this->pdo->prepare( "INSERT INTO $table (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', array_fill( 0, count( $cols ), '?' ) ) . ')' );
		$st->execute( array_values( $data ) );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}
	public function update( $table, $data, $where ) {
		$set = implode( ',', array_map( function ( $c ) {
			return "$c = ?";
		}, array_keys( $data ) ) );
		$w   = implode( ' AND ', array_map( function ( $c ) {
			return "$c = ?";
		}, array_keys( $where ) ) );
		$st = $this->pdo->prepare( "UPDATE $table SET $set WHERE $w" );
		$st->execute( array_merge( array_values( $data ), array_values( $where ) ) );
		return $st->rowCount();
	}
	public function query( $sql ) {
		return $this->pdo->exec( $sql );
	}
}
$GLOBALS['wpdb'] = new SF_Test_WPDB();

require SF_DIR . 'includes/class-sf-secrets.php';
require SF_DIR . 'includes/class-sf-options.php';
require SF_DIR . 'includes/class-sf-db.php';
require SF_DIR . 'includes/class-sf-api.php';
require SF_DIR . 'includes/class-sf-facebook.php';
require SF_DIR . 'includes/class-sf-teams.php';
require SF_DIR . 'includes/class-sf-players.php';
require SF_DIR . 'includes/class-sf-playerphotos.php';
require SF_DIR . 'includes/class-sf-cards.php';
require SF_DIR . 'includes/class-sf-samples.php';
require SF_DIR . 'includes/class-sf-poster.php';
require SF_DIR . 'includes/class-sf-engine.php';
require SF_DIR . 'includes/class-sf-health.php';
require SF_DIR . 'includes/class-sf-security.php';
require SF_DIR . 'includes/class-sf-webauthn.php';
require SF_DIR . 'includes/class-sf-passwordless.php';
require SF_DIR . 'includes/class-sf-console.php';
require SF_DIR . 'includes/class-sf-plugin.php';

if ( ! function_exists( 'get_users' ) ) {
	function get_users( $args = array() ) { return array(); }
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $uid, $key, $single = false ) { return ''; }
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string() {
		return 'UTC';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $p = '' ) {
		return 'https://c.example.test' . $p;
	}
}

if ( ! function_exists( 'wp_timezone_choice' ) ) {
	function wp_timezone_choice( $selected ) {
		return '<option value="Asia/Karachi" selected>Karachi</option>';
	}
}
if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $a, $b = 0 ) {
		return '5 mins';
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) {
		return $v;
	}
}
