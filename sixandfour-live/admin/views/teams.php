<?php
/**
 * Teams screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$known = get_option( 'sf_known_teams', array() );
$known = is_array( $known ) ? $known : array();
$names = array_values( array_unique( array_merge( SF_Teams::flag_names(), $known ) ) );
$logos = SF_Options::get( 'team_logos', array() );
SF_Admin::form_open( 'save_teams' );
?>
<p class="sf-lede">National teams show a country flag. Any team can use your own picture instead. Teams without either show an initials badge.</p>
<div class="sf-group sf-teams">
	<?php foreach ( $names as $i => $name ) : ?>
		<?php
		$key   = SF_Teams::key_for( $name );
		$t     = SF_Teams::resolve( $name );
		$att   = isset( $logos[ $key ] ) ? (int) $logos[ $key ] : 0;
		$thumb = $att ? wp_get_attachment_image_url( $att, 'thumbnail' ) : '';
		?>
		<div class="sf-row sf-team">
			<div class="sf-pic">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="sf-logo-prev">
				<?php elseif ( 'flag' === $t['kind'] ) : ?>
					<img src="<?php echo esc_url( SF_URL . 'assets/flags/' . basename( $t['path'] ) ); ?>" alt="" class="sf-flag-prev">
				<?php else : ?>
					<span class="sf-badge"><?php echo esc_html( $t['initials'] ); ?></span>
				<?php endif; ?>
			</div>
			<div class="sf-l"><b><?php echo esc_html( $name ); ?></b><span><?php echo $att ? 'Your picture' : ( 'flag' === $t['kind'] ? 'Country flag' : 'Initials badge' ); ?></span></div>
			<input type="hidden" name="logo_key[]" value="<?php echo esc_attr( $key ); ?>">
			<input type="hidden" name="logo_id[]" value="<?php echo (int) $att; ?>" class="sf-logo-id">
			<div class="sf-inline">
				<button type="button" class="sf-btn sf-sm sf-pick" data-title="Choose a picture for <?php echo esc_attr( $name ); ?>">Choose picture</button>
				<button type="button" class="sf-btn sf-sm sf-clear">Remove</button>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<p class="sf-sub">Use a picture you own or have permission to use. Official board and league logos are trademarks and are not included in this plugin.</p>
<div class="sf-actions"><button class="sf-btn sf-primary" type="submit">Save team pictures</button></div>
</form>
