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
