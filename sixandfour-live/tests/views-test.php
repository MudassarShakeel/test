<?php
/**
 * Renders every admin screen with WordPress stand-ins and fails on any PHP warning.
 * Run: php tests/views-test.php
 */
require __DIR__ . '/wp-stubs.php';

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_nonce_field( $a = '' ) { echo '<input type="hidden" name="_wpnonce" value="x">'; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=x'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function site_url( $p = '' ) { return 'https://c.example.test/' . $p; }
function human_time_diff( $a, $b = 0 ) { return '5 mins'; }
function number_format_i18n( $n ) { return number_format( $n ); }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? " checked='checked'" : ''; if ( $echo ) { echo $r; } return $r; }
function wp_trim_words( $t, $n = 55 ) { return implode( ' ', array_slice( explode( ' ', $t ), 0, $n ) ); }
function wp_get_attachment_image_url( $id, $s = '' ) { return ''; }
function get_current_user_id() { return 1; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function wp_unslash( $v ) { return $v; }
function current_user_can( $c ) { return true; }
function wp_enqueue_media() {}
function wp_timestamp() { return time(); }
require SF_DIR . 'includes/class-sf-admin.php';

set_error_handler( function ( $no, $str, $file, $line ) {
	throw new ErrorException( $str, 0, $no, $file, $line );
} );

SF_Options::update( array( 'api_key' => 'k', 'fb_page_id' => '1', 'fb_token' => 't', 'followed' => array( 's1' => 'Harbour Cup 2026' ) ) );
update_option( 'sf_fb_status', array( 'ok' => true, 'name' => 'Six & Four Live', 'fans' => 9800, 'message' => 'Connected' ) );
SF_DB::add_post( array( 'type' => 'toss', 'dedupe' => 'a', 'status' => 'pending', 'caption' => "TOSS | x\n#tag", 'image' => '' ) );
SF_DB::add_post( array( 'type' => 'result', 'dedupe' => 'b', 'status' => 'failed', 'caption' => 'RESULT | y', 'error' => 'Token expired' ) );
SF_DB::add_post( array( 'type' => 'manual', 'dedupe' => 'c', 'status' => 'approved', 'caption' => 'Later', 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) ) );
set_transient( 'sf_series_results_1', array( 'harbour', array( array( 'id' => 's1', 'name' => 'Harbour Cup 2026', 'startDate' => '2026-10-24', 'endDate' => '2026-11-14' ), array( 'id' => 's2', 'name' => 'Harbour Cup Women', 'startDate' => 'a' ) ) ), 600 );
update_option( 'sf_last_matches', array( time() - 120, array( array( 'id' => 'm1', 'name' => 'England vs Australia', 'status' => 'Live', 'teams' => array( 'England', 'Australia' ), 'series_id' => 's1', 'matchStarted' => true, 'matchEnded' => false, 'dateTimeGMT' => gmdate( 'Y-m-d\TH:i:s' ), 'score' => array() ) ) ) );
SF_Log::add( 'A test line' );

$fail = 0;
foreach ( array_keys( SF_Admin::tabs() ) as $tab ) {
	$_GET['tab'] = $tab;
	ob_start();
	try {
		SF_Admin::render();
		$out = ob_get_clean();
		$ok  = strlen( $out ) > 500 && false !== strpos( $out, 'Six &amp; Four Live' );
		echo ( $ok ? '  PASS  ' : '  FAIL  ' ) . $tab . ' (' . strlen( $out ) . " bytes)\n";
		$fail += $ok ? 0 : 1;
	} catch ( Throwable $e ) {
		ob_end_clean();
		echo '  FAIL  ' . $tab . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
		$fail++;
	}
}
echo $fail ? "$fail FAILED\n" : "All screens render\n";
exit( $fail ? 1 : 0 );
