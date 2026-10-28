<?php
/**
 * Players screen: your own photos for the player cards.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$photos = SF_Options::get( 'player_photos', array() );
$names  = SF_Players::known();
// Players that already have a photo stay on the list.
foreach ( $photos as $k => $att ) {
	$have = false;
	foreach ( $names as $n ) {
		if ( SF_Players::key( $n ) === $k ) {
			$have = true;
		}
	}
	if ( ! $have ) {
		$names[] = ucwords( $k );
	}
}
SF_Admin::form_open( 'save_players' );
?>
<p class="sf-lede">Add a photo for a player and it shows on that player's cards: centuries, five-wicket hauls, top performer and the match summary. Players without a photo show their initials.</p>
<div class="sf-group sf-teams">
	<?php foreach ( $names as $name ) : ?>
		<?php
		$key   = SF_Players::key( $name );
		$att   = isset( $photos[ $key ] ) ? (int) $photos[ $key ] : 0;
		$thumb = $att ? wp_get_attachment_image_url( $att, 'thumbnail' ) : '';
		?>
		<div class="sf-row sf-team">
			<div class="sf-pic">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="sf-logo-prev">
				<?php else : ?>
					<span class="sf-badge"><?php echo esc_html( SF_Teams::initials( $name ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="sf-l"><b><?php echo esc_html( $name ); ?></b><span><?php echo $att ? 'Your photo' : 'Initials'; ?></span></div>
			<input type="hidden" name="pl_name[]" value="<?php echo esc_attr( $name ); ?>">
			<input type="hidden" name="pl_id[]" value="<?php echo (int) $att; ?>" class="sf-logo-id">
			<div class="sf-inline">
				<button type="button" class="sf-btn sf-sm sf-pick" data-title="Choose a photo for <?php echo esc_attr( $name ); ?>">Choose photo</button>
				<button type="button" class="sf-btn sf-sm sf-clear">Remove</button>
			</div>
		</div>
	<?php endforeach; ?>
	<?php for ( $i = 0; $i < 3; $i++ ) : ?>
		<div class="sf-row sf-team">
			<div class="sf-pic"><span class="sf-badge">+</span></div>
			<div class="sf-l"><label class="screen-reader-text" for="pl_new_<?php echo (int) $i; ?>">Player name</label><input class="sf-field" id="pl_new_<?php echo (int) $i; ?>" type="text" name="pl_name[]" placeholder="Add a player by name, for example Oliver Hartley" maxlength="60"><span>Type the name as it appears on the cards</span></div>
			<input type="hidden" name="pl_id[]" value="" class="sf-logo-id">
			<div class="sf-inline">
				<button type="button" class="sf-btn sf-sm sf-pick" data-title="Choose a photo">Choose photo</button>
			</div>
		</div>
	<?php endfor; ?>
</div>
<p class="sf-sub">Only use photos you took or have permission to use. Player photos belong to photographers and agencies, so the plugin never downloads them for you. A clear head-and-shoulders photo works best, in portrait or square.</p>
<div class="sf-actions"><button class="sf-btn sf-primary" type="submit">Save player photos</button></div>
</form>
