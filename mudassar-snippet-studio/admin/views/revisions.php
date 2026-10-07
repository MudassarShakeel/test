<?php
/**
 * Revisions & Schedule tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

$msst_all     = MSST_Snippets::query( array( 'per_page' => 200 ) );
$msst_current = MSST_Admin::current_snippet();
$msst_compare = isset( $_GET['compare'] ) ? absint( $_GET['compare'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view.
?>
<form method="get" class="msst-toolbar">
	<input type="hidden" name="page" value="<?php echo esc_attr( MSST_Admin::PAGE ); ?>">
	<input type="hidden" name="tab" value="revisions">
	<label class="msst-label" for="msst_pick"><?php esc_html_e( 'Snippet', 'mudassar-snippet-studio' ); ?></label>
	<select class="msst-input msst-w-240" id="msst_pick" name="snippet" onchange="this.form.submit()">
		<option value=""><?php esc_html_e( 'Choose…', 'mudassar-snippet-studio' ); ?></option>
		<?php foreach ( $msst_all['items'] as $msst_s ) : ?>
			<option value="<?php echo esc_attr( (string) $msst_s['id'] ); ?>" <?php selected( $msst_current ? $msst_current['id'] : 0, $msst_s['id'] ); ?>><?php echo esc_html( $msst_s['title'] ); ?></option>
		<?php endforeach; ?>
	</select>
</form>

<?php if ( $msst_current ) : ?>
	<?php $msst_revisions = MSST_Snippets::revisions( $msst_current['id'] ); ?>
	<div class="msst-grid msst-grid-2">
		<div class="msst-card">
			<h2><?php echo esc_html( sprintf( /* translators: %s: snippet title */ __( 'Revisions – %s', 'mudassar-snippet-studio' ), $msst_current['title'] ) ); ?></h2>
			<?php if ( ! $msst_revisions ) : ?>
				<p class="msst-desc"><?php esc_html_e( 'No earlier versions yet. A revision is saved each time the code changes.', 'mudassar-snippet-studio' ); ?></p>
			<?php else : ?>
				<table class="msst-table">
					<thead><tr><th><?php esc_html_e( 'Date', 'mudassar-snippet-studio' ); ?></th><th><?php esc_html_e( 'By', 'mudassar-snippet-studio' ); ?></th><th></th></tr></thead>
					<?php foreach ( $msst_revisions as $msst_i => $msst_rev ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', (int) $msst_rev['time'] ) ); ?></td>
							<td><?php echo esc_html( $msst_rev['user'] ); ?></td>
							<td class="msst-right">
								<a class="msst-btn msst-btn-outline" href="
								<?php
								echo esc_url(
									MSST_Admin::url(
										'revisions',
										array(
											'snippet' => $msst_current['id'],
											'compare' => $msst_i,
										)
									)
								);
								?>
																			"><?php esc_html_e( 'Compare', 'mudassar-snippet-studio' ); ?></a>
								<a class="msst-btn msst-btn-outline" data-confirm="1" href="
								<?php
								echo esc_url(
									MSST_Admin::action_url(
										'restore',
										array(
											'snippet' => $msst_current['id'],
											'rev'     => $msst_i,
										)
									)
								);
								?>
																							"><?php esc_html_e( 'Restore', 'mudassar-snippet-studio' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>
		</div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Compare with current', 'mudassar-snippet-studio' ); ?></h2>
			<?php if ( isset( $msst_revisions[ $msst_compare ] ) ) : ?>
				<div class="msst-code msst-diff">
					<?php foreach ( MSST_Diff::lines( $msst_revisions[ $msst_compare ]['code'], $msst_current['code'] ) as $msst_line ) : ?>
						<div class="msst-diff-<?php echo esc_attr( $msst_line['op'] ); ?>"><?php echo esc_html( ( 'add' === $msst_line['op'] ? '+ ' : ( 'del' === $msst_line['op'] ? '- ' : '  ' ) ) . $msst_line['text'] ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="msst-desc"><?php esc_html_e( 'Choose a revision to see what changed.', 'mudassar-snippet-studio' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Schedule', 'mudassar-snippet-studio' ); ?></h2>
		<p class="msst-desc">
			<?php
			if ( $msst_current['start'] || $msst_current['end'] ) {
				echo esc_html(
					sprintf(
						/* translators: 1: start, 2: end */
						__( 'Runs from %1$msst_s until %2$msst_s (site time).', 'mudassar-snippet-studio' ),
						$msst_current['start'] ? wp_date( 'Y-m-d H:i', $msst_current['start'] ) : __( 'now', 'mudassar-snippet-studio' ),
						$msst_current['end'] ? wp_date( 'Y-m-d H:i', $msst_current['end'] ) : __( 'forever', 'mudassar-snippet-studio' )
					)
				);
			} else {
				esc_html_e( 'No schedule – runs whenever it is active.', 'mudassar-snippet-studio' );
			}
			?>
		</p>
		<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::url( 'edit', array( 'snippet' => $msst_current['id'] ) ) ); ?>"><?php esc_html_e( 'Change schedule', 'mudassar-snippet-studio' ); ?></a>
	</div>
<?php endif; ?>
