<?php
/**
 * Ready-made snippets & generator tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

MSST_Admin::intro( __( 'Ready-made snippets', 'scripts-manager-by-mudassar' ), __( 'Click “Add” and the snippet is copied to My Snippets, switched OFF. Read it, then turn it ON.', 'scripts-manager-by-mudassar' ) );
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Pick one', 'scripts-manager-by-mudassar' ); ?></h2>
		<table class="msst-table">
			<?php foreach ( MSST_Library::items() as $msst_key => $msst_item ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $msst_item['title'] ); ?></strong>
						<span class="msst-pill"><?php echo esc_html( strtoupper( $msst_item['type'] ) ); ?></span><br>
						<span class="msst-desc msst-m0"><?php echo esc_html( $msst_item['desc'] ); ?></span>
					</td>
					<td class="msst-right"><a class="msst-btn msst-btn-sm" href="<?php echo esc_url( MSST_Admin::action_url( 'use_library', array( 'item' => $msst_key ) ) ); ?>"><?php esc_html_e( 'Add', 'scripts-manager-by-mudassar' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Make a snippet from a form', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Fill in two boxes. We write the code for you.', 'scripts-manager-by-mudassar' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_generate">
			<?php wp_nonce_field( 'msst_generate' ); ?>
			<p>
				<label class="msst-label" for="msst_generator"><?php esc_html_e( 'What do you want to make?', 'scripts-manager-by-mudassar' ); ?></label>
				<select class="msst-input" id="msst_generator" name="msst_generator">
					<?php foreach ( MSST_Library::generators() as $msst_slug => $msst_label ) : ?>
						<option value="<?php echo esc_attr( $msst_slug ); ?>"><?php echo esc_html( $msst_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p><label class="msst-label" for="msst_gen_name"><?php esc_html_e( 'Name', 'scripts-manager-by-mudassar' ); ?></label><input class="msst-input" type="text" id="msst_gen_name" name="msst_gen_name" placeholder="Portfolio" maxlength="60" required></p>
			<p><label class="msst-label" for="msst_gen_slug"><?php esc_html_e( 'Short name (lowercase, no spaces)', 'scripts-manager-by-mudassar' ); ?></label><input class="msst-input" type="text" id="msst_gen_slug" name="msst_gen_slug" placeholder="portfolio" maxlength="20" pattern="[a-z0-9_\-]+" required></p>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Create snippet', 'scripts-manager-by-mudassar' ); ?></button>
		</form>
	</div>
</div>
