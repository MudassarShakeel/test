<?php
/**
 * Settings & Safety tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

$msst_settings = MSST_Settings::get();
$msst_secret   = MSST_Security::ensure_secret();
$msst_test_on  = MSST_Security::test_mode();
$msst_safe_url = add_query_arg( 'msst_safe_mode', $msst_secret, home_url( '/' ) );

?>
<div class="msst-grid msst-grid-2">
	<form class="msst-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h2><?php esc_html_e( 'Switches', 'scripts-manager-by-mudassar' ); ?></h2>
		<input type="hidden" name="action" value="msst_save_settings">
		<?php wp_nonce_field( 'msst_save_settings' ); ?>
		<p><label class="msst-check"><input type="checkbox" name="msst_php_enabled" value="1" <?php checked( $msst_settings['php_enabled'] ); ?>> <span><b><?php esc_html_e( 'Allow PHP snippets', 'scripts-manager-by-mudassar' ); ?></b></span></label></p>
		<p><label class="msst-check"><input type="checkbox" name="msst_auto_disable" value="1" <?php checked( $msst_settings['auto_disable'] ); ?>> <span><b><?php esc_html_e( 'Turn a snippet OFF if it breaks the site', 'scripts-manager-by-mudassar' ); ?></b></span></label></p>
		<p><label class="msst-check"><input type="checkbox" name="msst_delete_on_uninstall" value="1" <?php checked( $msst_settings['delete_on_uninstall'] ); ?>> <span><b><?php esc_html_e( 'Delete everything if I delete the plugin', 'scripts-manager-by-mudassar' ); ?></b></span></label></p>
		<button type="submit" class="msst-btn"><?php esc_html_e( 'Save settings', 'scripts-manager-by-mudassar' ); ?></button>
		<hr class="msst-hr">
		<h2><?php esc_html_e( 'Try without snippets (only you)', 'scripts-manager-by-mudassar' ); ?></h2>
		<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::action_url( 'test_mode', array( 'to' => $msst_test_on ? '0' : '1' ) ) ); ?>"><?php echo $msst_test_on ? esc_html__( 'Turn off test view', 'scripts-manager-by-mudassar' ) : esc_html__( 'Turn on test view', 'scripts-manager-by-mudassar' ); ?></a>
	</form>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Emergency button', 'scripts-manager-by-mudassar' ); ?></h2>
		<div class="msst-note msst-note-warning">
			<strong><?php esc_html_e( 'Safe Mode link', 'scripts-manager-by-mudassar' ); ?></strong><br>
			<code class="msst-copy"><?php echo esc_html( $msst_safe_url ); ?></code>
		</div>
		<a class="msst-btn msst-btn-outline" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'regen_secret' ) ); ?>"><?php esc_html_e( 'Make a new link', 'scripts-manager-by-mudassar' ); ?></a>
		<hr class="msst-hr">
				<div class="msst-chk <?php echo MSST_Security::can_edit_php() ? '' : 'msst-chk-off'; ?>"><i><?php echo MSST_Security::can_edit_php() ? '✓' : '!'; ?></i> <?php echo MSST_Security::can_edit_php() ? esc_html__( 'You can add PHP snippets', 'scripts-manager-by-mudassar' ) : esc_html__( 'PHP is locked for your account on this site', 'scripts-manager-by-mudassar' ); ?></div>
	</div>
</div>
