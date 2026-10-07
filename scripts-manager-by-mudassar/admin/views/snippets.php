<?php
/**
 * My Snippets tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
$msst_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$msst_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$msst_paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable
$msst_status = in_array( $msst_status, array( 'on', 'off', 'problem' ), true ) ? $msst_status : '';
$msst_counts = MSST_Snippets::counts();
$msst_list   = MSST_Snippets::query(
	array(
		'search' => $msst_search,
		'status' => $msst_status,
		'paged'  => $msst_paged,
	)
);
$msst_kinds  = MSST_Snippets::kinds();

MSST_Admin::intro( __( 'Your snippets', 'scripts-manager-by-mudassar' ), __( 'Every box of code you added is listed here. Use the switch to turn each one ON or OFF.', 'scripts-manager-by-mudassar' ) );
?>
<div class="msst-grid msst-grid-4">
	<div class="msst-card msst-stat"><b><?php echo esc_html( (string) $msst_counts['total'] ); ?></b><span><?php esc_html_e( 'Snippets', 'scripts-manager-by-mudassar' ); ?></span></div>
	<div class="msst-card msst-stat"><b class="msst-ok"><?php echo esc_html( (string) $msst_counts['active'] ); ?></b><span><?php esc_html_e( 'Turned ON', 'scripts-manager-by-mudassar' ); ?></span></div>
	<div class="msst-card msst-stat"><b class="msst-warn"><?php echo esc_html( (string) $msst_counts['scheduled'] ); ?></b><span><?php esc_html_e( 'Scheduled', 'scripts-manager-by-mudassar' ); ?></span></div>
	<div class="msst-card msst-stat"><b class="msst-err"><?php echo esc_html( (string) $msst_counts['errors'] ); ?></b><span><?php esc_html_e( 'Need attention', 'scripts-manager-by-mudassar' ); ?></span></div>
</div>

<form method="get" class="msst-toolbar">
	<input type="hidden" name="page" value="<?php echo esc_attr( MSST_Admin::PAGE ); ?>">
	<input type="hidden" name="tab" value="snippets">
	<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'edit' ) ); ?>"><?php esc_html_e( '+ Add Snippet', 'scripts-manager-by-mudassar' ); ?></a>
	<span class="msst-chips">
		<?php
		$msst_chips = array(
			''        => __( 'All', 'scripts-manager-by-mudassar' ),
			'on'      => __( 'ON', 'scripts-manager-by-mudassar' ),
			'off'     => __( 'OFF', 'scripts-manager-by-mudassar' ),
			'problem' => __( 'Needs attention', 'scripts-manager-by-mudassar' ),
		);
		foreach ( $msst_chips as $msst_key => $msst_label ) :
			?>
			<a class="<?php echo $msst_key === $msst_status ? 'is-active' : ''; ?>" href="
			<?php
			echo esc_url(
				MSST_Admin::url(
					'snippets',
					array_filter(
						array(
							'status' => $msst_key,
							's'      => $msst_search,
						)
					)
				)
			);
			?>
						"><?php echo esc_html( $msst_label ); ?></a>
		<?php endforeach; ?>
	</span>
	<span class="msst-spacer"></span>
	<input class="msst-input msst-w-240" type="search" name="s" value="<?php echo esc_attr( $msst_search ); ?>" placeholder="<?php esc_attr_e( 'Search by name…', 'scripts-manager-by-mudassar' ); ?>">
	<?php if ( $msst_status ) : ?>
		<input type="hidden" name="status" value="<?php echo esc_attr( $msst_status ); ?>">
	<?php endif; ?>
</form>

<?php if ( ! $msst_list['items'] ) : ?>
	<div class="msst-card msst-center msst-empty-card">
		<div class="msst-ic msst-ic-center" aria-hidden="true">🗂️</div>
		<h2><?php echo $msst_search || $msst_status ? esc_html__( 'Nothing matches your search', 'scripts-manager-by-mudassar' ) : esc_html__( 'No snippets yet', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Start with a ready-made snippet, or add your own in 4 easy steps.', 'scripts-manager-by-mudassar' ); ?></p>
		<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'edit' ) ); ?>"><?php esc_html_e( 'Add my first snippet', 'scripts-manager-by-mudassar' ); ?></a>
		<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::url( 'library' ) ); ?>"><?php esc_html_e( 'Browse ready-made', 'scripts-manager-by-mudassar' ); ?></a>
	</div>
<?php else : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="msst_bulk">
	<?php wp_nonce_field( 'msst_bulk' ); ?>
	<div class="msst-toolbar">
		<label class="msst-small"><?php esc_html_e( 'With selected:', 'scripts-manager-by-mudassar' ); ?></label>
		<select class="msst-input msst-w-140" name="bulk_action">
			<option value=""><?php esc_html_e( 'Choose…', 'scripts-manager-by-mudassar' ); ?></option>
			<option value="activate"><?php esc_html_e( 'Turn ON', 'scripts-manager-by-mudassar' ); ?></option>
			<option value="deactivate"><?php esc_html_e( 'Turn OFF', 'scripts-manager-by-mudassar' ); ?></option>
			<option value="delete"><?php esc_html_e( 'Delete', 'scripts-manager-by-mudassar' ); ?></option>
		</select>
		<button type="submit" class="msst-btn msst-btn-outline msst-btn-sm" data-confirm="1"><?php esc_html_e( 'Apply', 'scripts-manager-by-mudassar' ); ?></button>
	</div>
	<table class="msst-table">
		<thead><tr>
			<th class="msst-w-30"><input type="checkbox" data-check-all="1" aria-label="<?php esc_attr_e( 'Select all', 'scripts-manager-by-mudassar' ); ?>"></th>
			<th><?php esc_html_e( 'Name', 'scripts-manager-by-mudassar' ); ?></th>
			<th><?php esc_html_e( 'What it is', 'scripts-manager-by-mudassar' ); ?></th>
			<th><?php esc_html_e( 'Where it shows', 'scripts-manager-by-mudassar' ); ?></th>
			<th><?php esc_html_e( 'On which pages', 'scripts-manager-by-mudassar' ); ?></th>
			<th><?php esc_html_e( 'Status', 'scripts-manager-by-mudassar' ); ?></th>
			<th></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $msst_list['items'] as $msst_s ) : ?>
			<?php
			$msst_locations = MSST_Snippets::locations( $msst_s['type'] );
			$msst_loc_label = isset( $msst_locations[ $msst_s['location'] ] ) ? $msst_locations[ $msst_s['location'] ] : $msst_s['location'];
			$msst_edit_url  = MSST_Admin::url( 'edit', array( 'snippet' => $msst_s['id'] ) );
			?>
			<tr>
				<td><input type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) $msst_s['id'] ); ?>" aria-label="<?php esc_attr_e( 'Select snippet', 'scripts-manager-by-mudassar' ); ?>"></td>
				<td>
					<a class="msst-strong" href="<?php echo esc_url( $msst_edit_url ); ?>"><?php echo esc_html( $msst_s['title'] ); ?></a>
					<?php if ( '' !== $msst_s['error'] ) : ?>
						<br><small class="msst-err">⚠ <?php esc_html_e( 'Turned off automatically – it had an error. Click Fix it to see why.', 'scripts-manager-by-mudassar' ); ?></small>
					<?php elseif ( $msst_s['start'] || $msst_s['end'] ) : ?>
						<span class="msst-pill msst-pill-warn"><?php esc_html_e( 'Scheduled', 'scripts-manager-by-mudassar' ); ?></span>
					<?php endif; ?>
				</td>
				<td><span class="msst-pill"><?php echo esc_html( isset( $msst_kinds[ $msst_s['type'] ] ) ? $msst_kinds[ $msst_s['type'] ][0] : $msst_s['type'] ); ?></span></td>
				<td><?php echo esc_html( $msst_loc_label ); ?></td>
				<td><?php echo $msst_s['conditions'] ? esc_html__( 'Only some pages', 'scripts-manager-by-mudassar' ) : esc_html__( 'All pages', 'scripts-manager-by-mudassar' ); ?></td>
				<td>
					<span class="msst-on-t">
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
												" role="switch" aria-checked="<?php echo $msst_s['active'] ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Turn this snippet ON or OFF', 'scripts-manager-by-mudassar' ); ?>"></a>
						<?php echo $msst_s['active'] ? esc_html__( 'ON', 'scripts-manager-by-mudassar' ) : esc_html__( 'OFF', 'scripts-manager-by-mudassar' ); ?>
					</span>
				</td>
				<td class="msst-right msst-nowrap">
					<a class="msst-btn msst-btn-sm <?php echo '' !== $msst_s['error'] ? '' : 'msst-btn-outline'; ?>" href="<?php echo esc_url( $msst_edit_url ); ?>"><?php echo '' !== $msst_s['error'] ? esc_html__( 'Fix it', 'scripts-manager-by-mudassar' ) : esc_html__( 'Edit', 'scripts-manager-by-mudassar' ); ?></a>
					<details class="msst-menu">
						<summary class="msst-btn msst-btn-outline msst-btn-sm"><?php esc_html_e( 'More', 'scripts-manager-by-mudassar' ); ?> ▾</summary>
						<div class="msst-menu-panel">
							<a href="<?php echo esc_url( MSST_Admin::action_url( 'duplicate', array( 'snippet' => $msst_s['id'] ) ) ); ?>"><?php esc_html_e( 'Make a copy', 'scripts-manager-by-mudassar' ); ?></a>
							<a href="<?php echo esc_url( MSST_Admin::url( 'revisions', array( 'snippet' => $msst_s['id'] ) ) ); ?>"><?php esc_html_e( 'See older versions', 'scripts-manager-by-mudassar' ); ?></a>
							<a class="msst-danger" href="<?php echo esc_url( MSST_Admin::action_url( 'delete', array( 'snippet' => $msst_s['id'] ) ) ); ?>" data-confirm="1"><?php esc_html_e( 'Delete', 'scripts-manager-by-mudassar' ); ?></a>
						</div>
					</details>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</form>
<?php endif; ?>
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
							array_filter(
								array(
									's'      => $msst_search,
									'status' => $msst_status,
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
