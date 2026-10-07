<?php
/**
 * Settings, safety and help.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

global $wp_version;
$msst_settings = MSST_Settings::get();
$msst_safe_url = add_query_arg( 'msst_safe_mode', MSST_Security::ensure_secret(), home_url( '/' ) );
$msst_test_on  = MSST_Security::test_mode();
$msst_counts   = MSST_Snippets::counts();
$msst_info     = sprintf(
	'Plugin %s · WordPress %s · PHP %s · Multisite: %s · Snippets: %d (%d on)',
	MSST_VERSION,
	$wp_version,
	PHP_VERSION,
	is_multisite() ? 'yes' : 'no',
	$msst_counts['all'],
	$msst_counts['active']
);
?>
<div class="msst-grid msst-grid-2">
	<form class="msst-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h2><?php esc_html_e( 'General', 'scripts-manager-by-mudassar' ); ?></h2>
		<input type="hidden" name="action" value="msst_save_settings">
		<?php wp_nonce_field( 'msst_save_settings' ); ?>
		<p><label class="msst-check"><input type="checkbox" name="msst_php_enabled" value="1" <?php checked( $msst_settings['php_enabled'] ); ?>> <span><b><?php esc_html_e( 'Allow PHP snippets', 'scripts-manager-by-mudassar' ); ?></b></span></label></p>
		<p><label class="msst-check"><input type="checkbox" name="msst_auto_disable" value="1" <?php checked( $msst_settings['auto_disable'] ); ?>> <span><b><?php esc_html_e( 'Turn a snippet OFF if it breaks the site', 'scripts-manager-by-mudassar' ); ?></b></span></label></p>
		<p><label class="msst-check"><input type="checkbox" name="msst_delete_on_uninstall" value="1" <?php checked( $msst_settings['delete_on_uninstall'] ); ?>> <span><b><?php esc_html_e( 'Delete all data when the plugin is deleted', 'scripts-manager-by-mudassar' ); ?></b></span></label></p>
		<button type="submit" class="msst-btn"><?php esc_html_e( 'Save settings', 'scripts-manager-by-mudassar' ); ?></button>
		<hr class="msst-hr">
		<h2><?php esc_html_e( 'Test view (only you)', 'scripts-manager-by-mudassar' ); ?></h2>
		<p>
			<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::action_url( 'test_mode', array( 'to' => $msst_test_on ? '0' : '1' ) ) ); ?>"><?php echo $msst_test_on ? esc_html__( 'Turn off test view', 'scripts-manager-by-mudassar' ) : esc_html__( 'Turn on test view', 'scripts-manager-by-mudassar' ); ?></a>
		</p>
		<div class="msst-chk <?php echo MSST_Security::can_edit_php() ? '' : 'msst-chk-off'; ?>"><i><?php echo MSST_Security::can_edit_php() ? '✓' : '!'; ?></i> <?php echo MSST_Security::can_edit_php() ? esc_html__( 'You can add PHP snippets', 'scripts-manager-by-mudassar' ) : esc_html__( 'PHP is locked for your account on this site', 'scripts-manager-by-mudassar' ); ?></div>
	</form>
	<div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Safe Mode link', 'scripts-manager-by-mudassar' ); ?></h2>
			<div class="msst-note msst-note-warning"><code class="msst-copy"><?php echo esc_html( $msst_safe_url ); ?></code></div>
			<a class="msst-btn msst-btn-outline" data-confirm="1" href="<?php echo esc_url( MSST_Admin::action_url( 'regen_secret' ) ); ?>"><?php esc_html_e( 'Make a new link', 'scripts-manager-by-mudassar' ); ?></a>
		</div>
		<div class="msst-card msst-center">
			<div class="msst-logo msst-logo-center" aria-hidden="true">&lt;/&gt;</div>
			<h2 class="msst-h-center"><?php esc_html_e( 'Scripts Manager By Mudassar', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc">
				<?php
				printf(
					/* translators: 1: version, 2: author */
					esc_html__( 'Version %1$s · Built by %2$s', 'scripts-manager-by-mudassar' ),
					esc_html( MSST_VERSION ),
					'<strong>' . esc_html( MSST_Brand::AUTHOR ) . '</strong>'
				);
				?>
			</p>
			<?php echo MSST_Brand::link( 'contact', 'settings-contact-support', __( 'Contact Support', 'scripts-manager-by-mudassar' ), 'msst-btn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo MSST_Brand::link( 'website', 'settings-visit-website', __( 'Visit mudassar.work', 'scripts-manager-by-mudassar' ), 'msst-btn msst-btn-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="msst-label msst-left msst-mt"><?php esc_html_e( 'System info', 'scripts-manager-by-mudassar' ); ?></p>
			<textarea class="msst-code msst-left" rows="3" readonly onclick="this.select()"><?php echo esc_textarea( $msst_info ); ?></textarea>
		</div>
	</div>
</div>
