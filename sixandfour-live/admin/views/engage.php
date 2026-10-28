<?php
/**
 * Engage screen.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$o = SF_Options::all();
$jobs = array(
	'milestones' => array( 'Milestone alerts', 'Centuries and five-wicket hauls, with a question for fans' ),
	'stumps'     => array( 'Test match day summaries', 'A post at stumps each day of a Test, then the final result' ),
	'poll'       => array( 'Pick a side', 'Before a match, fans vote with a reaction and comment their score' ),
	'table'      => array( 'Weekly points table', 'Every Monday at 09:00 for the tournaments you follow' ),
	'ranking'    => array( 'Monthly team ranking', 'On the 1st at 09:00, worked out from the matches this plugin covered' ),
	'potm'       => array( 'Top performer card', 'A separate card shortly after the match summary' ),
	'pinned'     => array( 'Pinned "Today\'s matches" post', 'One text post that the plugin edits through the day' ),
);
$core = array(
	'fixtures' => array( "Today's matches", 'Every day at 08:00' ),
	'toss'     => array( 'Toss', 'When the toss is announced in the data' ),
	'live'     => array( 'Live updates', 'Innings breaks for the matches with their own card' ),
	'wicket'   => array( 'Wickets', 'A card for each wicket. This can add many posts.' ),
	'result'   => array( 'Result', 'When a match finishes' ),
	'summary'  => array( 'Match summary', 'Top scorer, top wicket taker and key numbers' ),
);
SF_Admin::form_open( 'save_engage' );
?>
<p class="sf-lede">Posts that give fans something to react to and comment on. Every one ends with a question.</p>

<h2 class="sf-sec">Engagement posts</h2>
<div class="sf-group">
	<?php foreach ( $jobs as $k => $j ) { SF_Admin::switch_row( 'job_' . $k, $o['jobs'][ $k ], $j[0], $j[1] ); } ?>
</div>

<h2 class="sf-sec">Milestones to alert</h2>
<div class="sf-group">
	<?php
	SF_Admin::switch_row( 'ms_century', $o['milestones']['century'], 'Century' );
	SF_Admin::switch_row( 'ms_fivefor', $o['milestones']['fivefor'], 'Five-wicket haul' );
	SF_Admin::switch_row( 'ms_record', $o['milestones']['record'], 'Tournament record', 'Fastest hundred and best figures, worked out from what this plugin has seen' );
	?>
</div>
<p class="sf-sub">Milestone alerts need the scorecard from your cricket API. Hat-tricks need ball-by-ball data, which the free plan does not include.</p>

<h2 class="sf-sec">Match posts</h2>
<div class="sf-group">
	<?php foreach ( $core as $k => $j ) { SF_Admin::switch_row( 'job_' . $k, $o['jobs'][ $k ], $j[0], $j[1] ); } ?>
</div>

<h2 class="sf-sec">Busy match days</h2>
<div class="sf-group">
	<div class="sf-row"><label class="sf-l" for="live_cards"><b>Matches with their own live card</b><span>The rest go into one shared scoreboard</span></label><input class="sf-field sf-narrow" id="live_cards" name="live_cards" type="number" min="1" max="5" value="<?php echo (int) $o['live_cards']; ?>"></div>
	<div class="sf-row"><label class="sf-l" for="per_match_hour"><b>Posts per match, per hour</b></label><input class="sf-field sf-narrow" id="per_match_hour" name="per_match_hour" type="number" min="1" max="6" value="<?php echo (int) $o['per_match_hour']; ?>"></div>
	<?php SF_Admin::switch_row( 'digest', $o['digest'], 'Shared scoreboard', 'One card with every live match' ); ?>
	<div class="sf-row"><label class="sf-l" for="digest_every"><b>Scoreboard every (minutes)</b></label><input class="sf-field sf-narrow" id="digest_every" name="digest_every" type="number" min="10" max="240" step="5" value="<?php echo (int) $o['digest_every']; ?>"></div>
	<div class="sf-row"><label class="sf-l" for="poll_hours_before"><b>Pick a side, hours before the match</b></label><input class="sf-field sf-narrow" id="poll_hours_before" name="poll_hours_before" type="number" min="1" max="12" value="<?php echo (int) $o['poll_hours_before']; ?>"></div>
	<div class="sf-row"><label class="sf-l" for="polls_per_day"><b>Pick a side posts per day</b></label><input class="sf-field sf-narrow" id="polls_per_day" name="polls_per_day" type="number" min="0" max="10" value="<?php echo (int) $o['polls_per_day']; ?>"></div>
</div>

<h2 class="sf-sec">Questions for fans</h2>
<div class="sf-group"><div class="sf-row sf-block">
	<label for="questions"><b>One question per line.</b> The plugin picks one that fits the post.</label>
	<textarea class="sf-field" id="questions" name="questions" rows="6"><?php echo esc_textarea( $o['questions'] ); ?></textarea>
</div></div>

<div class="sf-actions"><button class="sf-btn sf-primary" type="submit">Save changes</button></div>
</form>

<h2 class="sf-sec">Pinned post</h2>
<div class="sf-tile">
	<p>The pinned post is a text post listing today's matches. Create it once, pin it to the top of your Page, and the plugin keeps the text up to date. A picture cannot be changed after posting, so this one is text only.</p>
	<p class="sf-sub">Current post id: <?php echo $o['pinned_post_id'] ? '<code>' . esc_html( $o['pinned_post_id'] ) . '</code>' : 'none yet'; ?></p>
	<?php SF_Admin::form_open( 'create_pinned', 'class="sf-inline"' ); ?>
		<button class="sf-btn" type="submit"><?php echo $o['pinned_post_id'] ? 'Create a new pinned post' : 'Create the pinned post'; ?></button>
	</form>
</div>
