<?php
/**
 * Mobile home screen: the console on your home page.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$page = (int) SF_Options::get( 'home_page', 0 );
$live = $page && 'page' === get_post_type( $page ) && 'trash' !== get_post_status( $page );
$is_home = $live && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page;
?>
<p class="sf-lede">Run everything from your phone. The console lives on your home page. Visitors see only a sign-in card, and only administrators see the controls.</p>

<div class="sf-group">
	<div class="sf-row">
		<div class="sf-l"><b>Status</b><span><?php echo $is_home ? 'Your home page shows the console.' : ( $live ? 'The console page exists but is not your home page.' : 'Not set up yet.' ); ?></span></div>
		<span class="sf-pill <?php echo $is_home ? 'sf-ok' : 'sf-warn'; ?>"><?php echo $is_home ? 'On' : 'Off'; ?></span>
	</div>
	<div class="sf-row">
		<div class="sf-l"><b>Shortcode</b><span>Put it on any page.</span></div>
		<code>[sixandfour_admin]</code>
	</div>
</div>

<div class="sf-actions">
	<?php SF_Admin::form_open( 'create_home' ); ?>
		<button class="sf-btn sf-primary" type="submit"><?php echo $is_home ? 'Check the page again' : 'Create the mobile home page'; ?></button>
	</form>
	<?php if ( $is_home && get_option( 'sf_home_prev' ) ) : ?>
		<?php SF_Admin::form_open( 'restore_home' ); ?>
			<button class="sf-btn" type="submit">Restore my old home page</button>
		</form>
	<?php endif; ?>
	<?php if ( $live ) : ?>
		<a class="sf-btn" href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener">Open on this device</a>
	<?php endif; ?>
</div>
<p class="sf-sub">Tip: on your phone, open the page and choose Add to Home Screen. It opens like an app.</p>
