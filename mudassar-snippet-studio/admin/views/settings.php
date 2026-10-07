<?php
/**
 * Settings & Security tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

$msst_settings = MSST_Settings::get();
$msst_secret   = MSST_Security::ensure_secret();
$msst_test_on  = MSST_Security::test_mode();
$msst_safe_url = add_query_arg( 'msst_safe_mode', $msst_secret, home_url( '/' ) );
?>
<div class="msst-grid msst-grid-2">
	<form class="msst-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h2><?php esc_html_e( 'General', 'mudassar-snippet-studio' ); ?></h2>
		<input type="hidden" name="action" value="msst_save_settings">
		<?php wp_nonce_field( 'msst_save_settings' ); ?>
		<p><label><input type="checkbox" name="msst_php_enabled" value="1" <?php checked( $msst_settings['php_enabled'] ); ?>> <?php esc_html_e( 'Enable PHP snippets', 'mudassar-snippet-studio' ); ?></label></p>
		<p><label><input type="checkbox" name="msst_auto_disable" value="1" <?php checked( $msst_settings['auto_disable'] ); ?>> <?php esc_html_e( 'Auto-disable a snippet when it causes an error', 'mudassar-snippet-studio' ); ?></label></p>
		<p><label><input type="checkbox" name="msst_delete_on_uninstall" value="1" <?php checked( $msst_settings['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Delete all data when the plugin is deleted', 'mudassar-snippet-studio' ); ?></label></p>
		<button type="submit" class="msst-btn"><?php esc_html_e( 'Save Settings', 'mudassar-snippet-studio' ); ?></button>
		<hr class="msst-hr">
		<p>
			<strong><?php esc_html_e( 'Test mode (just for you)', 'mudassar-snippet-studio' ); ?></strong><br>
			<span class="msst-desc"><?php esc_html_e( 'No snippets run for your account while this is on. Visitors are not affected.', 'mudassar-snippet-studio' ); ?></span><br>
			<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::action_url( 'test_mode', array( 'to' => $msst_test_on ? '0' : '1' ) ) ); ?>"><?php echo $msst_test_on ? esc_html__( 'Turn off', 'mudassar-snippet-studio' ) : esc_html__( 'Turn on', 'mudassar-snippet-studio' ); ?></a>
		</p>
	</form>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Security', 'mudassar-snippet-studio' ); ?></h2>
		<div class="msst-note"><?php esc_html_e( '✔ Every action needs a nonce and the msst_manage_snippets capability.', 'mudassar-snippet-studio' ); ?></div>
		<div class="msst-note"><?php esc_html_e( '✔ Snippets are signed. Code changed outside the plugin is blocked.', 'mudassar-snippet-studio' ); ?></div>
		<div class="msst-note <?php echo MSST_Security::can_edit_php() ? '' : 'msst-note-warning'; ?>">
			<?php echo MSST_Security::can_edit_php() ? esc_html__( '✔ You can create and edit PHP snippets.', 'mudassar-snippet-studio' ) : esc_html__( 'PHP editing is locked for your account on this site.', 'mudassar-snippet-studio' ); ?>
		</div>
		<div class="msst-note msst-note-warning">
			<?php esc_html_e( 'Safe mode URL – opens the site with all snippets off. Keep it secret:', 'mudassar-snippet-studio' ); ?><br>
			<code class="msst-copy"><?php echo esc_html( $msst_safe_url ); ?></code><br><br>
			<a class="msst-btn msst-btn-outline" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'regen_secret' ) ); ?>"><?php esc_html_e( 'Regenerate', 'mudassar-snippet-studio' ); ?></a>
		</div>
		<p class="msst-desc"><?php esc_html_e( 'You can also add define( \'MSST_DISABLE_SNIPPETS\', true ); to wp-config.php to turn every snippet off.', 'mudassar-snippet-studio' ); ?></p>
	</div>
</div>
