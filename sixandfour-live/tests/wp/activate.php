<?php
$r = activate_plugin( 'sixandfour-live/sixandfour-live.php' );
echo is_wp_error( $r ) ? 'Activation failed: ' . $r->get_error_message() : 'Plugin activated', "\n";
