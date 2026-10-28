<?php
/**
 * Setup screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$o  = SF_Options::all();
$fb = get_option( 'sf_fb_status', array() );
$fb = is_array( $fb ) ? $fb : array();
$cron_url = site_url( 'wp-cron.php?doing_wp_cron' );
SF_Admin::form_open( 'save_setup' );
?>
<p class="sf-lede">Connections and posting rules. Tokens are stored in your WordPress database and are never shown again.</p>

<h2 class="sf-sec">Posting</h2>
<div class="sf-group">
	<div class="sf-row">
		<div class="sf-l"><b>How posts go out</b><span>Ask me first keeps every post in the queue until you approve it</span></div>
		<div class="sf-inline">
			<label><input type="radio" name="mode" value="auto" <?php checked( $o['mode'], 'auto' ); ?>> Auto-post</label>
			<label><input type="radio" name="mode" value="approve" <?php checked( $o['mode'], 'approve' ); ?>> Ask me first</label>
		</div>
	</div>
	<div class="sf-row"><label class="sf-l" for="max_posts_day"><b>Posts per day</b><span>Keeps the Page from feeling spammy. Manual posts do not count.</span></label><input class="sf-field sf-narrow" id="max_posts_day" name="max_posts_day" type="number" min="1" max="100" value="<?php echo (int) $o['max_posts_day']; ?>"></div>
	<div class="sf-row"><label class="sf-l" for="quiet_start"><b>Quiet hours start</b><span>Nothing posts between start and end, in the site's time zone</span></label><input class="sf-field sf-narrow" id="quiet_start" name="quiet_start" type="time" value="<?php echo esc_attr( $o['quiet_start'] ); ?>"></div>
	<div class="sf-row"><label class="sf-l" for="quiet_end"><b>Quiet hours end</b></label><input class="sf-field sf-narrow" id="quiet_end" name="quiet_end" type="time" value="<?php echo esc_attr( $o['quiet_end'] ); ?>"></div>
</div>

<h2 class="sf-sec">Facebook Page</h2>
<div class="sf-group">
	<div class="sf-row"><label class="sf-l" for="fb_page_id"><b>Page ID</b><span>From your Page's About section</span></label><input class="sf-field" id="fb_page_id" name="fb_page_id" type="text" inputmode="numeric" value="<?php echo esc_attr( $o['fb_page_id'] ); ?>"></div>
	<div class="sf-row"><label class="sf-l" for="fb_token"><b>Page access token</b><span>Needs pages_manage_posts and pages_read_engagement. <?php echo $o['fb_token'] ? 'A token is saved. Leave blank to keep it.' : ''; ?></span></label><input class="sf-field" id="fb_token" name="fb_token" type="password" autocomplete="new-password" placeholder="<?php echo $o['fb_token'] ? '••••••••••••' : 'Paste the token'; ?>"></div>
	<div class="sf-row"><label class="sf-l" for="fb_handle"><b>Page link shown on cards</b></label><input class="sf-field" id="fb_handle" name="fb_handle" type="text" value="<?php echo esc_attr( $o['fb_handle'] ); ?>"></div>
	<div class="sf-row"><div class="sf-l"><b>Connection</b><span><?php echo ! empty( $fb['message'] ) ? esc_html( $fb['message'] . ( ! empty( $fb['name'] ) ? ': ' . $fb['name'] : '' ) ) : 'Not tested yet'; ?>. Testing posts nothing.</span></div><button class="sf-btn sf-sm" type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php?action=sf_test_fb' ) ); ?>">Test connection</button></div>
</div>

<h2 class="sf-sec">Cricket data</h2>
<div class="sf-group">
	<div class="sf-row"><label class="sf-l" for="api_key"><b>CricketData.org API key</b><span><?php echo $o['api_key'] ? 'A key is saved. Leave blank to keep it.' : 'Get a free key at cricketdata.org'; ?></span></label><input class="sf-field" id="api_key" name="api_key" type="password" autocomplete="new-password" placeholder="<?php echo $o['api_key'] ? '••••••••••••' : 'Paste the key'; ?>"></div>
	<?php foreach ( array( 2, 3, 4 ) as $n ) : $kn = 'api_key' . $n; ?>
	<div class="sf-row"><label class="sf-l" for="<?php echo esc_attr( $kn ); ?>"><b>Backup key <?php echo (int) ( $n - 1 ); ?></b><span><?php echo $o[ $kn ] ? 'A key is saved. Leave blank to keep it. Type - to remove it.' : 'Optional. Used when the key before it is used up for the day.'; ?></span></label><input class="sf-field" id="<?php echo esc_attr( $kn ); ?>" name="<?php echo esc_attr( $kn ); ?>" type="password" autocomplete="new-password" placeholder="<?php echo $o[ $kn ] ? '••••••••••••' : 'Paste the key'; ?>"></div>
	<?php endforeach; ?>
	<div class="sf-row"><label class="sf-l" for="api_limit"><b>Daily call limit for each key</b><span>The plugin keeps 10% in reserve and spaces its checks to last the day</span></label><input class="sf-field sf-narrow" id="api_limit" name="api_limit" type="number" min="10" value="<?php echo (int) $o['api_limit']; ?>"></div>
	<div class="sf-row"><div class="sf-l"><b>API key</b><span>Test costs one call</span></div><button class="sf-btn sf-sm" type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php?action=sf_test_api' ) ); ?>">Test key</button></div>
</div>

<h2 class="sf-sec">Which matches to cover</h2>
<div class="sf-group">
	<?php
	SF_Admin::switch_row( 'skip_women', $o['skip_women'], 'Skip women\'s matches', 'Leaves them out of every post and saves API calls.' );
	SF_Admin::switch_row( 'top_only', $o['top_only'], 'Only matches between the top teams', 'Used when you follow no tournament. A tournament you follow on the Tournaments screen is always covered.' );
	?>
	<div class="sf-row sf-block"><label for="top_teams"><b>Top teams</b> <span class="sf-muted">One per line. Both teams must be on the list.</span></label><textarea class="sf-field" id="top_teams" name="top_teams" rows="8"><?php echo esc_textarea( $o['top_teams'] ); ?></textarea></div>
	<?php $st = SF_API::stats(); ?>
	<div class="sf-row"><div class="sf-l"><b>Cricket calls today</b><span><?php echo (int) $st['hits']; ?> of <?php echo (int) $st['limit']; ?> used over <?php echo (int) $st['keys']; ?> key<?php echo 1 === (int) $st['keys'] ? '' : 's'; ?>. The plugin checks often only around live matches.</span></div></div>
</div>

<h2 class="sf-sec">This site</h2>
<div class="sf-group">
	<?php SF_Admin::switch_row( 'noindex', $o['noindex'], 'Hide from search engines', 'Adds a noindex tag. robots.txt is left alone, so Facebook link previews keep working.' ); ?>
</div>
<div class="sf-actions"><button class="sf-btn sf-primary" type="submit">Save changes</button></div>
</form>

<h2 class="sf-sec">Run it on a real schedule</h2>
<div class="sf-tile">
	<p>WordPress only runs scheduled jobs when someone visits the site. On a quiet site, use a real cron job so scores are checked every 5 minutes.</p>
	<ol class="sf-steps">
		<li>In <code>wp-config.php</code>, add this line above "That's all, stop editing":<br><code>define( 'DISABLE_WP_CRON', true );</code></li>
		<li>In Hostinger hPanel, open Advanced, then Cron Jobs, and add a job every 5 minutes with this command:<br><code>wget -q -O - "<?php echo esc_html( $cron_url ); ?>" &gt;/dev/null 2&gt;&amp;1</code></li>
	</ol>
	<p class="sf-sub">Status: <?php echo ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'WordPress cron is switched off, so your real cron job must be running.' : 'WordPress cron is on, so jobs run when the site gets visits.'; ?></p>
</div>
