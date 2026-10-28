<?php
/**
 * Manual post screen: your own picture and text.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$upcoming = array();
foreach ( SF_DB::posts_by_status( 'approved', 30 ) as $p ) {
	if ( 'manual' === $p['type'] ) {
		$upcoming[] = $p;
	}
}
SF_Admin::form_open( 'manual_post', 'enctype="multipart/form-data"' );
?>
<p class="sf-lede">Post your own picture and words. It goes to the same Facebook Page, outside the automatic rules.</p>
<div class="sf-group">
	<div class="sf-row sf-block">
		<label for="sf-image"><b>Picture</b> <span class="sf-muted">JPG, PNG or WebP. Leave empty for a text post.</span></label>
		<div class="sf-pickrow">
			<input type="file" id="sf-image" name="image" accept="image/jpeg,image/png,image/webp">
			<span class="sf-muted">or</span>
			<button type="button" class="sf-btn sf-sm" id="sf-media">Choose from Media Library</button>
			<input type="hidden" name="attachment_id" id="sf-att" value="">
		</div>
		<img id="sf-preview" class="sf-preview" alt="" hidden>
	</div>
	<div class="sf-row sf-block">
		<label for="sf-caption"><b>Caption</b></label>
		<textarea class="sf-field" id="sf-caption" name="caption" rows="6"></textarea>
	</div>
	<?php SF_Admin::switch_row( 'add_question', 0, 'Add a question for fans', 'Adds one from your list on the Engage screen' ); ?>
	<?php SF_Admin::switch_row( 'watermark', 1, 'Add the Six & Four mark to the picture', 'A small mark in a corner of your picture' ); ?>
	<div class="sf-row">
		<label class="sf-l" for="wm_pos"><b>Mark position</b></label>
		<select class="sf-field sf-narrow" id="wm_pos" name="wm_pos">
			<option value="br">Bottom right</option>
			<option value="bl">Bottom left</option>
			<option value="tr">Top right</option>
			<option value="tl">Top left</option>
		</select>
	</div>
	<div class="sf-row">
		<div class="sf-l"><b>When</b></div>
		<div class="sf-inline">
			<label><input type="radio" name="when" value="now" checked> Post now</label>
			<label><input type="radio" name="when" value="later"> Schedule</label>
			<label class="screen-reader-text" for="when_at">Date and time</label>
			<input class="sf-field" type="datetime-local" id="when_at" name="when_at">
		</div>
	</div>
</div>
<div class="sf-actions"><button class="sf-btn sf-primary" type="submit">Post to Facebook</button></div>
</form>

<?php if ( $upcoming ) : ?>
	<h2 class="sf-sec">Scheduled</h2>
	<div class="sf-group">
		<?php foreach ( $upcoming as $p ) : ?>
			<div class="sf-row">
				<span class="sf-time"><?php echo $p['scheduled_at'] ? esc_html( wp_date( 'j M H:i', strtotime( $p['scheduled_at'] . ' UTC' ) ) ) : 'Soon'; ?></span>
				<div class="sf-l"><b><?php echo esc_html( wp_trim_words( $p['caption'], 14 ) ); ?></b></div>
				<?php SF_Admin::form_open( 'queue_action', 'class="sf-inline"' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
					<button class="sf-btn sf-sm" type="submit" name="do" value="skip">Cancel</button>
				</form>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
