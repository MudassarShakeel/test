<?php
/**
 * Post queue screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pending  = SF_DB::posts_by_status( 'pending', 30 );
$failed   = SF_DB::posts_by_status( 'failed', 10 );
$approved = SF_DB::posts_by_status( 'approved', 10 );
$mode     = SF_Options::get( 'mode', 'auto' );

$render = function ( $p, $buttons ) {
	$img = SF_Poster::image_url( $p );
	?>
	<div class="sf-item">
		<div class="sf-thumb"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php else : ?><div class="sf-textonly">Text post</div><?php endif; ?></div>
		<div class="sf-body">
			<div class="sf-meta"><span class="sf-pill sf-warn"><?php echo esc_html( ucfirst( $p['status'] ) ); ?></span> <?php echo esc_html( ucfirst( $p['type'] ) ); ?> <span class="sf-muted"><?php echo esc_html( wp_date( 'j M, H:i', strtotime( $p['created_at'] . ' UTC' ) ) ); ?></span></div>
			<?php if ( $p['error'] ) : ?><p class="sf-err"><?php echo esc_html( $p['error'] ); ?></p><?php endif; ?>
			<?php $why = SF_Poster::wait_reason( $p ); if ( '' !== $why ) : ?><p class="sf-sub"><b>Why it has not posted:</b> <?php echo esc_html( $why ); ?></p><?php endif; ?>
			<?php SF_Admin::form_open( 'queue_action' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
				<label class="sf-field-l" for="cap<?php echo (int) $p['id']; ?>">Caption</label>
				<textarea class="sf-field" id="cap<?php echo (int) $p['id']; ?>" name="caption" rows="5"><?php echo esc_textarea( $p['caption'] ); ?></textarea>
				<div class="sf-actions">
					<?php if ( 'approve' === $buttons ) : ?>
						<button class="sf-btn sf-primary" type="submit" name="do" value="approve">Approve and post</button>
						<button class="sf-btn" type="submit" name="do" value="skip">Skip</button>
					<?php elseif ( 'retry' === $buttons ) : ?>
						<button class="sf-btn sf-primary" type="submit" name="do" value="retry">Try again</button>
						<button class="sf-btn" type="submit" name="do" value="skip">Skip</button>
					<?php else : ?>
						<button class="sf-btn sf-primary" type="submit" name="do" value="now">Post now</button>
						<button class="sf-btn" type="submit" name="do" value="skip">Cancel this post</button>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</div>
	<?php
};
?>
<p class="sf-lede">
	<?php if ( 'approve' === $mode ) : ?>
		Check the picture, edit the caption, then approve. Posting is set to Ask me first.
	<?php else : ?>
		Posting is set to Auto-post, so new posts go straight out. Posts waiting for the posting rules, and any that failed, are shown here.
	<?php endif; ?>
</p>

<h2 class="sf-sec">Waiting for you</h2>
<?php if ( empty( $pending ) ) : ?>
	<div class="sf-tile"><div class="sf-big">All caught up</div><p class="sf-sub">New posts will appear here when the next match event happens.</p></div>
<?php endif; ?>
<?php foreach ( $pending as $p ) { $render( $p, 'approve' ); } ?>

<?php if ( $approved ) : ?>
	<h2 class="sf-sec">Approved, waiting to send</h2>
	<?php foreach ( $approved as $p ) { $render( $p, 'cancel' ); } ?>
<?php endif; ?>

<?php if ( $failed ) : ?>
	<h2 class="sf-sec">Failed</h2>
	<?php foreach ( $failed as $p ) { $render( $p, 'retry' ); } ?>
<?php endif; ?>
