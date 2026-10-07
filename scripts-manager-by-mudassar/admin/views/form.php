<?php
/**
 * Add New / Edit snippet form.
 *
 * @package ScriptsManagerByMudassar
 * @var array $msst_snippet Snippet being edited (or defaults).
 * @var array $msst_choices Picker choices.
 */

defined( 'ABSPATH' ) || exit;

$msst_php_ok  = MSST_Security::can_edit_php();
$msst_locked  = $msst_snippet['id'] && 'php' === $msst_snippet['type'] && ! $msst_php_ok;
$msst_rows    = array(
	'pages'      => array( __( 'Page List', 'scripts-manager-by-mudassar' ), 'pages', 'msst_pages' ),
	'posts'      => array( __( 'Post List', 'scripts-manager-by-mudassar' ), 'posts', 'msst_posts' ),
	'categories' => array( __( 'Category List', 'scripts-manager-by-mudassar' ), 'categories', 'msst_categories' ),
	'post_types' => array( __( 'Post Types', 'scripts-manager-by-mudassar' ), 'post_types', 'msst_post_types' ),
	'tags'       => array( __( 'Tag List', 'scripts-manager-by-mudassar' ), 'tags', 'msst_tags' ),
	'ex_pages'   => array( __( 'Exclude Pages', 'scripts-manager-by-mudassar' ), 'pages', 'msst_ex_pages' ),
	'ex_posts'   => array( __( 'Exclude Posts', 'scripts-manager-by-mudassar' ), 'posts', 'msst_ex_posts' ),
);
$msst_visible = array(
	'site_wide'  => array( 'ex_pages', 'ex_posts' ),
	'pages'      => array( 'pages' ),
	'posts'      => array( 'posts' ),
	'categories' => array( 'categories' ),
	'post_types' => array( 'post_types' ),
	'tags'       => array( 'tags' ),
);
$msst_show    = isset( $msst_visible[ $msst_snippet['display_on'] ] ) ? $msst_visible[ $msst_snippet['display_on'] ] : array();
$msst_lint    = 'php' === $msst_snippet['type'] && '' !== $msst_snippet['code'] ? MSST_Snippets::lint_php( $msst_snippet['code'] ) : null;

/**
 * Print a searchable checkbox list.
 *
 * @param string $field    Field name (without []).
 * @param array  $items    id => label.
 * @param array  $selected Selected IDs or slugs.
 */
$msst_picker = static function ( $field, array $items, array $selected ) {
	$selected = array_map( 'strval', $selected );
	echo '<div class="msst-picker">';
	if ( count( $items ) > 8 ) {
		echo '<input type="search" class="msst-input msst-input-sm msst-picker-search" placeholder="' . esc_attr__( 'Type to filter…', 'scripts-manager-by-mudassar' ) . '" aria-label="' . esc_attr__( 'Filter the list', 'scripts-manager-by-mudassar' ) . '">';
	}
	echo '<div class="msst-picklist">';
	foreach ( $items as $value => $label ) {
		printf(
			'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> <span>%4$s</span></label>',
			esc_attr( $field ),
			esc_attr( (string) $value ),
			in_array( (string) $value, $selected, true ) ? 'checked' : '',
			esc_html( $label )
		);
	}
	if ( ! $items ) {
		echo '<span class="msst-small">' . esc_html__( 'Nothing to choose yet.', 'scripts-manager-by-mudassar' ) . '</span>';
	}
	echo '</div></div>';
};
?>
<?php if ( '' !== $msst_snippet['error'] ) : ?>
	<div class="msst-note msst-note-error"><strong><?php esc_html_e( 'This snippet was turned off automatically:', 'scripts-manager-by-mudassar' ); ?></strong> <?php echo esc_html( $msst_snippet['error'] ); ?></div>
<?php endif; ?>
<?php if ( ! $msst_php_ok ) : ?>
	<div class="msst-note msst-note-warning"><?php esc_html_e( 'PHP snippets are locked on this site for your account.', 'scripts-manager-by-mudassar' ); ?></div>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msst-card msst-form" id="msst-form">
	<input type="hidden" name="action" value="msst_save_snippet">
	<input type="hidden" name="msst_id" value="<?php echo esc_attr( (string) $msst_snippet['id'] ); ?>">
	<?php wp_nonce_field( 'msst_save_snippet' ); ?>

	<div class="msst-row">
		<label class="msst-label" for="msst_name"><?php esc_html_e( 'Snippet Name', 'scripts-manager-by-mudassar' ); ?></label>
		<div><input class="msst-input" type="text" id="msst_name" name="msst_name" value="<?php echo esc_attr( $msst_snippet['name'] ); ?>" maxlength="200" required></div>
	</div>

	<div class="msst-row">
		<label class="msst-label" for="msst_type"><?php esc_html_e( 'Snippet Type', 'scripts-manager-by-mudassar' ); ?></label>
		<div>
			<select class="msst-input" id="msst_type" name="msst_type">
				<?php foreach ( MSST_Snippets::types() as $msst_slug => $msst_label ) : ?>
					<option value="<?php echo esc_attr( $msst_slug ); ?>" <?php selected( $msst_snippet['type'], $msst_slug ); ?> <?php disabled( 'php' === $msst_slug && ! $msst_php_ok ); ?>><?php echo esc_html( $msst_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="msst-row">
		<label class="msst-label" for="msst_display_on"><?php esc_html_e( 'Site Display', 'scripts-manager-by-mudassar' ); ?></label>
		<div>
			<select class="msst-input" id="msst_display_on" name="msst_display_on">
				<?php foreach ( MSST_Snippets::display_options() as $msst_slug => $msst_label ) : ?>
					<option value="<?php echo esc_attr( $msst_slug ); ?>" <?php selected( $msst_snippet['display_on'], $msst_slug ); ?>><?php echo esc_html( $msst_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<?php foreach ( $msst_rows as $msst_key => $msst_def ) : ?>
		<div class="msst-row" data-row="<?php echo esc_attr( $msst_key ); ?>" <?php echo in_array( $msst_key, $msst_show, true ) ? '' : 'hidden'; ?>>
			<span class="msst-label"><?php echo esc_html( $msst_def[0] ); ?></span>
			<div><?php $msst_picker( $msst_def[2], $msst_choices[ $msst_def[1] ], $msst_snippet['targets'][ $msst_key ] ); ?></div>
		</div>
	<?php endforeach; ?>

	<div class="msst-row" id="msst-row-location" <?php echo 'shortcode' === $msst_snippet['display_on'] ? 'hidden' : ''; ?>>
		<label class="msst-label" for="msst_location"><?php esc_html_e( 'Location', 'scripts-manager-by-mudassar' ); ?></label>
		<div>
			<select class="msst-input" id="msst_location" name="msst_location">
				<?php foreach ( MSST_Snippets::locations( $msst_snippet['type'] ) as $msst_slug => $msst_label ) : ?>
					<option value="<?php echo esc_attr( $msst_slug ); ?>" <?php selected( $msst_snippet['location'], $msst_slug ); ?>><?php echo esc_html( $msst_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="msst-row">
		<label class="msst-label" for="msst_device"><?php esc_html_e( 'Device Display', 'scripts-manager-by-mudassar' ); ?></label>
		<div>
			<select class="msst-input" id="msst_device" name="msst_device">
				<?php foreach ( MSST_Snippets::devices() as $msst_slug => $msst_label ) : ?>
					<option value="<?php echo esc_attr( $msst_slug ); ?>" <?php selected( $msst_snippet['device'], $msst_slug ); ?>><?php echo esc_html( $msst_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="msst-row">
		<label class="msst-label" for="msst_status"><?php esc_html_e( 'Status', 'scripts-manager-by-mudassar' ); ?></label>
		<div>
			<select class="msst-input" id="msst_status" name="msst_status">
				<option value="1" <?php selected( $msst_snippet['status'], true ); ?>><?php esc_html_e( 'Active', 'scripts-manager-by-mudassar' ); ?></option>
				<option value="0" <?php selected( $msst_snippet['status'], false ); ?>><?php esc_html_e( 'Inactive', 'scripts-manager-by-mudassar' ); ?></option>
			</select>
		</div>
	</div>

	<div class="msst-row msst-row-code">
		<label class="msst-label" for="msst_code"><?php esc_html_e( 'Snippet / Code', 'scripts-manager-by-mudassar' ); ?></label>
		<div>
			<textarea class="msst-code" id="msst_code" name="msst_code" rows="14" spellcheck="false" <?php echo $msst_locked ? 'readonly' : ''; ?>><?php echo esc_textarea( $msst_snippet['code'] ); ?></textarea>
			<?php if ( $msst_lint && $msst_lint['ok'] ) : ?>
				<div class="msst-note msst-note-success"><?php esc_html_e( '✔ PHP syntax looks good.', 'scripts-manager-by-mudassar' ); ?></div>
				<?php foreach ( $msst_lint['warnings'] as $msst_warning ) : ?>
					<div class="msst-note msst-note-warning"><?php echo esc_html( $msst_warning ); ?></div>
				<?php endforeach; ?>
			<?php elseif ( $msst_lint ) : ?>
				<div class="msst-note msst-note-error"><?php echo esc_html( $msst_lint['error'] ); ?></div>
			<?php endif; ?>
			<div class="msst-note msst-note-warning"><?php esc_html_e( 'Warning: Using improper code or untrusted sources code can break your site or create security risks.', 'scripts-manager-by-mudassar' ); ?></div>
		</div>
	</div>

	<?php if ( ! $msst_locked ) : ?>
		<button type="submit" class="msst-btn msst-btn-big"><?php esc_html_e( 'Save', 'scripts-manager-by-mudassar' ); ?></button>
	<?php endif; ?>
	<a class="msst-btn msst-btn-outline msst-btn-big" href="<?php echo esc_url( MSST_Admin::url( MSST_Admin::PAGE_LIST ) ); ?>"><?php esc_html_e( 'Back to list', 'scripts-manager-by-mudassar' ); ?></a>
	<?php if ( $msst_snippet['id'] && 'php' !== $msst_snippet['type'] ) : ?>
		<p class="msst-small"><?php esc_html_e( 'Shortcode:', 'scripts-manager-by-mudassar' ); ?> <code class="msst-copy">[msst_snippet id="<?php echo esc_html( (string) $msst_snippet['id'] ); ?>"]</code></p>
	<?php endif; ?>
</form>
