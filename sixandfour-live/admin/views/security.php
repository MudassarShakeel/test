<?php
/**
 * Security screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sec    = SF_Options::get( 'security', array() );
$sec    = array_merge( SF_Security::defaults(), is_array( $sec ) ? $sec : array() );
$checks = SF_Security::checks();
$map    = array( 'ok' => array( 'sf-ok', 'OK' ), 'warn' => array( 'sf-warn', 'Check' ), 'bad' => array( 'sf-bad', 'Fix' ) );
?>
<p class="sf-lede">A health check for this site, and hardening you can switch on or off. All of it is on by default.</p>

<h2 class="sf-sec">Health check</h2>
<div class="sf-group">
	<?php foreach ( $checks as $c ) : ?>
		<div class="sf-row">
			<div class="sf-l"><b><?php echo esc_html( $c[0] ); ?></b><span><?php echo esc_html( $c[2] ); ?></span></div>
			<span class="sf-pill <?php echo esc_attr( $map[ $c[1] ][0] ); ?>"><?php echo esc_html( $map[ $c[1] ][1] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>

<?php SF_Admin::form_open( 'save_security' ); ?>
<h2 class="sf-sec">Hardening</h2>
<div class="sf-group">
	<?php
	SF_Admin::switch_row( 'sec_login_limit', $sec['login_limit'], 'Limit log-in tries', 'Five wrong passwords lock that address out for 15 minutes.' );
	SF_Admin::switch_row( 'sec_block_editor', $sec['block_editor'], 'Block the file editor', 'Nobody can change plugin or theme code from inside wp-admin.' );
	SF_Admin::switch_row( 'sec_xmlrpc_off', $sec['xmlrpc_off'], 'Turn off XML-RPC', 'An old remote-access door that attackers use to guess passwords. This plugin does not need it.' );
	SF_Admin::switch_row( 'sec_hide_version', $sec['hide_version'], 'Hide the WordPress version', 'Removes the version from page source.' );
	SF_Admin::switch_row( 'sec_user_enum_off', $sec['user_enum_off'], 'Hide user names from visitors', 'Blocks ?author=1 and the public users list.' );
	SF_Admin::switch_row( 'sec_headers', $sec['headers'], 'Send safety headers', 'Stops other sites from framing yours and from guessing file types.' );
	?>
</div>
<div class="sf-actions"><button class="sf-btn sf-primary" type="submit">Save changes</button></div>
</form>

<h2 class="sf-sec">What else to do</h2>
<div class="sf-tile">
	<ol class="sf-steps">
		<li>Use a long, unique password for every administrator, and turn on two-step log-in with a plugin such as Two Factor or WP 2FA.</li>
		<li>In Hostinger hPanel, turn on the free SSL certificate, force HTTPS, and keep automatic backups on.</li>
		<li>Keep WordPress, plugins and PHP up to date. Delete plugins and themes you do not use.</li>
		<li>Make the Facebook token with the least access it needs: pages_manage_posts and pages_read_engagement only.</li>
		<li>If you change the salts in wp-config.php, saved secrets can no longer be read. Enter the token and API key again in Setup.</li>
	</ol>
</div>
