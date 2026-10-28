<?php
/**
 * Test-only must-use plugin. Sends the plugin's cricket and Facebook calls to local mock servers.
 */
add_filter( 'sf_graph_base', function () {
	return 'http://127.0.0.1:8100/graph/';
} );
add_filter( 'sf_api_base', function () {
	return 'http://127.0.0.1:8100/cricket/';
} );

/* Keep sent email in a file so tests can read the sign-in link. */
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$dir = WP_CONTENT_DIR . '/mail-capture.json';
	$all = is_readable( $dir ) ? json_decode( file_get_contents( $dir ), true ) : array();
	$all[] = array( 'to' => $atts['to'], 'subject' => $atts['subject'], 'message' => $atts['message'] );
	file_put_contents( $dir, wp_json_encode( $all ) );
	return true;
}, 10, 2 );
