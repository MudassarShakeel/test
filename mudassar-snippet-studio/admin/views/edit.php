<?php
/**
 * Add / Edit snippet tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

$msst_snippet = MSST_Admin::current_snippet();
$msst_new     = ! $msst_snippet;
if ( $msst_new ) {
	$msst_snippet = array(
		'id'         => 0,
		'title'      => '',
		'type'       => MSST_Security::can_edit_php() ? 'php' : 'html',
		'code'       => '',
		'location'   => '',
		'param'      => 1,
		'priority'   => 10,
		'active'     => false,
		'conditions' => array(),
		'start'      => 0,
		'end'        => 0,
		'sig'        => '',
		'error'      => '',
	);
}
$msst_types     = MSST_Snippets::types();
$msst_locations = MSST_Snippets::locations( $msst_snippet['type'] );
$msst_php_ok    = MSST_Security::can_edit_php();
$msst_lint      = 'php' === $msst_snippet['type'] && '' !== $msst_snippet['code'] ? MSST_Snippets::lint_php( $msst_snippet['code'] ) : null;
$msst_locked    = ! $msst_new && 'php' === $msst_snippet['type'] && ! $msst_php_ok;
$msst_fmt       = static function ( $ts ) {
	return $ts ? wp_date( 'Y-m-d\TH:i', $ts ) : '';
};
?>
<?php if ( ! $msst_new && ! MSST_Snippets::is_intact( $msst_snippet ) ) : ?>
	<div class="msst-note msst-note-error"><?php esc_html_e( 'Integrity warning: this snippet was changed outside the plugin. It will not run until you review it and save or activate it again.', 'mudassar-snippet-studio' ); ?></div>
<?php endif; ?>
<?php if ( '' !== $msst_snippet['error'] ) : ?>
	<div class="msst-note msst-note-error"><strong><?php esc_html_e( 'Auto-disabled:', 'mudassar-snippet-studio' ); ?></strong> <?php echo esc_html( $msst_snippet['error'] ); ?></div>
<?php endif; ?>
<?php if ( ! $msst_php_ok ) : ?>
	<div class="msst-note msst-note-warning"><?php esc_html_e( 'PHP snippets are locked on this site (needs unfiltered_html, super admin on multisite, and file editing allowed – or MSST_ALLOW_PHP_EDIT in wp-config.php).', 'mudassar-snippet-studio' ); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msst-grid msst-grid-main">
	<input type="hidden" name="action" value="msst_save_snippet">
	<input type="hidden" name="msst_id" value="<?php echo esc_attr( (string) $msst_snippet['id'] ); ?>">
	<?php wp_nonce_field( 'msst_save_snippet' ); ?>
	<div>
		<div class="msst-card">
			<p>
				<label class="msst-label" for="msst_title"><?php esc_html_e( 'Title', 'mudassar-snippet-studio' ); ?></label>
				<input class="msst-input" type="text" id="msst_title" name="msst_title" value="<?php echo esc_attr( $msst_snippet['title'] ); ?>" maxlength="200" required>
			</p>
			<div class="msst-types" role="radiogroup" aria-label="<?php esc_attr_e( 'Snippet type', 'mudassar-snippet-studio' ); ?>">
				<?php foreach ( $msst_types as $msst_slug => $msst_label ) : ?>
					<label class="msst-type">
						<input type="radio" name="msst_type" value="<?php echo esc_attr( $msst_slug ); ?>" <?php checked( $msst_snippet['type'], $msst_slug ); ?> <?php disabled( 'php' === $msst_slug && ! $msst_php_ok ); ?>>
						<span><?php echo esc_html( $msst_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<textarea class="msst-code" id="msst_code" name="msst_code" rows="14" spellcheck="false" <?php echo $msst_locked ? 'readonly' : ''; ?>><?php echo esc_textarea( $msst_snippet['code'] ); ?></textarea>
			<?php if ( $msst_lint && $msst_lint['ok'] ) : ?>
				<div class="msst-note msst-note-success"><?php esc_html_e( '✔ Syntax check passed', 'mudassar-snippet-studio' ); ?></div>
				<?php foreach ( $msst_lint['warnings'] as $msst_warning ) : ?>
					<div class="msst-note msst-note-warning"><?php echo esc_html( $msst_warning ); ?></div>
				<?php endforeach; ?>
			<?php elseif ( $msst_lint ) : ?>
				<div class="msst-note msst-note-error"><?php echo esc_html( $msst_lint['error'] ); ?></div>
			<?php else : ?>
				<p class="msst-desc"><?php esc_html_e( 'PHP is syntax-checked when you save. Do not include <?php tags.', 'mudassar-snippet-studio' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Smart Conditional Logic', 'mudassar-snippet-studio' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'Run this snippet only when ANY group matches. A group matches when ALL of its rules match. Leave empty to always run.', 'mudassar-snippet-studio' ); ?></p>
			<div id="msst-rules"></div>
		</div>
	</div>
	<div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Publish', 'mudassar-snippet-studio' ); ?></h2>
			<p><label><input type="checkbox" name="msst_active" value="1" <?php checked( $msst_snippet['active'] ); ?>> <?php esc_html_e( 'Active', 'mudassar-snippet-studio' ); ?></label></p>
			<p>
				<label class="msst-label" for="msst_location"><?php esc_html_e( 'Location', 'mudassar-snippet-studio' ); ?></label>
				<select class="msst-input" id="msst_location" name="msst_location" data-current="<?php echo esc_attr( $msst_snippet['location'] ); ?>">
					<?php foreach ( $msst_locations as $msst_slug => $msst_label ) : ?>
						<option value="<?php echo esc_attr( $msst_slug ); ?>" <?php selected( $msst_snippet['location'], $msst_slug ); ?>><?php echo esc_html( $msst_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p id="msst-param-wrap" hidden>
				<label class="msst-label" for="msst_param"><?php esc_html_e( 'Paragraph number', 'mudassar-snippet-studio' ); ?></label>
				<input class="msst-input" type="number" min="1" max="50" id="msst_param" name="msst_param" value="<?php echo esc_attr( (string) $msst_snippet['param'] ); ?>">
			</p>
			<p>
				<label class="msst-label" for="msst_priority"><?php esc_html_e( 'Priority (lower runs first)', 'mudassar-snippet-studio' ); ?></label>
				<input class="msst-input" type="number" min="0" max="999" id="msst_priority" name="msst_priority" value="<?php echo esc_attr( (string) $msst_snippet['priority'] ); ?>">
			</p>
			<p>
				<label class="msst-label" for="msst_start"><?php esc_html_e( 'Start (site time, optional)', 'mudassar-snippet-studio' ); ?></label>
				<input class="msst-input" type="datetime-local" id="msst_start" name="msst_start" value="<?php echo esc_attr( $msst_fmt( $msst_snippet['start'] ) ); ?>">
			</p>
			<p>
				<label class="msst-label" for="msst_end"><?php esc_html_e( 'End (site time, optional)', 'mudassar-snippet-studio' ); ?></label>
				<input class="msst-input" type="datetime-local" id="msst_end" name="msst_end" value="<?php echo esc_attr( $msst_fmt( $msst_snippet['end'] ) ); ?>">
			</p>
			<?php if ( ! $msst_locked ) : ?>
				<button type="submit" class="msst-btn"><?php esc_html_e( 'Save Snippet', 'mudassar-snippet-studio' ); ?></button>
			<?php endif; ?>
			<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::url( 'snippets' ) ); ?>"><?php esc_html_e( 'Back', 'mudassar-snippet-studio' ); ?></a>
		</div>
		<?php if ( ! $msst_new ) : ?>
			<div class="msst-card">
				<h2><?php esc_html_e( 'Shortcode', 'mudassar-snippet-studio' ); ?></h2>
				<p><code class="msst-copy">[msst_snippet id="<?php echo esc_html( (string) $msst_snippet['id'] ); ?>"]</code></p>
				<p class="msst-desc"><?php esc_html_e( 'Works for non-PHP snippets whose location is "Shortcode only".', 'mudassar-snippet-studio' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</form>
