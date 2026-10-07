<?php
/**
 * Import / Export tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Download a backup', 'scripts-manager-by-mudassar' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_export">
			<?php wp_nonce_field( 'msst_export' ); ?>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Download file', 'scripts-manager-by-mudassar' ); ?></button>
		</form>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Load a backup', 'scripts-manager-by-mudassar' ); ?></h2>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_import">
			<?php wp_nonce_field( 'msst_import' ); ?>
			<p><input type="file" name="msst_file" accept=".json,application/json" required></p>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Load file', 'scripts-manager-by-mudassar' ); ?></button>
		</form>
	</div>
</div>
