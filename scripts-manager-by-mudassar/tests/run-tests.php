<?php
/**
 * Standalone logic tests (no WordPress needed): php tests/run-tests.php
 * WordPress functions used by the tested units are stubbed below.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MSST_DIR', dirname( __DIR__ ) . '/' );
define( 'MSST_SLUG', 'scripts-manager-by-mudassar' );
define( 'MSST_VERSION', '1.0.0' );
define( 'PHP_URL_PATH_X', 5 );

function __( $s ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function esc_js( $s ) { return addslashes( (string) $s ); }
function wp_salt() { return 'test-salt-123'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function wp_roles() { return (object) array( 'roles' => array( 'administrator' => array( 'name' => 'Administrator' ), 'editor' => array( 'name' => 'Editor' ) ) ); }
function translate_user_role( $s ) { return $s; }
function get_post_types() { return array( 'post' => (object) array( 'labels' => (object) array( 'singular_name' => 'Post' ) ) ); }
function wpautop( $s ) { return '<p>' . $s . '</p>'; }
function wp_kses_post( $s ) { return $s; }
function do_shortcode( $s ) { return $s; }
function get_option() { return false; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function is_wp_error( $x ) { return false; }
class WP_Error {}

foreach ( glob( MSST_DIR . 'includes/class-msst-*.php' ) as $f ) {
	require_once $f;
}

$pass = 0;
$fail = 0;
function ok( $cond, $name ) {
	global $pass, $fail;
	if ( $cond ) { ++$pass; echo "  ok   $name\n"; } else { ++$fail; echo "  FAIL $name\n"; }
}

echo "Conditions::clean\n";
$c = MSST_Conditions::clean( array( array(
	array( 'type' => 'page_type', 'op' => 'is', 'value' => 'single' ),
	array( 'type' => 'page_type', 'op' => 'is', 'value' => 'evil' ),          // bad value
	array( 'type' => 'nope', 'op' => 'is', 'value' => 'x' ),                  // bad type
	array( 'type' => 'url', 'op' => 'contains', 'value' => '<b>/shop</b>' ),  // sanitised
	array( 'type' => 'url', 'op' => 'DROP', 'value' => 'x' ),                 // bad op
) , 'not-an-array' ) );
ok( 1 === count( $c ) && 2 === count( $c[0] ), 'drops invalid rules/groups' );
ok( '/shop' === $c[0][1]['value'], 'sanitises free-text values' );
ok( array() === MSST_Conditions::clean( 'x' ), 'non-array input -> empty' );
ok( MSST_Conditions::passes( array() ), 'no rules always passes' );
ok( MSST_Conditions::needs_query( $c ), 'page_type needs query' );

echo "PHP lint / normalize\n";
ok( MSST_Snippets::lint_php( "add_filter('a','b');" )['ok'], 'valid php passes' );
$bad = MSST_Snippets::lint_php( "add_filter('a',;" );
ok( ! $bad['ok'] && '' !== $bad['error'], 'syntax error is caught without executing' );
ok( array( 'exec() ' ) !== MSST_Snippets::lint_php( 'exec("ls");' )['warnings'] && 1 === count( MSST_Snippets::lint_php( 'exec("ls");' )['warnings'] ), 'warns on risky function' );
ok( 'echo 1;' === MSST_Snippets::normalize_php( "<?php\necho 1; ?>" ), 'strips php tags' );

echo "Security signatures\n";
$sig = MSST_Security::sign( 'a|b' );
ok( MSST_Security::verify( 'a|b', $sig ), 'valid signature verifies' );
ok( ! MSST_Security::verify( 'a|c', $sig ), 'tampered payload rejected' );
ok( ! MSST_Security::verify( 'a|b', '' ), 'empty signature rejected' );
$p = MSST_Snippets::payload( 5, 'php', 'echo 1;' );
ok( MSST_Security::verify( $p, MSST_Security::sign( $p ) ), 'snippet payload round-trip' );
ok( ! MSST_Security::verify( MSST_Snippets::payload( 6, 'php', 'echo 1;' ), MSST_Security::sign( $p ) ), 'signature bound to snippet id' );

echo "Integration IDs\n";
ok( 'G-AB12CD34EF' === MSST_Settings::clean_integration_id( 'ga4', 'g-ab12cd34ef' ), 'GA4 accepted' );
ok( '' === MSST_Settings::clean_integration_id( 'ga4', 'G-1");alert(1);//' ), 'GA4 injection rejected' );
ok( '' === MSST_Settings::clean_integration_id( 'gtm', '"><script>' ), 'GTM injection rejected' );
ok( '1234567890' === MSST_Settings::clean_integration_id( 'meta', '1234567890' ), 'Meta pixel accepted' );
ok( '' === MSST_Settings::clean_integration_id( 'meta', '12 34' ), 'Meta pixel invalid rejected' );
$out = MSST_Integrations::render( 'header', array( 'ga4' => 'G-AB12CD34EF', 'gtm' => '', 'meta' => '', 'tiktok' => '' ) );
ok( false !== strpos( $out, 'G-AB12CD34EF' ), 'GA4 markup generated' );
ok( '' === MSST_Integrations::render( 'header', array( 'ga4' => '");evil', 'gtm' => '', 'meta' => '', 'tiktok' => '' ) ), 'invalid ID outputs nothing' );

echo "Diff\n";
$d = MSST_Diff::lines( "a\nb\nc", "a\nx\nc" );
$ops = array_column( $d, 'op' );
ok( array( 'same', 'del', 'add', 'same' ) === $ops, 'line diff ops' );

echo "Brand UTM links\n";
$u = MSST_Brand::url( 'contact', 'Header-Contact-Us' );
ok( 0 === strpos( $u, 'https://mudassar.work/contact/?' ), 'contact URL base' );
ok( false !== strpos( $u, 'utm_source=scripts-manager-by-mudassar' ) && false !== strpos( $u, 'utm_medium=wordpress-plugin' ) && false !== strpos( $u, 'utm_campaign=plugin-contact' ) && false !== strpos( $u, 'utm_content=header-contact-us' ), 'UTM tags present' );
ok( false === strpos( $u, 'localhost' ), 'no site data in URL' );
ok( 0 === strpos( MSST_Brand::url( 'website', 'x' ), 'https://mudassar.work/?' ), 'website URL base' );
$link = MSST_Brand::link( 'website', 'footer', 'mudassar.work' );
ok( false !== strpos( $link, 'rel="noopener noreferrer"' ) && false !== strpos( $link, 'target="_blank"' ), 'external link has noopener' );

echo "Error log lines\n";
$line = MSST_Logger::format_line( 0, 'error', 7, "Evil\n[2099-01-01 00:00:00 UTC] ERROR snippet#1 \"fake\"", "msg\r\nforged: line\x00", 'path=/x user=1' );
ok( 1 === substr_count( $line, "\n" ) && "\n" === substr( $line, -1 ), 'newlines in titles/messages cannot forge extra log lines' );
ok( false !== strpos( $line, '[1970-01-01 00:00:00 UTC] ERROR snippet#7' ), 'line has UTC time, level and snippet id' );
ok( false === strpos( $line, "\x00" ), 'control characters removed' );
ok( strlen( MSST_Logger::format_line( 0, 'error', 1, str_repeat( 'a', 500 ), str_repeat( 'b', 5000 ) ) ) < 900, 'long text is truncated' );
ok( false !== strpos( MSST_Logger::format_line( 0, 'bogus', 1, 't', 'm' ), ' ERROR ' ), 'unknown level falls back to ERROR' );

echo "Importer validation\n";
ok( false === is_array( MSST_Importer::import( str_repeat( 'x', 2097153 ) ) ), 'oversize file rejected' );
ok( false === is_array( MSST_Importer::import( '{"plugin":"other","snippets":[]}' ) ), 'wrong plugin marker rejected' );
ok( false === is_array( MSST_Importer::import( 'not json' ) ), 'invalid JSON rejected' );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
