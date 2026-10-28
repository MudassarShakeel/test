<?php
// Installs WordPress into the test site. Run by setup.sh.
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST'] = 'localhost:8099';
require getenv( 'SF_WP_WORK' ) . '/site/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$r = wp_install( 'Six and Four Test', 'admin', 'admin@example.test', true, '', 'Admin#Pass12345' );
echo "WordPress installed. User id ", $r['user_id'], "\n";
