<?php
/**
 * Start Here tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

MSST_Admin::intro( __( 'Welcome! Pick what you want to do.', 'scripts-manager-by-mudassar' ), __( 'You do not need to know code. Each card below takes you to a simple step-by-step page.', 'scripts-manager-by-mudassar' ) );
$msst_safe_url = add_query_arg( 'msst_safe_mode', MSST_Security::ensure_secret(), home_url( '/' ) );
?>
<div class="msst-grid msst-grid-3">
	<div class="msst-card msst-big">
		<div class="msst-ic" aria-hidden="true">📈</div>
		<h2><?php esc_html_e( 'Add Google Analytics or a pixel', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Paste your ID (like G-ABC123) and we do the rest. No code needed.', 'scripts-manager-by-mudassar' ); ?></p>
		<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'headers' ) ); ?>"><?php esc_html_e( 'Set it up', 'scripts-manager-by-mudassar' ); ?></a>
	</div>
	<div class="msst-card msst-big">
		<div class="msst-ic" aria-hidden="true">✍️</div>
		<h2><?php esc_html_e( 'Add my own code', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'A guided 4-step page: name it, paste the code, choose where, turn it on.', 'scripts-manager-by-mudassar' ); ?></p>
		<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'edit' ) ); ?>"><?php esc_html_e( 'Add a snippet', 'scripts-manager-by-mudassar' ); ?></a>
	</div>
	<div class="msst-card msst-big">
		<div class="msst-ic" aria-hidden="true">📦</div>
		<h2><?php esc_html_e( 'Use a ready-made snippet', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Common fixes like hiding the admin bar or smooth scrolling. One click.', 'scripts-manager-by-mudassar' ); ?></p>
		<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'library' ) ); ?>"><?php esc_html_e( 'Browse list', 'scripts-manager-by-mudassar' ); ?></a>
	</div>
</div>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'How it works', 'scripts-manager-by-mudassar' ); ?></h2>
		<div class="msst-chk"><i>1</i> <?php esc_html_e( 'Choose what to add', 'scripts-manager-by-mudassar' ); ?></div>
		<div class="msst-chk"><i>2</i> <?php esc_html_e( 'Choose where it appears on your site', 'scripts-manager-by-mudassar' ); ?></div>
		<div class="msst-chk"><i>3</i> <?php esc_html_e( 'Turn it ON and look at your site', 'scripts-manager-by-mudassar' ); ?></div>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Something went wrong?', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Do not panic. Your site can be put back in seconds.', 'scripts-manager-by-mudassar' ); ?></p>
		<div class="msst-note msst-note-warning"><?php esc_html_e( 'Emergency button: opens your site with all snippets switched off, so you can fix things.', 'scripts-manager-by-mudassar' ); ?></div>
		<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( $msst_safe_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Safe Mode link', 'scripts-manager-by-mudassar' ); ?></a>
		<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::url( 'logs' ) ); ?>"><?php esc_html_e( 'See problems', 'scripts-manager-by-mudassar' ); ?></a>
	</div>
</div>
