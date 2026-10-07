<?php
/**
 * History & Schedule tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

$msst_all     = MSST_Snippets::query( array( 'per_page' => 200 ) );
$msst_current = MSST_Admin::current_snippet();
$msst_compare = isset( $_GET['compare'] ) ? absint( $_GET['compare'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view.

MSST_Admin::intro( __( 'History & schedule', 'scripts-manager-by-mudassar' ), __( 'Every time you change code, the old version is saved. You can go back any time.', 'scripts-manager-by-mudassar' ) );
?>
<form method="get" class="msst-card msst-inline-card">
	<input type="hidden" name="page" value="<?php echo esc_attr( MSST_Admin::PAGE ); ?>">
	<input type="hidden" name="tab" value="revisions">
	<label class="msst-label" for="msst_pick"><?php esc_html_e( 'Choose a snippet', 'scripts-manager-by-mudassar' ); ?></label>
	<select class="msst-input msst-w-320" id="msst_pick" name="snippet" onchange="this.form.submit()">
		<option value=""><?php esc_html_e( 'Choose…', 'scripts-manager-by-mudassar' ); ?></option>
		<?php foreach ( $msst_all['items'] as $msst_s ) : ?>
			<option value="<?php echo esc_attr( (string) $msst_s['id'] ); ?>" <?php selected( $msst_current ? $msst_current['id'] : 0, $msst_s['id'] ); ?>><?php echo esc_html( $msst_s['title'] ); ?></option>
		<?php endforeach; ?>
	</select>
</form>

<?php if ( $msst_current ) : ?>
	<?php $msst_revisions = MSST_Snippets::revisions( $msst_current['id'] ); ?>
	<div class="msst-grid msst-grid-2">
		<div class="msst-card">
			<h2><?php echo esc_html( sprintf( /* translators: %s: snippet title */ __( 'Older versions of “%s”', 'scripts-manager-by-mudassar' ), $msst_current['title'] ) ); ?></h2>
			<?php if ( ! $msst_revisions ) : ?>
				<p class="msst-desc"><?php esc_html_e( 'No older versions yet. One is saved each time the code changes.', 'scripts-manager-by-mudassar' ); ?></p>
			<?php else : ?>
				<table class="msst-table">
					<?php foreach ( $msst_revisions as $msst_i => $msst_rev ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', (int) $msst_rev['time'] ) ); ?></td>
							<td><?php echo esc_html( $msst_rev['user'] ); ?></td>
							<td class="msst-right msst-nowrap">
								<a class="msst-btn msst-btn-outline msst-btn-sm" href="
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
																						"><?php esc_html_e( 'See changes', 'scripts-manager-by-mudassar' ); ?></a>
								<a class="msst-btn msst-btn-sm" data-confirm="1" href="
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
																						"><?php esc_html_e( 'Go back to this', 'scripts-manager-by-mudassar' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>
		</div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'What changed', 'scripts-manager-by-mudassar' ); ?></h2>
			<?php if ( isset( $msst_revisions[ $msst_compare ] ) ) : ?>
				<p class="msst-hint"><?php esc_html_e( 'Red lines were removed. Green lines were added.', 'scripts-manager-by-mudassar' ); ?></p>
				<div class="msst-code msst-diff">
					<?php foreach ( MSST_Diff::lines( $msst_revisions[ $msst_compare ]['code'], $msst_current['code'] ) as $msst_line ) : ?>
						<div class="msst-diff-<?php echo esc_attr( $msst_line['op'] ); ?>"><?php echo esc_html( ( 'add' === $msst_line['op'] ? '+ ' : ( 'del' === $msst_line['op'] ? '- ' : '  ' ) ) . $msst_line['text'] ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="msst-desc"><?php esc_html_e( 'Click “See changes” on an older version.', 'scripts-manager-by-mudassar' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Schedule', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc">
			<?php
			if ( $msst_current['start'] || $msst_current['end'] ) {
				echo esc_html(
					sprintf(
						/* translators: 1: start, 2: end */
						__( 'Shows from %1$s until %2$s (site time).', 'scripts-manager-by-mudassar' ),
						$msst_current['start'] ? wp_date( 'Y-m-d H:i', $msst_current['start'] ) : __( 'now', 'scripts-manager-by-mudassar' ),
						$msst_current['end'] ? wp_date( 'Y-m-d H:i', $msst_current['end'] ) : __( 'forever', 'scripts-manager-by-mudassar' )
					)
				);
			} else {
				esc_html_e( 'No schedule – it shows whenever it is ON. Set dates in Add Snippet → More options (great for sales banners).', 'scripts-manager-by-mudassar' );
			}
			?>
		</p>
		<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::url( 'edit', array( 'snippet' => $msst_current['id'] ) ) ); ?>"><?php esc_html_e( 'Change schedule', 'scripts-manager-by-mudassar' ); ?></a>
	</div>
<?php endif; ?>
