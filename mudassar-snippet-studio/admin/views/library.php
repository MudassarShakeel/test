<?php
/**
 * Library & Generator tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Local Snippet Library', 'mudassar-snippet-studio' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Ready-made snippets stored inside the plugin. No external connection. They are added inactive.', 'mudassar-snippet-studio' ); ?></p>
		<table class="msst-table">
			<?php foreach ( MSST_Library::items() as $msst_key => $msst_item ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $msst_item['title'] ); ?></strong><br><span class="msst-pill msst-type-<?php echo esc_attr( $msst_item['type'] ); ?>"><?php echo esc_html( strtoupper( $msst_item['type'] ) ); ?></span></td>
					<td class="msst-right"><a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::action_url( 'use_library', array( 'item' => $msst_key ) ) ); ?>"><?php esc_html_e( 'Use', 'mudassar-snippet-studio' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Snippet Generator', 'mudassar-snippet-studio' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Create common PHP from a form. Values are escaped for you.', 'mudassar-snippet-studio' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msst_generate">
			<?php wp_nonce_field( 'msst_generate' ); ?>
			<p>
				<label class="msst-label" for="msst_generator"><?php esc_html_e( 'Generator', 'mudassar-snippet-studio' ); ?></label>
				<select class="msst-input" id="msst_generator" name="msst_generator">
					<?php foreach ( MSST_Library::generators() as $msst_slug => $msst_label ) : ?>
						<option value="<?php echo esc_attr( $msst_slug ); ?>"><?php echo esc_html( $msst_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p><label class="msst-label" for="msst_gen_name"><?php esc_html_e( 'Name', 'mudassar-snippet-studio' ); ?></label><input class="msst-input" type="text" id="msst_gen_name" name="msst_gen_name" placeholder="Portfolio" maxlength="60" required></p>
			<p><label class="msst-label" for="msst_gen_slug"><?php esc_html_e( 'Slug', 'mudassar-snippet-studio' ); ?></label><input class="msst-input" type="text" id="msst_gen_slug" name="msst_gen_slug" placeholder="portfolio" maxlength="20" pattern="[a-z0-9_\-]+" required></p>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Generate snippet', 'mudassar-snippet-studio' ); ?></button>
		</form>
	</div>
</div>
