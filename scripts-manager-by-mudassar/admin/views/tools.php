<?php
/**
 * Import / Export tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

MSST_Admin::intro( __( 'Move snippets between sites', 'scripts-manager-by-mudassar' ), __( 'Download a backup file, or load one from another site. Loaded snippets arrive OFF so nothing runs by surprise.', 'scripts-manager-by-mudassar' ) );
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Download a backup', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Saves all your snippets in one file.', 'scripts-manager-by-mudassar' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_export">
			<?php wp_nonce_field( 'msst_export' ); ?>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Download file', 'scripts-manager-by-mudassar' ); ?></button>
		</form>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Load a backup', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Choose a .json file you downloaded before (max 2 MB). Every part is checked for safety.', 'scripts-manager-by-mudassar' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_import">
			<?php wp_nonce_field( 'msst_import' ); ?>
			<p><input type="file" name="msst_file" accept=".json,application/json" required></p>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Load file', 'scripts-manager-by-mudassar' ); ?></button>
		</form>
	</div>
</div>
