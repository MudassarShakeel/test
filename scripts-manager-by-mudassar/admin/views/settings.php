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

MSST_Admin::intro( __( 'Settings & safety', 'scripts-manager-by-mudassar' ), __( 'Simple switches, plus your emergency button.', 'scripts-manager-by-mudassar' ) );
?>
<div class="msst-grid msst-grid-2">
	<form class="msst-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h2><?php esc_html_e( 'Switches', 'scripts-manager-by-mudassar' ); ?></h2>
		<input type="hidden" name="action" value="msst_save_settings">
		<?php wp_nonce_field( 'msst_save_settings' ); ?>
		<p><label class="msst-check"><input type="checkbox" name="msst_php_enabled" value="1" <?php checked( $msst_settings['php_enabled'] ); ?>> <span><b><?php esc_html_e( 'Allow PHP snippets', 'scripts-manager-by-mudassar' ); ?></b><br><small><?php esc_html_e( 'Turn this off to stop all PHP code at once.', 'scripts-manager-by-mudassar' ); ?></small></span></label></p>
		<p><label class="msst-check"><input type="checkbox" name="msst_auto_disable" value="1" <?php checked( $msst_settings['auto_disable'] ); ?>> <span><b><?php esc_html_e( 'Turn a snippet OFF if it breaks the site', 'scripts-manager-by-mudassar' ); ?></b><br><small><?php esc_html_e( 'Recommended. The problem is written to the error log.', 'scripts-manager-by-mudassar' ); ?></small></span></label></p>
		<p><label class="msst-check"><input type="checkbox" name="msst_delete_on_uninstall" value="1" <?php checked( $msst_settings['delete_on_uninstall'] ); ?>> <span><b><?php esc_html_e( 'Delete everything if I delete the plugin', 'scripts-manager-by-mudassar' ); ?></b><br><small><?php esc_html_e( 'Snippets, settings and the log file.', 'scripts-manager-by-mudassar' ); ?></small></span></label></p>
		<button type="submit" class="msst-btn"><?php esc_html_e( 'Save settings', 'scripts-manager-by-mudassar' ); ?></button>
		<hr class="msst-hr">
		<h2><?php esc_html_e( 'Try without snippets (only you)', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'See how the site looks without any snippets. Visitors are not affected.', 'scripts-manager-by-mudassar' ); ?></p>
		<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::action_url( 'test_mode', array( 'to' => $msst_test_on ? '0' : '1' ) ) ); ?>"><?php echo $msst_test_on ? esc_html__( 'Turn off test view', 'scripts-manager-by-mudassar' ) : esc_html__( 'Turn on test view', 'scripts-manager-by-mudassar' ); ?></a>
	</form>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Emergency button', 'scripts-manager-by-mudassar' ); ?></h2>
		<div class="msst-note msst-note-warning">
			<?php echo wp_kses( __( '<b>Safe Mode link</b> – opens your site with every snippet OFF. Save it somewhere private. Anyone with this link can switch snippets off for themselves.', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?><br>
			<code class="msst-copy"><?php echo esc_html( $msst_safe_url ); ?></code>
		</div>
		<a class="msst-btn msst-btn-outline" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'regen_secret' ) ); ?>"><?php esc_html_e( 'Make a new link', 'scripts-manager-by-mudassar' ); ?></a>
		<p class="msst-hint"><?php esc_html_e( 'Locked out of the dashboard? Add this line to wp-config.php to turn everything off:', 'scripts-manager-by-mudassar' ); ?> <code>define( 'MSST_DISABLE_SNIPPETS', true );</code></p>
		<hr class="msst-hr">
		<h2><?php esc_html_e( 'Protection', 'scripts-manager-by-mudassar' ); ?></h2>
		<div class="msst-chk"><i>✓</i> <?php esc_html_e( 'Only administrators can use this plugin', 'scripts-manager-by-mudassar' ); ?></div>
		<div class="msst-chk"><i>✓</i> <?php esc_html_e( 'Code changed from outside is blocked', 'scripts-manager-by-mudassar' ); ?></div>
		<div class="msst-chk"><i>✓</i> <?php esc_html_e( 'Every button is protected against fake clicks', 'scripts-manager-by-mudassar' ); ?></div>
		<div class="msst-chk <?php echo MSST_Security::can_edit_php() ? '' : 'msst-chk-off'; ?>"><i><?php echo MSST_Security::can_edit_php() ? '✓' : '!'; ?></i> <?php echo MSST_Security::can_edit_php() ? esc_html__( 'You can add PHP snippets', 'scripts-manager-by-mudassar' ) : esc_html__( 'PHP is locked for your account on this site', 'scripts-manager-by-mudassar' ); ?></div>
	</div>
</div>
