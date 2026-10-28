<?php
/**
 * Tournaments screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$followed = SF_Options::get( 'followed', array() );
$found    = get_transient( 'sf_series_results_' . get_current_user_id() );
?>
<p class="sf-lede">Choose what to follow. The tournament name appears on every card. If you follow nothing, the plugin covers every match your API returns.</p>

<h2 class="sf-sec">Following</h2>
<div class="sf-group">
	<?php if ( empty( $followed ) ) : ?>
		<div class="sf-row"><div class="sf-l"><b>Everything</b><span>Search below to follow only some tournaments.</span></div></div>
	<?php endif; ?>
	<?php foreach ( $followed as $id => $name ) : ?>
		<div class="sf-row">
			<div class="sf-l"><b><?php echo esc_html( $name ); ?></b><span class="sf-mono"><?php echo esc_html( $id ); ?></span></div>
			<?php SF_Admin::form_open( 'unfollow', 'class="sf-inline"' ); ?>
				<input type="hidden" name="series_id" value="<?php echo esc_attr( $id ); ?>">
				<button class="sf-btn sf-sm" type="submit">Remove</button>
			</form>
		</div>
	<?php endforeach; ?>
</div>

<h2 class="sf-sec">Find a tournament</h2>
<div class="sf-group"><div class="sf-row">
	<div class="sf-l"><b>Search by name</b><span>Each search uses one cricket API call.</span></div>
	<?php SF_Admin::form_open( 'search_series', 'class="sf-inline sf-search"' ); ?>
		<label class="screen-reader-text" for="sf-q">Tournament name</label>
		<input class="sf-field" id="sf-q" type="text" name="q" placeholder="Tournament name" value="<?php echo $found ? esc_attr( $found[0] ) : ''; ?>">
		<button class="sf-btn sf-sm" type="submit">Search</button>
	</form>
</div></div>

<?php if ( is_array( $found ) && ! empty( $found[1] ) ) : ?>
	<h2 class="sf-sec">Results for "<?php echo esc_html( $found[0] ); ?>"</h2>
	<div class="sf-group">
		<?php foreach ( array_slice( $found[1], 0, 15 ) as $s ) : ?>
			<?php
			$sid  = isset( $s['id'] ) ? $s['id'] : '';
			$name = isset( $s['name'] ) ? $s['name'] : $sid;
			$dates = trim( ( isset( $s['startDate'] ) ? $s['startDate'] : '' ) . ( isset( $s['endDate'] ) ? ' to ' . $s['endDate'] : '' ) );
			?>
			<div class="sf-row">
				<div class="sf-l"><b><?php echo esc_html( $name ); ?></b><span><?php echo esc_html( $dates ); ?></span></div>
				<?php if ( isset( $followed[ $sid ] ) ) : ?>
					<span class="sf-pill sf-ok">Following</span>
				<?php else : ?>
					<?php SF_Admin::form_open( 'follow', 'class="sf-inline"' ); ?>
						<input type="hidden" name="series_id" value="<?php echo esc_attr( $sid ); ?>">
						<input type="hidden" name="series_name" value="<?php echo esc_attr( $name ); ?>">
						<button class="sf-btn sf-sm" type="submit">Follow</button>
					</form>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php elseif ( is_array( $found ) ) : ?>
	<p class="sf-sub">Nothing found for "<?php echo esc_html( $found[0] ); ?>".</p>
<?php endif; ?>

<h2 class="sf-sec">Add by id</h2>
<div class="sf-group"><div class="sf-row">
	<div class="sf-l"><b>Tournament id and name</b><span>The id is shown in your cricket API dashboard.</span></div>
	<?php SF_Admin::form_open( 'follow', 'class="sf-inline sf-search"' ); ?>
		<label class="screen-reader-text" for="sf-sid">Tournament id</label>
		<input class="sf-field" id="sf-sid" type="text" name="series_id" placeholder="Id">
		<label class="screen-reader-text" for="sf-sname">Tournament name</label>
		<input class="sf-field" id="sf-sname" type="text" name="series_name" placeholder="Name">
		<button class="sf-btn sf-sm" type="submit">Follow</button>
	</form>
</div></div>
