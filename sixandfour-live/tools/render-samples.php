<?php
/**
 * Developer tool: draws one sample card of every type into a folder.
 * Usage: php tools/render-samples.php /path/to/output
 * It needs only PHP with GD. WordPress is not loaded.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'SF_DIR', dirname( __DIR__ ) . '/' );
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $d ) {
		return is_dir( $d ) || mkdir( $d, 0755, true );
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public function __construct( $c = '', $m = '' ) {
			$this->code    = $c;
			$this->message = $m;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $x ) {
		return $x instanceof WP_Error;
	}
}
require SF_DIR . 'includes/class-sf-cards.php';
require SF_DIR . 'includes/class-sf-samples.php';

$out = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : sys_get_temp_dir() . '/sf-samples';
$r   = new SF_Cards();
foreach ( SF_Samples::all() as $type => $d ) {
	$res = $r->render( $type, $d, $out . '/' . $type . '.png' );
	echo $type, ' => ', is_wp_error( $res ) ? 'ERROR ' . $res->get_error_message() : $res, "\n";
}
