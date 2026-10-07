<?php
/**
 * Tools: export and import.
 *
 * @package ScriptsManagerByMudassar
 * @var array $msst_all Query result of all snippets.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="msst-grid msst-grid-2">
	<form class="msst-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h2><?php esc_html_e( 'Export Snippets', 'scripts-manager-by-mudassar' ); ?></h2>
		<input type="hidden" name="action" value="msst_export">
		<?php wp_nonce_field( 'msst_export' ); ?>
		<div class="msst-picker">
			<div class="msst-picklist msst-picklist-tall">
				<?php if ( ! $msst_all['items'] ) : ?>
					<span class="msst-small"><?php esc_html_e( 'No snippets yet.', 'scripts-manager-by-mudassar' ); ?></span>
				<?php else : ?>
					<label><input type="checkbox" data-check-all="1"> <strong><?php esc_html_e( 'Select all', 'scripts-manager-by-mudassar' ); ?></strong></label>
				<?php endif; ?>
				<?php foreach ( $msst_all['items'] as $msst_s ) : ?>
					<label><input type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) $msst_s['id'] ); ?>"> <span><?php echo esc_html( $msst_s['name'] ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</div>
		<button type="submit" class="msst-btn"><?php esc_html_e( 'Export File', 'scripts-manager-by-mudassar' ); ?></button>
	</form>
	<form class="msst-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h2><?php esc_html_e( 'Import Snippets', 'scripts-manager-by-mudassar' ); ?></h2>
		<input type="hidden" name="action" value="msst_import">
		<?php wp_nonce_field( 'msst_import' ); ?>
		<p><input type="file" name="msst_file" accept=".json,application/json" required></p>
		<p class="msst-small"><?php esc_html_e( 'Imported snippets arrive OFF so nothing runs by surprise. Max 2 MB.', 'scripts-manager-by-mudassar' ); ?></p>
		<button type="submit" class="msst-btn"><?php esc_html_e( 'Import', 'scripts-manager-by-mudassar' ); ?></button>
	</form>
</div>
