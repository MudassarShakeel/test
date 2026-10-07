<?php
/**
 * Import / Export tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Export', 'mudassar-snippet-studio' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Download all snippets as a JSON file.', 'mudassar-snippet-studio' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_export">
			<?php wp_nonce_field( 'msst_export' ); ?>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Download JSON', 'mudassar-snippet-studio' ); ?></button>
		</form>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Import', 'mudassar-snippet-studio' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Upload a JSON export (max 2 MB). Every field is validated and all imported snippets arrive inactive until you review them.', 'mudassar-snippet-studio' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_import">
			<?php wp_nonce_field( 'msst_import' ); ?>
			<p><input type="file" name="msst_file" accept=".json,application/json" required></p>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Import', 'mudassar-snippet-studio' ); ?></button>
		</form>
	</div>
</div>
