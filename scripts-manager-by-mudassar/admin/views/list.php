<?php
/**
 * All Snippets list.
 *
 * @package ScriptsManagerByMudassar
 * @var array  $msst_list    Query result.
 * @var string $msst_search  Search text.
 * @var string $msst_orderby Sort column.
 * @var string $msst_order   asc|desc.
 * @var int    $msst_paged   Page number.
 */

defined( 'ABSPATH' ) || exit;

$msst_types = MSST_Snippets::types();
$msst_keep  = array_filter( array( 's' => $msst_search ) );
$msst_sort  = static function ( $column, $label ) use ( $msst_orderby, $msst_order, $msst_keep ) {
	$next = ( $msst_orderby === $column && 'asc' === $msst_order ) ? 'desc' : 'asc';
	$mark = $msst_orderby === $column ? ( 'asc' === $msst_order ? ' ▲' : ' ▼' ) : '';
	return '<a href="' . esc_url(
		MSST_Admin::url(
			MSST_Admin::PAGE_LIST,
			array_merge(
				$msst_keep,
				array(
					'orderby' => $column,
					'order'   => $next,
				)
			)
		)
	) . '">' . esc_html( $label . $mark ) . '</a>';
};
?>
<div class="msst-toolbar">
	<select form="msst-bulk" class="msst-input msst-input-sm msst-w-160" name="bulk_action" aria-label="<?php esc_attr_e( 'Bulk actions', 'scripts-manager-by-mudassar' ); ?>">
		<option value=""><?php esc_html_e( 'Bulk actions', 'scripts-manager-by-mudassar' ); ?></option>
		<option value="activate"><?php esc_html_e( 'Activate', 'scripts-manager-by-mudassar' ); ?></option>
		<option value="deactivate"><?php esc_html_e( 'Deactivate', 'scripts-manager-by-mudassar' ); ?></option>
		<option value="delete"><?php esc_html_e( 'Delete', 'scripts-manager-by-mudassar' ); ?></option>
	</select>
	<button form="msst-bulk" type="submit" class="msst-btn msst-btn-outline msst-btn-sm" data-confirm="1"><?php esc_html_e( 'Apply', 'scripts-manager-by-mudassar' ); ?></button>
	<span class="msst-spacer"></span>
	<form method="get" class="msst-search">
		<input type="hidden" name="page" value="<?php echo esc_attr( MSST_Admin::PAGE_LIST ); ?>">
		<input class="msst-input msst-input-sm msst-w-240" type="search" name="s" value="<?php echo esc_attr( $msst_search ); ?>" aria-label="<?php esc_attr_e( 'Search snippets', 'scripts-manager-by-mudassar' ); ?>">
		<button type="submit" class="msst-btn msst-btn-outline msst-btn-sm"><?php esc_html_e( 'Search Snippets', 'scripts-manager-by-mudassar' ); ?></button>
	</form>
	<span class="msst-small"><?php echo esc_html( sprintf( /* translators: %d: number of snippets */ _n( '%d item', '%d items', $msst_list['total'], 'scripts-manager-by-mudassar' ), $msst_list['total'] ) ); ?></span>
</div>

<form id="msst-bulk" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="msst_bulk">
	<?php wp_nonce_field( 'msst_bulk' ); ?>
	<table class="msst-table">
		<thead>
			<tr>
				<th class="msst-w-30"><input type="checkbox" data-check-all="1" aria-label="<?php esc_attr_e( 'Select all', 'scripts-manager-by-mudassar' ); ?>"></th>
				<th><?php echo $msst_sort( 'id', __( 'ID', 'scripts-manager-by-mudassar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?></th>
				<th><?php esc_html_e( 'Status', 'scripts-manager-by-mudassar' ); ?></th>
				<th><?php echo $msst_sort( 'name', __( 'Snippet Name', 'scripts-manager-by-mudassar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th><?php esc_html_e( 'Display On', 'scripts-manager-by-mudassar' ); ?></th>
				<th><?php echo $msst_sort( 'location', __( 'Location', 'scripts-manager-by-mudassar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th><?php echo $msst_sort( 'type', __( 'Snippet Type', 'scripts-manager-by-mudassar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th><?php esc_html_e( 'Devices', 'scripts-manager-by-mudassar' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'scripts-manager-by-mudassar' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $msst_list['items'] ) : ?>
			<tr><td colspan="9" class="msst-empty"><?php esc_html_e( 'No snippets found.', 'scripts-manager-by-mudassar' ); ?> <a href="<?php echo esc_url( MSST_Admin::url( MSST_Admin::PAGE_ADD ) ); ?>"><?php esc_html_e( 'Add New Snippet', 'scripts-manager-by-mudassar' ); ?></a></td></tr>
		<?php endif; ?>
		<?php foreach ( $msst_list['items'] as $msst_s ) : ?>
			<?php
			$msst_displays = MSST_Snippets::display_options();
			$msst_display  = isset( $msst_displays[ $msst_s['display_on'] ] ) ? preg_replace( '/\s*\(.*\)$/', '', $msst_displays[ $msst_s['display_on'] ] ) : '';
			$msst_locs     = MSST_Snippets::locations( $msst_s['type'] );
			$msst_loc      = 'shortcode' === $msst_s['display_on'] ? '—' : ( isset( $msst_locs[ $msst_s['location'] ] ) ? $msst_locs[ $msst_s['location'] ] : $msst_s['location'] );
			$msst_devices  = MSST_Snippets::devices();
			$msst_edit     = MSST_Admin::url( MSST_Admin::PAGE_ADD, array( 'id' => $msst_s['id'] ) );
			?>
			<tr>
				<td><input type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) $msst_s['id'] ); ?>" aria-label="<?php esc_attr_e( 'Select snippet', 'scripts-manager-by-mudassar' ); ?>"></td>
				<td><?php echo esc_html( (string) $msst_s['id'] ); ?></td>
				<td>
					<span class="msst-on-t">
						<a class="msst-toggle <?php echo $msst_s['status'] ? 'is-on' : ''; ?>" href="
						<?php
						echo esc_url(
							MSST_Admin::action_url(
								'toggle',
								array(
									'id' => $msst_s['id'],
									'to' => $msst_s['status'] ? '0' : '1',
								)
							)
						);
						?>
												" role="switch" aria-checked="<?php echo $msst_s['status'] ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Turn this snippet ON or OFF', 'scripts-manager-by-mudassar' ); ?>"></a>
						<?php echo $msst_s['status'] ? esc_html__( 'ON', 'scripts-manager-by-mudassar' ) : esc_html__( 'OFF', 'scripts-manager-by-mudassar' ); ?>
					</span>
				</td>
				<td>
					<a class="msst-strong" href="<?php echo esc_url( $msst_edit ); ?>"><?php echo esc_html( $msst_s['name'] ); ?></a>
					<?php if ( '' !== $msst_s['error'] ) : ?>
						<div class="msst-err msst-small">⚠ <?php echo esc_html( sprintf( /* translators: %s: error message */ __( 'Turned off automatically: %s', 'scripts-manager-by-mudassar' ), $msst_s['error'] ) ); ?></div>
					<?php endif; ?>
					<div class="msst-row-actions">
						<a href="<?php echo esc_url( $msst_edit ); ?>"><?php esc_html_e( 'Edit', 'scripts-manager-by-mudassar' ); ?></a> ·
						<a href="<?php echo esc_url( MSST_Admin::action_url( 'duplicate', array( 'id' => $msst_s['id'] ) ) ); ?>"><?php esc_html_e( 'Duplicate', 'scripts-manager-by-mudassar' ); ?></a> ·
						<a class="msst-danger" href="<?php echo esc_url( MSST_Admin::action_url( 'delete', array( 'id' => $msst_s['id'] ) ) ); ?>" data-confirm="1"><?php esc_html_e( 'Delete', 'scripts-manager-by-mudassar' ); ?></a>
					</div>
				</td>
				<td><?php echo esc_html( $msst_display ); ?></td>
				<td><?php echo esc_html( $msst_loc ); ?></td>
				<td><span class="msst-pill"><?php echo esc_html( $msst_types[ $msst_s['type'] ] ); ?></span></td>
				<td><?php echo esc_html( $msst_devices[ $msst_s['device'] ] ); ?></td>
				<td><?php echo 'php' === $msst_s['type'] ? '—' : '<code class="msst-copy">[msst_snippet id="' . esc_html( (string) $msst_s['id'] ) . '"]</code>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ID is escaped. ?></td>
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
							MSST_Admin::PAGE_LIST,
							array_merge(
								$msst_keep,
								array(
									'orderby' => $msst_orderby,
									'order'   => $msst_order,
								)
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
