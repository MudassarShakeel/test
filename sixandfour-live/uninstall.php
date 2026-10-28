<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes the plugin's settings, tables and generated pictures.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sf_posts' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sf_results' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

foreach ( array( 'sf_options', 'sf_log', 'sf_state', 'sf_api_stats', 'sf_fb_status', 'sf_known_teams', 'sf_records', 'sf_next_check', 'sf_last_tick', 'sf_last_digest', 'sf_last_matches', 'sf_pinned_hash', 'sf_db_version' ) as $opt ) {
	delete_option( $opt );
}
delete_transient( 'sf_current_matches' );

$up  = wp_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'sixandfour-live';
if ( is_dir( $dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}
