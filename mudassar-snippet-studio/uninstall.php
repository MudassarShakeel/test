<?php
/**
 * Uninstall: remove the capability always; remove data only if the user opted in.
 *
 * @package MudassarSnippetStudio
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

$msst_ids = get_posts(
	array(
		'post_type'      => 'msst_snippet',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $msst_ids as $msst_id ) {
	wp_delete_post( $msst_id, true );
}
foreach ( array( 'msst_settings', 'msst_global', 'msst_safe_secret', 'msst_audit', 'msst_errors' ) as $msst_option ) {
	delete_option( $msst_option );
}
delete_metadata( 'user', 0, 'msst_test_mode', '', true );
delete_post_meta_by_key( '_msst_page_header' );
delete_post_meta_by_key( '_msst_page_footer' );
delete_post_meta_by_key( '_msst_page_sig' );
