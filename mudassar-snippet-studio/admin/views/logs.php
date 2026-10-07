<?php
/**
 * Error Log & Audit tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

$msst_errors = MSST_Logger::get( 'errors' );
$msst_audit  = MSST_Logger::get( 'audit' );
$msst_fmt    = static function ( $ts ) {
	return wp_date( get_option( 'date_format' ) . ' H:i', (int) $ts );
};
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Error Log', 'mudassar-snippet-studio' ); ?></h2>
		<?php if ( ! $msst_errors ) : ?>
			<p class="msst-desc"><?php esc_html_e( 'No errors. 🎉', 'mudassar-snippet-studio' ); ?></p>
		<?php endif; ?>
		<?php foreach ( array_slice( $msst_errors, 0, 50 ) as $msst_row ) : ?>
			<div class="msst-note msst-note-<?php echo 'warning' === $msst_row['level'] ? 'warning' : 'error'; ?>">
				<strong><?php echo esc_html( $msst_row['title'] ); ?></strong> – <?php echo esc_html( $msst_row['msg'] ); ?><br>
				<small><?php echo esc_html( $msst_fmt( $msst_row['time'] ) ); ?></small>
			</div>
		<?php endforeach; ?>
		<?php if ( $msst_errors ) : ?>
			<a class="msst-btn msst-btn-outline" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'clear_log', array( 'log' => 'errors' ) ) ); ?>"><?php esc_html_e( 'Clear', 'mudassar-snippet-studio' ); ?></a>
		<?php endif; ?>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Audit Log', 'mudassar-snippet-studio' ); ?></h2>
		<?php if ( ! $msst_audit ) : ?>
			<p class="msst-desc"><?php esc_html_e( 'Nothing recorded yet.', 'mudassar-snippet-studio' ); ?></p>
		<?php else : ?>
			<table class="msst-table">
				<?php foreach ( array_slice( $msst_audit, 0, 50 ) as $msst_row ) : ?>
					<tr><td><?php echo esc_html( $msst_fmt( $msst_row['time'] ) ); ?></td><td><?php echo esc_html( $msst_row['user'] ); ?></td><td><?php echo esc_html( $msst_row['msg'] ); ?></td></tr>
				<?php endforeach; ?>
			</table>
			<p><a class="msst-btn msst-btn-outline" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'clear_log', array( 'log' => 'audit' ) ) ); ?>"><?php esc_html_e( 'Clear', 'mudassar-snippet-studio' ); ?></a></p>
		<?php endif; ?>
	</div>
</div>
