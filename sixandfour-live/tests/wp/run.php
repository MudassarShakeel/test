<?php
// Loads the test WordPress and runs a script inside it. Usage: SF_WP_WORK=/work php run.php script.php [args]
define( 'SF_TEST_WP', true );
$_SERVER['HTTP_HOST']   = 'localhost:8099';
$_SERVER['REQUEST_URI'] = '/';
require getenv( 'SF_WP_WORK' ) . '/site/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require $argv[1];
