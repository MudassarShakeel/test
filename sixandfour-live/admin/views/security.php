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

<?php
$uid      = get_current_user_id();
$keys     = SF_Passwordless::passkeys( $uid );
$only_on  = (bool) SF_Options::get( 'passwordless_only', 0 );
$magic_on = (bool) SF_Options::get( 'magic_link', 1 );
?>
<h2 class="sf-sec">Password-free sign-in for administrators</h2>
<p class="sf-sub">Sign in with your fingerprint, face or screen lock (a passkey), or with a one-time link sent to your email. Passkeys need an HTTPS address.</p>
<div class="sf-group">
	<?php foreach ( $keys as $id => $k ) : ?>
		<div class="sf-row">
			<div class="sf-l"><b><?php echo esc_html( isset( $k['name'] ) && '' !== $k['name'] ? $k['name'] : 'Passkey' ); ?></b><span>Added <?php echo esc_html( ! empty( $k['created'] ) ? wp_date( 'j M Y', (int) $k['created'] ) : '' ); ?></span></div>
			<button type="button" class="sf-btn sf-sm sf-pk-del" data-id="<?php echo esc_attr( $id ); ?>">Remove</button>
		</div>
	<?php endforeach; ?>
	<div class="sf-row sf-block">
		<label for="sf-pk-name"><b>Add a passkey to this device</b></label>
		<div class="sf-pickrow">
			<input type="text" class="sf-field" id="sf-pk-name" placeholder="Name, for example My phone" maxlength="40">
			<button type="button" class="sf-btn sf-primary" id="sf-pk-add">Add passkey</button>
		</div>
		<p class="sf-sub" id="sf-pk-msg"></p>
	</div>
</div>

<?php SF_Admin::form_open( 'save_signin' ); ?>
<div class="sf-group">
	<?php
	SF_Admin::switch_row( 'magic_link', $magic_on, 'Email sign-in link', 'Sends a one-time link, valid for 10 minutes, to the administrator email address.' );
	SF_Admin::switch_row( 'passwordless_only', $only_on, 'Turn passwords off for administrators', $keys ? 'Administrators can only use a passkey or an email link. Other users keep their passwords.' : 'Add a passkey first. This stays locked until you have one.' );
	?>
</div>
<div class="sf-actions"><button class="sf-btn sf-primary" type="submit">Save sign-in settings</button></div>
</form>
<p class="sf-sub">Locked out? Add <code>define( 'SF_ALLOW_PASSWORD', true );</code> to wp-config.php to allow passwords again.</p>

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
