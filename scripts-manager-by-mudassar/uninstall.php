<?php
/**
 * Uninstall: remove the capability always; remove data only if the user opted in.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$msst_role = get_role( 'administrator' );
if ( $msst_role ) {
	$msst_role->remove_cap( 'msst_manage_snippets' );
}

$msst_settings = get_option( 'msst_settings', array() );
if ( empty( $msst_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'msst_snippets' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing our own table on request.

foreach ( array( 'msst_settings', 'msst_safe_secret', 'msst_db_version' ) as $msst_option ) {
	delete_option( $msst_option );
}
delete_metadata( 'user', 0, 'msst_test_mode', '', true );
