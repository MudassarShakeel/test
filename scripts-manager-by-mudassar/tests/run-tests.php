<?php
/** Logic + database tests. Run: php tests/run-tests.php */
require __DIR__ . '/bootstrap.php';

function snip( $o = array() ) {
	return array_merge( array( 'name' => 'T', 'type' => 'html', 'code' => '<b>x</b>', 'display_on' => 'site_wide', 'location' => 'footer', 'device' => 'all', 'targets' => array(), 'status' => true ), $o );
}

echo "Security signatures\n";
$sig = MSST_Security::sign( 'a|b' );
ok( MSST_Security::verify( 'a|b', $sig ), 'valid signature verifies' );
ok( ! MSST_Security::verify( 'a|c', $sig ), 'tampered payload rejected' );
ok( ! MSST_Security::verify( 'a|b', '' ), 'empty signature rejected' );

echo "Saving snippets (real SQL)\n";
$a = MSST_Snippets::save( snip( array( 'name' => 'First', 'type' => 'css', 'code' => 'body{color:red}', 'location' => 'header' ) ) );
$b = MSST_Snippets::save( snip( array( 'name' => 'Second' ) ) );
$c = MSST_Snippets::save( snip( array( 'name' => 'Third', 'type' => 'js', 'status' => false, 'device' => 'mobile' ) ) );
ok( 1 === $a && 2 === $b && 3 === $c, 'IDs are sequential 1, 2, 3' );
$row = MSST_Snippets::get( 1 );
ok( 'First' === $row['name'] && 'css' === $row['type'] && true === $row['status'] && 'header' === $row['location'], 'round trip of fields' );
ok( MSST_Snippets::is_intact( $row ), 'new snippet is signed and intact' );
$GLOBALS['wpdb']->pdo->exec( "UPDATE wp_msst_snippets SET code='<i>evil</i>' WHERE id=2" );
ok( ! MSST_Snippets::is_intact( MSST_Snippets::get( 2 ) ), 'code changed in the database is detected' );
ok( MSST_Snippets::set_status( 2, true ) && MSST_Snippets::is_intact( MSST_Snippets::get( 2 ) ), 'turning ON re-approves current code' );
$bad = MSST_Snippets::save( snip( array( 'type' => 'php', 'code' => "add_filter('a',;" ) ) );
ok( is_wp_error( $bad ), 'PHP syntax error is rejected' );
ok( is_wp_error( MSST_Snippets::save( snip( array( 'type' => 'php', 'code' => 'echo 1;', 'display_on' => 'shortcode' ) ) ) ), 'PHP cannot be shortcode-only' );
ok( is_wp_error( MSST_Snippets::save( snip( array( 'code' => '   ' ) ) ) ), 'empty code is rejected' );
$fix = MSST_Snippets::get( MSST_Snippets::save( snip( array( 'display_on' => 'evil', 'location' => 'nowhere', 'device' => 'toaster', 'type' => 'weird' ) ) ) );
ok( 'site_wide' === $fix['display_on'] && 'header' === $fix['location'] && 'all' === $fix['device'] && 'html' === $fix['type'], 'invalid display/location/device/type fall back to safe values' );
$GLOBALS['USER']['unfiltered_html'] = false;
ok( is_wp_error( MSST_Snippets::save( snip() ) ), 'user without unfiltered_html cannot save raw code' );
$GLOBALS['USER']['unfiltered_html'] = true;
$php = MSST_Snippets::save( snip( array( 'type' => 'php', 'code' => "<?php\nadd_filter('a','b'); ?>", 'location' => 'bogus' ) ) );
$pr  = MSST_Snippets::get( $php );
ok( "add_filter('a','b');" === $pr['code'] && 'everywhere' === $pr['location'], 'PHP tags stripped, PHP location defaulted' );
ok( 1 === count( MSST_Snippets::lint_php( 'exec("ls");' )['warnings'] ), 'risky function gives a warning' );

echo "Target lists\n";
$t = MSST_Snippets::clean_targets( array( 'pages' => array( '5', '5', 'abc', '7;DROP TABLE x', -9, array( 1 ) ), 'post_types' => array( 'Post', 'page"><script>' ), 'tags' => 'nope' ) );
ok( array( 5, 7, 9 ) === $t['pages'], 'page IDs become unique positive integers' );
ok( array( 'post', 'pagescript' ) === $t['post_types'], 'post type slugs are sanitised' );
ok( array() === $t['tags'] && array() === $t['ex_posts'], 'bad or missing lists become empty' );
$withT = MSST_Snippets::get( MSST_Snippets::save( snip( array( 'display_on' => 'pages', 'targets' => array( 'pages' => array( '10', '11' ) ) ) ) ) );
ok( array( 10, 11 ) === $withT['targets']['pages'], 'targets survive the database round trip' );

echo "Listing, search, sorting, counts\n";
$all = MSST_Snippets::query( array( 'per_page' => 50 ) );
ok( $all['total'] >= 6, 'list returns rows' );
ok( 1 === MSST_Snippets::query( array( 'search' => 'Third' ) )['total'], 'search by name' );
ok( 0 === MSST_Snippets::query( array( 'search' => "' OR 1=1 --" ) )['total'], 'SQL injection in search returns nothing' );
ok( MSST_Snippets::query( array( 'per_page' => 50 ) )['total'] === $all['total'], 'table still intact after injection attempt' );
ok( 1 === MSST_Snippets::query( array( 'type' => 'js' ) )['total'], 'filter by type' );
$off = MSST_Snippets::query( array( 'status' => 'inactive', 'per_page' => 50 ) );
ok( $off['total'] >= 1 && ! $off['items'][0]['status'], 'filter inactive' );
$desc = MSST_Snippets::query( array( 'orderby' => 'id', 'order' => 'desc', 'per_page' => 50 ) );
ok( $desc['items'][0]['id'] > $desc['items'][1]['id'], 'sort by ID descending' );
$evil = MSST_Snippets::query( array( 'orderby' => 'id; DROP TABLE wp_msst_snippets', 'per_page' => 50 ) );
ok( $evil['total'] === $all['total'], 'ORDER BY is whitelisted' );
$p1 = MSST_Snippets::query( array( 'per_page' => 2, 'paged' => 1 ) );
ok( 2 === count( $p1['items'] ) && $p1['pages'] >= 3, 'pagination' );
$cn = MSST_Snippets::counts();
ok( $cn['all'] === $cn['active'] + $cn['inactive'] && $cn['inactive'] >= 1, 'counts add up' );

echo "Status, errors, delete, duplicate\n";
MSST_Snippets::disable_with_error( 1, "boom\nwith <b>html</b>" );
$r1 = MSST_Snippets::get( 1 );
ok( false === $r1['status'] && 'boom with html' === $r1['error'], 'auto-disable stores a clean error' );
ok( MSST_Snippets::set_status( 1, true ) && '' === MSST_Snippets::get( 1 )['error'], 'turning ON clears the error' );
$dup = MSST_Snippets::duplicate( 1 );
ok( false === MSST_Snippets::get( $dup )['status'] && false !== strpos( MSST_Snippets::get( $dup )['name'], '(copy)' ), 'duplicate is OFF with (copy)' );
ok( MSST_Snippets::delete( $dup ) && null === MSST_Snippets::get( $dup ), 'delete removes the row' );
$GLOBALS['USER']['unfiltered_html'] = false;
ok( ! MSST_Snippets::delete( $php ), 'cannot delete a PHP snippet without PHP rights' );
$GLOBALS['USER']['unfiltered_html'] = true;

echo "Where to show (Site Display)\n";
function shows( $display, $targets, $q ) {
	$GLOBALS['Q'] = $q;
	$s = MSST_Snippets::clean_targets( $targets );
	return MSST_Display::page_ok( array( 'display_on' => $display, 'targets' => $s ) );
}
ok( shows( 'site_wide', array(), array( 'front' => 1 ) ), 'site wide shows on the home page' );
ok( ! shows( 'site_wide', array( 'ex_pages' => array( 10 ) ), array( 'singular' => 1, 'page' => 1, 'qid' => 10 ) ), 'site wide hides on an excluded page' );
ok( shows( 'site_wide', array( 'ex_pages' => array( 10 ) ), array( 'singular' => 1, 'page' => 1, 'qid' => 11 ) ), 'site wide still shows on other pages' );
ok( ! shows( 'site_wide', array( 'ex_posts' => array( 20 ) ), array( 'singular' => 1, 'single' => 1, 'qid' => 20 ) ), 'site wide hides on an excluded post' );
ok( shows( 'pages', array( 'pages' => array( 10 ) ), array( 'page' => 1, 'id' => array( 10 ) ) ) && ! shows( 'pages', array( 'pages' => array( 10 ) ), array( 'page' => 1, 'id' => array( 11 ) ) ), 'specific pages' );
ok( ! shows( 'pages', array(), array( 'page' => 1, 'id' => array( 10 ) ) ), 'specific pages with an empty list shows nowhere' );
ok( shows( 'posts', array( 'posts' => array( 20 ) ), array( 'single' => 1, 'id' => array( 20 ) ) ), 'specific posts' );
ok( shows( 'categories', array( 'categories' => array( 3 ) ), array( 'category' => 1, 'term' => array( 3 ) ) ), 'category archive' );
ok( shows( 'categories', array( 'categories' => array( 3 ) ), array( 'singular' => 1, 'has_cat' => array( 3 ) ) ), 'post inside the category' );
ok( ! shows( 'categories', array( 'categories' => array( 3 ) ), array( 'singular' => 1, 'has_cat' => array( 4 ) ) ), 'post in another category hidden' );
ok( shows( 'tags', array( 'tags' => array( 4 ) ), array( 'tag' => 1, 'term' => array( 4 ) ) ), 'tag archive' );
ok( shows( 'post_types', array( 'post_types' => array( 'product' ) ), array( 'singular' => 1, 'type' => array( 'product' ) ) ), 'post type single' );
ok( shows( 'post_types', array( 'post_types' => array( 'product' ) ), array( 'pt_archive' => 1, 'type' => array( 'product' ) ) ), 'post type archive' );
ok( shows( 'home', array(), array( 'front' => 1 ) ) && ! shows( 'home', array(), array( 'search' => 1 ) ), 'home page' );
ok( shows( 'search', array(), array( 'search' => 1 ) ) && ! shows( 'search', array(), array( 'front' => 1 ) ), 'search page' );
ok( shows( 'archives', array(), array( 'archive' => 1 ) ), 'all archive pages' );
ok( shows( 'latest', array(), array( 'home' => 1 ) ), 'latest posts (blog index)' );
ok( ! shows( 'shortcode', array(), array( 'front' => 1, 'singular' => 1 ) ), 'shortcode-only never shows automatically' );
$GLOBALS['Q'] = array( 'mobile' => 1 );
ok( MSST_Display::device_ok( 'all' ) && MSST_Display::device_ok( 'mobile' ) && ! MSST_Display::device_ok( 'desktop' ), 'device: mobile visitor' );
$GLOBALS['Q'] = array();
ok( MSST_Display::device_ok( 'desktop' ) && ! MSST_Display::device_ok( 'mobile' ), 'device: desktop visitor' );
ok( MSST_Display::needs_query( array( 'display_on' => 'pages', 'targets' => MSST_Snippets::empty_targets() ) ) && ! MSST_Display::needs_query( array( 'display_on' => 'site_wide', 'targets' => MSST_Snippets::empty_targets() ) ), 'only page-specific snippets wait for the main query' );

echo "Output\n";
ok( false !== strpos( MSST_Runner::render( array( 'type' => 'css', 'code' => 'a{b:c}' ) ), '<style>' ) && false !== strpos( MSST_Runner::render( array( 'type' => 'js', 'code' => 'x=1' ) ), '<script>' ), 'CSS and JS get wrapped' );
ok( '<script>y()</script>' === MSST_Runner::render( array( 'type' => 'js', 'code' => '<script>y()</script>' ) ), 'JS that already has script tags is not wrapped twice' );
ok( '<b>x</b>' === MSST_Runner::render( array( 'type' => 'html', 'code' => '<b>x</b>' ) ), 'HTML is printed as is' );

echo "Export / import\n";
$json = MSST_Importer::export( array( 1, 3 ) );
$doc  = json_decode( $json, true );
ok( MSST_SLUG === $doc['plugin'] && 2 === count( $doc['snippets'] ), 'export contains only the selected snippets' );
$before = MSST_Snippets::counts()['all'];
$res    = MSST_Importer::import( $json );
ok( 2 === $res['imported'] && MSST_Snippets::counts()['all'] === $before + 2, 'import adds the snippets' );
$last = MSST_Snippets::query( array( 'orderby' => 'id', 'order' => 'desc', 'per_page' => 2 ) );
ok( ! $last['items'][0]['status'] && ! $last['items'][1]['status'], 'imported snippets are OFF' );
ok( is_wp_error( MSST_Importer::import( str_repeat( 'x', 2097153 ) ) ), 'oversize file rejected' );
ok( is_wp_error( MSST_Importer::import( '{"plugin":"other","snippets":[]}' ) ), 'wrong plugin marker rejected' );
ok( is_wp_error( MSST_Importer::import( 'not json' ) ), 'invalid JSON rejected' );
$mixed = MSST_Importer::import( json_encode( array( 'plugin' => MSST_SLUG, 'snippets' => array( array( 'name' => 'ok', 'type' => 'html', 'code' => '<i>1</i>' ), array( 'name' => 'bad', 'type' => 'php', 'code' => 'x(' ), 'junk', array( 'name' => 5 ) ) ) ) );
ok( 1 === $mixed['imported'] && 3 === $mixed['skipped'], 'bad rows are skipped, good rows kept' );

echo "Branding links\n";
$u = MSST_Brand::url( 'contact', 'Header-Contact-Us' );
ok( 0 === strpos( $u, 'https://mudassar.work/contact/?' ) && false !== strpos( $u, 'utm_source=scripts-manager-by-mudassar' ) && false !== strpos( $u, 'utm_medium=wordpress-plugin' ) && false !== strpos( $u, 'utm_campaign=plugin-contact' ) && false !== strpos( $u, 'utm_content=header-contact-us' ), 'Contact link has UTM tags' );
ok( 0 === strpos( MSST_Brand::url( 'website', 'x' ), 'https://mudassar.work/?' ), 'website link base' );
ok( false !== strpos( MSST_Brand::link( 'website', 'f', 'm' ), 'rel="noopener noreferrer"' ), 'external links use noopener' );

finish();
