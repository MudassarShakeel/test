<?php
/**
 * Snippets list tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
$msst_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$msst_type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
$msst_paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable
$msst_counts = MSST_Snippets::counts();
$msst_list   = MSST_Snippets::query(
	array(
		'search' => $msst_search,
		'type'   => $msst_type,
		'paged'  => $msst_paged,
	)
);
$msst_types  = MSST_Snippets::types();
?>
<div class="msst-grid msst-grid-4">
	<div class="msst-card msst-stat"><b><?php echo esc_html( (string) $msst_counts['total'] ); ?></b><span><?php esc_html_e( 'Total snippets', 'mudassar-snippet-studio' ); ?></span></div>
	<div class="msst-card msst-stat"><b class="msst-ok"><?php echo esc_html( (string) $msst_counts['active'] ); ?></b><span><?php esc_html_e( 'Active', 'mudassar-snippet-studio' ); ?></span></div>
	<div class="msst-card msst-stat"><b class="msst-warn"><?php echo esc_html( (string) $msst_counts['scheduled'] ); ?></b><span><?php esc_html_e( 'Scheduled', 'mudassar-snippet-studio' ); ?></span></div>
	<div class="msst-card msst-stat"><b class="msst-err"><?php echo esc_html( (string) $msst_counts['errors'] ); ?></b><span><?php esc_html_e( 'Auto-disabled (error)', 'mudassar-snippet-studio' ); ?></span></div>
</div>

<form method="get" class="msst-toolbar">
	<input type="hidden" name="page" value="<?php echo esc_attr( MSST_Admin::PAGE ); ?>">
	<input type="hidden" name="tab" value="snippets">
	<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'edit' ) ); ?>"><?php esc_html_e( '+ Add Snippet', 'mudassar-snippet-studio' ); ?></a>
	<span class="msst-spacer"></span>
	<input class="msst-input msst-w-240" type="search" name="s" value="<?php echo esc_attr( $msst_search ); ?>" placeholder="<?php esc_attr_e( 'Search snippets…', 'mudassar-snippet-studio' ); ?>">
	<select class="msst-input msst-w-140" name="type" onchange="this.form.submit()">
		<option value=""><?php esc_html_e( 'All types', 'mudassar-snippet-studio' ); ?></option>
		<?php foreach ( $msst_types as $msst_slug => $msst_label ) : ?>
			<option value="<?php echo esc_attr( $msst_slug ); ?>" <?php selected( $msst_type, $msst_slug ); ?>><?php echo esc_html( $msst_label ); ?></option>
		<?php endforeach; ?>
	</select>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="msst_bulk">
	<?php wp_nonce_field( 'msst_bulk' ); ?>
	<div class="msst-toolbar">
		<select class="msst-input msst-w-140" name="bulk_action">
			<option value=""><?php esc_html_e( 'Bulk actions', 'mudassar-snippet-studio' ); ?></option>
			<option value="activate"><?php esc_html_e( 'Activate', 'mudassar-snippet-studio' ); ?></option>
			<option value="deactivate"><?php esc_html_e( 'Deactivate', 'mudassar-snippet-studio' ); ?></option>
			<option value="delete"><?php esc_html_e( 'Delete', 'mudassar-snippet-studio' ); ?></option>
		</select>
		<button type="submit" class="msst-btn msst-btn-outline" data-confirm="1"><?php esc_html_e( 'Apply', 'mudassar-snippet-studio' ); ?></button>
	</div>
	<table class="msst-table">
		<thead><tr>
			<th class="msst-w-30"><input type="checkbox" data-check-all="1" aria-label="<?php esc_attr_e( 'Select all', 'mudassar-snippet-studio' ); ?>"></th>
			<th><?php esc_html_e( 'Title', 'mudassar-snippet-studio' ); ?></th>
			<th><?php esc_html_e( 'Type', 'mudassar-snippet-studio' ); ?></th>
			<th><?php esc_html_e( 'Location', 'mudassar-snippet-studio' ); ?></th>
			<th><?php esc_html_e( 'Conditions', 'mudassar-snippet-studio' ); ?></th>
			<th><?php esc_html_e( 'Priority', 'mudassar-snippet-studio' ); ?></th>
			<th><?php esc_html_e( 'Status', 'mudassar-snippet-studio' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( ! $msst_list['items'] ) : ?>
			<tr><td colspan="7" class="msst-empty"><?php esc_html_e( 'No snippets yet. Add one or pick from the library.', 'mudassar-snippet-studio' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $msst_list['items'] as $msst_s ) : ?>
			<?php
			$msst_locations = MSST_Snippets::locations( $msst_s['type'] );
			$msst_loc_label = isset( $msst_locations[ $msst_s['location'] ] ) ? $msst_locations[ $msst_s['location'] ] : $msst_s['location'];
			$msst_cond_n    = array_sum( array_map( 'count', $msst_s['conditions'] ) );
			?>
			<tr>
				<td><input type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) $msst_s['id'] ); ?>" aria-label="<?php esc_attr_e( 'Select snippet', 'mudassar-snippet-studio' ); ?>"></td>
				<td>
					<a class="msst-strong" href="<?php echo esc_url( MSST_Admin::url( 'edit', array( 'snippet' => $msst_s['id'] ) ) ); ?>"><?php echo esc_html( $msst_s['title'] ); ?></a>
					<?php if ( '' !== $msst_s['error'] ) : ?>
						<span class="msst-pill msst-pill-err" title="<?php echo esc_attr( $msst_s['error'] ); ?>"><?php esc_html_e( 'Error – auto-disabled', 'mudassar-snippet-studio' ); ?></span>
					<?php elseif ( ( $msst_s['start'] || $msst_s['end'] ) ) : ?>
						<span class="msst-pill msst-pill-warn"><?php esc_html_e( 'Scheduled', 'mudassar-snippet-studio' ); ?></span>
					<?php endif; ?>
					<div class="msst-row-actions">
						<a href="<?php echo esc_url( MSST_Admin::url( 'edit', array( 'snippet' => $msst_s['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'mudassar-snippet-studio' ); ?></a> ·
						<a href="<?php echo esc_url( MSST_Admin::action_url( 'duplicate', array( 'snippet' => $msst_s['id'] ) ) ); ?>"><?php esc_html_e( 'Duplicate', 'mudassar-snippet-studio' ); ?></a> ·
						<a href="<?php echo esc_url( MSST_Admin::url( 'revisions', array( 'snippet' => $msst_s['id'] ) ) ); ?>"><?php esc_html_e( 'Revisions', 'mudassar-snippet-studio' ); ?></a> ·
						<a href="<?php echo esc_url( MSST_Admin::action_url( 'delete', array( 'snippet' => $msst_s['id'] ) ) ); ?>" data-confirm="1"><?php esc_html_e( 'Delete', 'mudassar-snippet-studio' ); ?></a>
					</div>
				</td>
				<td><span class="msst-pill msst-type-<?php echo esc_attr( $msst_s['type'] ); ?>"><?php echo esc_html( strtoupper( $msst_s['type'] ) ); ?></span></td>
				<td><?php echo esc_html( $msst_loc_label ); ?></td>
				<td><?php echo $msst_cond_n ? esc_html( sprintf( /* translators: %d: rules */ _n( '%d rule', '%d rules', $msst_cond_n, 'mudassar-snippet-studio' ), $msst_cond_n ) ) : esc_html__( 'All pages', 'mudassar-snippet-studio' ); ?></td>
				<td><?php echo esc_html( (string) $msst_s['priority'] ); ?></td>
				<td>
					<a class="msst-toggle <?php echo $msst_s['active'] ? 'is-on' : ''; ?>" href="
					<?php
					echo esc_url(
						MSST_Admin::action_url(
							'toggle',
							array(
								'snippet' => $msst_s['id'],
								'to'      => $msst_s['active'] ? '0' : '1',
							)
						)
					);
					?>
											" role="switch" aria-checked="<?php echo $msst_s['active'] ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Toggle snippet', 'mudassar-snippet-studio' ); ?>"></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</form>
<?php if ( $msst_list['pages'] > 1 ) : ?>
	<p class="msst-pager">
		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg(
						'paged',
						'%#%',
						MSST_Admin::url(
							'snippets',
							array(
								's'    => $msst_search,
								'type' => $msst_type,
							)
						)
					),
					'format'  => '',
					'current' => $msst_paged,
					'total'   => $msst_list['pages'],
				)
			)
		);
		?>
	</p>
<?php endif; ?>
