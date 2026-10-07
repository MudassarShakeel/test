<?php
/**
 * Problems & Activity tab (includes the error log file).
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

$msst_errors = MSST_Logger::get( 'errors' );
$msst_audit  = MSST_Logger::get( 'audit' );
$msst_file   = MSST_Logger::file_info();
$msst_tail   = MSST_Logger::tail( 60 );
$msst_fmt    = static function ( $ts ) {
	return wp_date( get_option( 'date_format' ) . ' H:i', (int) $ts );
};

MSST_Admin::intro( __( 'Problems & activity', 'scripts-manager-by-mudassar' ), __( 'If a snippet breaks, it is switched OFF for you and shown here. Below you can also see who changed what, and download the error log file.', 'scripts-manager-by-mudassar' ) );
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Problems', 'scripts-manager-by-mudassar' ); ?></h2>
		<?php if ( ! $msst_errors ) : ?>
			<div class="msst-note msst-note-success"><?php esc_html_e( 'Everything is working ✓', 'scripts-manager-by-mudassar' ); ?></div>
		<?php endif; ?>
		<?php foreach ( array_slice( $msst_errors, 0, 30 ) as $msst_row ) : ?>
			<div class="msst-note msst-note-<?php echo 'warning' === $msst_row['level'] ? 'warning' : 'error'; ?>">
				<strong><?php echo esc_html( $msst_row['title'] ); ?></strong> – <?php echo esc_html( $msst_row['msg'] ); ?><br>
				<small><?php echo esc_html( $msst_fmt( $msst_row['time'] ) ); ?></small>
				<?php if ( ! empty( $msst_row['id'] ) && MSST_Snippets::get( $msst_row['id'] ) ) : ?>
					· <a href="<?php echo esc_url( MSST_Admin::url( 'edit', array( 'snippet' => (int) $msst_row['id'] ) ) ); ?>"><?php esc_html_e( 'Fix it', 'scripts-manager-by-mudassar' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<?php if ( $msst_errors ) : ?>
			<a class="msst-btn msst-btn-outline msst-btn-sm" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'clear_log', array( 'log' => 'errors' ) ) ); ?>"><?php esc_html_e( 'Clear this list', 'scripts-manager-by-mudassar' ); ?></a>
		<?php endif; ?>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Who changed what', 'scripts-manager-by-mudassar' ); ?></h2>
		<?php if ( ! $msst_audit ) : ?>
			<p class="msst-desc"><?php esc_html_e( 'Nothing recorded yet.', 'scripts-manager-by-mudassar' ); ?></p>
		<?php else : ?>
			<table class="msst-table">
				<?php foreach ( array_slice( $msst_audit, 0, 30 ) as $msst_row ) : ?>
					<tr><td><?php echo esc_html( $msst_fmt( $msst_row['time'] ) ); ?></td><td><?php echo esc_html( $msst_row['user'] ); ?></td><td><?php echo esc_html( $msst_row['msg'] ); ?></td></tr>
				<?php endforeach; ?>
			</table>
			<p><a class="msst-btn msst-btn-outline msst-btn-sm" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'clear_log', array( 'log' => 'audit' ) ) ); ?>"><?php esc_html_e( 'Clear this list', 'scripts-manager-by-mudassar' ); ?></a></p>
		<?php endif; ?>
	</div>
</div>

<div class="msst-card" id="msst-error-file">
	<h2><?php esc_html_e( 'Error log file', 'scripts-manager-by-mudassar' ); ?></h2>
	<p class="msst-desc"><?php esc_html_e( 'A real text file on your server. Every snippet problem is written here, even if you are not looking at this page. Send it to us if you need help.', 'scripts-manager-by-mudassar' ); ?></p>
	<?php if ( ! $msst_file['writable'] ) : ?>
		<div class="msst-note msst-note-warning"><?php esc_html_e( 'The uploads folder is not writable, so the log file cannot be created. Problems are still shown in the list above.', 'scripts-manager-by-mudassar' ); ?></div>
	<?php endif; ?>
	<p class="msst-hint">
		<?php
		echo esc_html(
			$msst_file['exists']
				? sprintf(
					/* translators: 1: file name, 2: size */
					__( 'File: uploads/scripts-manager-logs/%1$s · Size: %2$s', 'scripts-manager-by-mudassar' ),
					$msst_file['name'],
					size_format( $msst_file['size'] )
				)
				: __( 'No log file yet – it is created the first time a problem happens.', 'scripts-manager-by-mudassar' )
		);
		?>
	</p>
	<?php if ( $msst_file['exists'] && $msst_file['size'] > 0 ) : ?>
		<pre class="msst-code msst-logfile"><?php echo esc_html( implode( "\n", $msst_tail ) ); ?></pre>
		<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::action_url( 'download_log' ) ); ?>"><?php esc_html_e( 'Download log file', 'scripts-manager-by-mudassar' ); ?></a>
		<a class="msst-btn msst-btn-outline" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'clear_log', array( 'log' => 'file' ) ) ); ?>"><?php esc_html_e( 'Empty the file', 'scripts-manager-by-mudassar' ); ?></a>
	<?php else : ?>
		<div class="msst-note msst-note-success"><?php esc_html_e( 'The log file is empty. Good news!', 'scripts-manager-by-mudassar' ); ?></div>
	<?php endif; ?>
</div>
