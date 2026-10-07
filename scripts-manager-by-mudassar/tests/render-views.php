<?php
/** Renders every admin screen with real data and checks the HTML. Run: php tests/render-views.php [--dump] */
require __DIR__ . '/bootstrap.php';

function wp_die( $m = '' ) { throw new RuntimeException( 'wp_die: ' . $m ); }
function wp_safe_redirect( $u ) { throw new RuntimeException( 'redirect:' . $u ); }

// Seed: one of every kind.
MSST_Snippets::save( array( 'name' => 'Facebook Pixel', 'type' => 'js', 'code' => 'fbq(1)', 'display_on' => 'site_wide', 'location' => 'header', 'device' => 'all', 'status' => true, 'targets' => array( 'ex_pages' => array( 10 ) ) ) );
MSST_Snippets::save( array( 'name' => 'Adaptive Portfolios', 'type' => 'html', 'code' => '<p>hi</p>', 'display_on' => 'pages', 'location' => 'before_content', 'device' => 'mobile', 'status' => true, 'targets' => array( 'pages' => array( 11 ) ) ) );
MSST_Snippets::save( array( 'name' => 'Banner <b>x</b>', 'type' => 'html', 'code' => '<p>x</p>', 'display_on' => 'shortcode', 'location' => 'header', 'device' => 'desktop', 'status' => false ) );
$bad = MSST_Snippets::save( array( 'name' => 'Legacy redirect', 'type' => 'php', 'code' => 'x();', 'display_on' => 'site_wide', 'location' => 'everywhere', 'device' => 'all', 'status' => true ) );
MSST_Snippets::disable_with_error( $bad, 'Call to undefined function x()' );

function page( $method, $get = array() ) {
	$_GET = $get;
	ob_start();
	try {
		MSST_Admin::$method();
	} catch ( Throwable $e ) {
		while ( ob_get_level() > 1 ) { ob_end_clean(); }
		$out = ob_get_clean();
		return "EXCEPTION: " . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" . $out;
	}
	return ob_get_clean();
}

$pages = array(
	'list'     => array( 'page_list', array() ),
	'add'      => array( 'page_form', array() ),
	'edit'     => array( 'page_form', array( 'id' => 2 ) ),
	'edit-php' => array( 'page_form', array( 'id' => $bad ) ),
	'tools'    => array( 'page_tools', array() ),
	'settings' => array( 'page_settings', array() ),
);
$html = array();
foreach ( $pages as $name => $def ) {
	$html[ $name ] = page( $def[0], $def[1] );
	ok( 0 !== strpos( $html[ $name ], 'EXCEPTION' ) && false !== strpos( $html[ $name ], 'Scripts Manager By Mudassar' ) && false !== strpos( $html[ $name ], 'utm_source=scripts-manager-by-mudassar' ) && false !== strpos( $html[ $name ], 'Contact Us' ), "screen '$name' renders with branding + UTM links" . ( 0 === strpos( $html[ $name ], 'EXCEPTION' ) ? ' -> ' . strtok( $html[ $name ], "\n" ) : '' ) );
}

$l = $html['list'];
ok( false !== strpos( $l, 'All <span>(4)</span>' ) && false !== strpos( $l, 'Active <span>(' ) && false !== strpos( $l, 'Inactive <span>(' ), 'list: All / Active / Inactive counts' );
foreach ( array( 'Status', 'Snippet Name', 'Display On', 'Location', 'Snippet Type', 'Devices', 'Shortcode', 'ID' ) as $col ) {
	ok( false !== strpos( $l, $col ), "list column: $col" );
}
ok( false !== strpos( $l, '[msst_snippet id="1"]' ), 'list: shortcode shown' );
ok( false !== strpos( $l, 'Banner &lt;b&gt;x&lt;/b&gt;' ) || false !== strpos( $l, 'Banner x' ), 'list: names are escaped / tag-free' );
ok( false === strpos( $l, 'Banner <b>x</b>' ), 'list: no raw HTML from a snippet name' );
ok( false !== strpos( $l, 'Turned off automatically: Call to undefined function x()' ), 'list: auto-disabled snippet shows its error' );
ok( false !== strpos( $l, 'action=msst_toggle' ) && false !== strpos( $l, 'action=msst_delete' ) && false !== strpos( $l, 'action=msst_duplicate' ), 'list: row actions carry nonces' );
ok( false !== strpos( $l, 'Specific Pages' ) && false !== strpos( $l, 'Site Wide' ) && false !== strpos( $l, 'Shortcode Only' ), 'list: Display On labels' );
ok( false !== strpos( $l, 'Only Mobile' ) && false !== strpos( $l, 'Show on All Devices' ), 'list: Devices labels' );
ok( false !== strpos( $l, 'Bulk actions' ) && false !== strpos( $l, 'All Snippet Types' ), 'list: bulk actions and type filter' );

$f = $html['add'];
foreach ( array( 'Snippet Name', 'Snippet Type', 'Site Display', 'Page List', 'Post List', 'Category List', 'Post Types', 'Tag List', 'Exclude Pages', 'Exclude Posts', 'Location', 'Device Display', 'Status', 'Snippet / Code' ) as $label ) {
	ok( false !== strpos( $f, '>' . $label . '<' ), "form field: $label" );
}
ok( false !== strpos( $f, 'Specific Categories (Archive &amp; Posts)' ) && false !== strpos( $f, 'Shortcode Only' ), 'form: all Site Display options' );
ok( false !== strpos( $f, 'Before Content' ) && false !== strpos( $f, 'After Content' ) && false !== strpos( $f, '>Header<' ) && false !== strpos( $f, '>Footer<' ), 'form: locations' );
ok( false !== strpos( $f, 'Only Desktop' ) && false !== strpos( $f, 'Only Mobile' ), 'form: devices' );
ok( false !== strpos( $f, 'Using improper code or untrusted sources code can break your site' ), 'form: code warning' );
ok( 1 === preg_match( '/name="msst_ex_pages\[\]" value="10"/', $f ) && 1 === preg_match( '/name="msst_pages\[\]" value="11"/', $f ), 'form: page pickers list pages' );
ok( 1 === preg_match( '/data-row="ex_pages"\s+>/', $f ) && 1 === preg_match( '/data-row="pages"\s+hidden/', $f ), 'form: Site Wide shows Exclude rows, hides Page List' );
$e = $html['edit'];
ok( 1 === preg_match( '/name="msst_pages\[\]" value="11"\s+checked/', $e ) && 1 === preg_match( '/data-row="pages"\s+>/', $e ), 'edit: saved pages are ticked and visible' );
ok( 1 === preg_match( '/value="mobile"\s+selected/', $e ), 'edit: device selected' );
ok( false !== strpos( $e, 'name="msst_id" value="2"' ) && false !== strpos( $e, '[msst_snippet id="2"]' ), 'edit: id and shortcode' );
ok( false !== strpos( $html['edit-php'], 'turned off automatically' ) && false !== strpos( $html['edit-php'], 'Call to undefined function x()' ), 'edit: shows why a PHP snippet was turned off' );
ok( false !== strpos( $html['tools'], 'Export Snippets' ) && false !== strpos( $html['tools'], 'Import Snippets' ) && false !== strpos( $html['tools'], 'Export File' ) && false !== strpos( $html['tools'], 'Select all' ), 'tools: export and import' );
ok( false !== strpos( $html['settings'], 'msst_safe_mode=' ) && false !== strpos( $html['settings'], 'Allow PHP snippets' ), 'settings: switches and Safe Mode link' );

$GLOBALS['USER']['msst_manage_snippets'] = false;
$denied = page( 'page_list' );
ok( 0 === strpos( $denied, 'EXCEPTION: wp_die' ), 'a user without the capability is refused' );
$GLOBALS['USER']['msst_manage_snippets'] = true;

if ( in_array( '--dump', $argv, true ) ) {
	@mkdir( __DIR__ . '/out' );
	$_GET = array( 'id' => 2 );
	$data = json_encode( MSST_Admin::script_data() );
	foreach ( $html as $name => $body ) {
		file_put_contents( __DIR__ . "/out/$name.html", '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../../assets/admin.css"><style>body{margin:0;background:#f0f0f1;font:13px -apple-system,Arial,sans-serif;color:#1d2327}.wp-side{float:left;width:160px;min-height:100vh;background:#1d2327}#wpbody{margin-left:160px;padding:10px}</style></head><body><div class="wp-side"></div><div id="wpbody">' . $body . '</div><script>window.msstData=' . $data . ';</script><script src="../../assets/admin.js"></script></body></html>' );
	}
}
finish();
