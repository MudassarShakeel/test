<?php
/**
 * Overview screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$o     = SF_Options::all();
$fb    = get_option( 'sf_fb_status', array() );
$fb    = is_array( $fb ) ? $fb : array();
$stats = SF_API::stats();
$next  = (int) get_option( 'sf_next_check', 0 );
$last  = (int) get_option( 'sf_last_tick', 0 );
list( $seen_at, $raw ) = SF_API::last_matches();
$matches = array();
foreach ( $raw as $r ) {
	if ( is_array( $r ) ) {
		$m = SF_API::normalize_match( $r );
		if ( '' !== $m['id'] && SF_Options::is_followed( $m['series_id'] ) ) {
			$matches[] = $m;
		}
	}
}
$live_i = 0;
$cron_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
?>
<?php if ( ! SF_Options::ready() ) : ?>
	<div class="sf-notice sf-warning">Finish <a href="<?php echo esc_url( SF_Admin::url( 'setup' ) ); ?>">Setup</a> to start: the Facebook Page, its access token and the cricket API key.</div>
<?php endif; ?>

<div class="sf-grid sf-grid-4">
	<div class="sf-tile">
		<h3>Facebook Page</h3>
		<?php if ( ! empty( $fb['ok'] ) ) : ?>
			<div class="sf-big"><span class="sf-dot"></span>Connected</div>
			<p class="sf-sub"><?php echo esc_html( $fb['name'] . ( ! empty( $fb['fans'] ) ? ', ' . number_format_i18n( $fb['fans'] ) . ' followers' : '' ) ); ?></p>
		<?php elseif ( ! empty( $fb['message'] ) ) : ?>
			<div class="sf-big sf-bad-text">Needs attention</div>
			<p class="sf-sub"><?php echo esc_html( $fb['message'] ); ?></p>
		<?php else : ?>
			<div class="sf-big">Not checked</div>
			<p class="sf-sub">Use Test connection in Setup.</p>
		<?php endif; ?>
	</div>
	<div class="sf-tile">
		<h3>Cricket data today</h3>
		<div class="sf-big"><?php echo (int) $stats['hits']; ?> <span class="sf-muted">of <?php echo (int) $stats['limit']; ?> calls</span></div>
		<div class="sf-meter"><i style="width:<?php echo (int) min( 100, $stats['limit'] ? round( $stats['hits'] * 100 / $stats['limit'] ) : 0 ); ?>%"></i></div>
		<p class="sf-sub">One call checks every match.</p>
	</div>
	<div class="sf-tile">
		<h3>Posting</h3>
		<div class="sf-big"><?php echo 'auto' === $o['mode'] ? 'Auto-post' : 'Ask me first'; ?></div>
		<p class="sf-sub"><a href="<?php echo esc_url( SF_Admin::url( 'setup' ) ); ?>">Change in Setup</a></p>
	</div>
	<div class="sf-tile">
		<h3>Next score check</h3>
		<div class="sf-big"><?php echo $next > time() ? esc_html( human_time_diff( time(), $next ) ) : 'Due now'; ?></div>
		<p class="sf-sub"><?php echo $last ? 'Last check ' . esc_html( human_time_diff( $last ) ) . ' ago.' : 'No check yet.'; ?></p>
	</div>
</div>

<?php if ( ! $cron_off ) : ?>
	<div class="sf-notice sf-info">Scheduled jobs only run when someone visits the site. For steady posting, set a real cron job. See <a href="<?php echo esc_url( SF_Admin::url( 'setup' ) ); ?>">Setup</a>.</div>
<?php endif; ?>

<div class="sf-actions">
	<?php SF_Admin::form_open( 'run_now', 'class="sf-inline"' ); ?>
		<button class="sf-btn sf-primary" type="submit">Check matches now</button>
	</form>
	<a class="sf-btn" href="<?php echo esc_url( SF_Admin::url( 'cards' ) ); ?>">See sample cards</a>
</div>

<h2 class="sf-sec">Today's matches</h2>
<div class="sf-group">
	<?php if ( empty( $matches ) ) : ?>
		<div class="sf-row"><div class="sf-l"><b>No matches yet</b><span>Press Check matches now after Setup. The list updates on every check.</span></div></div>
	<?php else : ?>
		<?php
		foreach ( $matches as $m ) :
			if ( $m['ended'] ) {
				$plan = 'Done';
				$cls  = 'sf-gray';
			} elseif ( $m['started'] ) {
				$plan = $live_i < (int) $o['live_cards'] ? 'Live card' : 'In scoreboard';
				$cls  = $live_i < (int) $o['live_cards'] ? 'sf-ok' : 'sf-gray';
				$live_i++;
			} else {
				$plan = 'Upcoming';
				$cls  = 'sf-warn';
			}
			?>
			<div class="sf-row">
				<span class="sf-time"><?php echo $m['start'] ? esc_html( wp_date( 'H:i', $m['start'] ) ) : ''; ?></span>
				<div class="sf-l"><b><?php echo esc_html( $m['teams'][0]['name'] . ' v ' . $m['teams'][1]['name'] ); ?></b><span><?php echo esc_html( trim( SF_Options::series_name( $m['series_id'] ) . ' ' . $m['status'] ) ); ?></span></div>
				<span class="sf-pill <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $plan ); ?></span>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php if ( $seen_at ) : ?>
	<p class="sf-sub">List from the check <?php echo esc_html( human_time_diff( $seen_at ) ); ?> ago.</p>
<?php endif; ?>

<h2 class="sf-sec">Recent posts</h2>
<div class="sf-group">
	<?php $recent = SF_DB::recent_posts( 10 ); ?>
	<?php if ( empty( $recent ) ) : ?>
		<div class="sf-row"><div class="sf-l"><b>Nothing yet</b><span>Posts appear here as soon as they are queued.</span></div></div>
	<?php endif; ?>
	<?php foreach ( $recent as $p ) : ?>
		<?php
		$map = array( 'posted' => 'sf-ok', 'approved' => 'sf-warn', 'pending' => 'sf-warn', 'failed' => 'sf-bad', 'skipped' => 'sf-gray' );
		?>
		<div class="sf-row">
			<span class="sf-time"><?php echo esc_html( wp_date( 'H:i', strtotime( ( $p['posted_at'] ? $p['posted_at'] : $p['created_at'] ) . ' UTC' ) ) ); ?></span>
			<div class="sf-l"><b><?php echo esc_html( ucfirst( $p['type'] ) ); ?></b><span><?php echo esc_html( wp_trim_words( strtok( $p['caption'], "\n" ), 18 ) ); ?><?php echo $p['error'] ? ' (' . esc_html( $p['error'] ) . ')' : ''; ?></span></div>
			<span class="sf-pill <?php echo esc_attr( isset( $map[ $p['status'] ] ) ? $map[ $p['status'] ] : 'sf-gray' ); ?>"><?php echo esc_html( ucfirst( $p['status'] ) ); ?></span>
		</div>
	<?php endforeach; ?>
</div>

<h2 class="sf-sec">Activity log</h2>
<div class="sf-group">
	<?php $log = SF_Log::recent( 8 ); ?>
	<?php if ( empty( $log ) ) : ?>
		<div class="sf-row"><div class="sf-l"><b>Quiet so far</b></div></div>
	<?php endif; ?>
	<?php foreach ( $log as $l ) : ?>
		<div class="sf-row"><span class="sf-time"><?php echo esc_html( wp_date( 'H:i', $l[0] ) ); ?></span><div class="sf-l"><span><?php echo esc_html( $l[2] ); ?></span></div></div>
	<?php endforeach; ?>
</div>
