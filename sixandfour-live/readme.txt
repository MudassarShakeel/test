=== Cricket Sport Live ===
Contributors: mudassar
Tags: cricket, facebook, scores, social, auto post
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Posts cricket scores, toss, results, match summaries, milestones, polls and rankings to your Facebook Page as clean score-card pictures.

== Description ==

Cricket Sport Live reads match data from CricketData.org and posts finished score-card pictures to your Facebook Page.

* Today's matches, toss, live updates, results and a full match summary with the top scorer and top wicket taker.
* Milestone alerts for centuries and five-wicket hauls, with tournament records.
* "Pick a side" posts before a match, weekly points tables, a monthly team ranking, and a pinned "Today's matches" post that the plugin keeps up to date.
* A shared scoreboard card for busy days, so ten matches in one day do not mean ten separate streams of posts.
* Manual posts: add your own picture and words, with an optional Cricket Sport mark, and post now or schedule.
* Auto-post, or Ask me first so every post waits for your approval.

Cards are drawn with PHP GD using the Inter font. National teams show a country flag. You can upload your own picture for any team.

= Needs =

* A Facebook Page, a Meta developer app, and a Page access token with pages_manage_posts and pages_read_engagement.
* A CricketData.org API key. The free plan has a small daily limit, and the plugin spaces its checks to stay inside it.
* PHP 7.4 or newer with the GD extension and FreeType support.

= About the monthly ranking =

The monthly ranking is worked out from the results this plugin saw, with 2 points for a win and 1 for a tie or no result. It is not an official ranking.

= Logos and licences =

Official cricket board and league logos are not included. Flags come from the flag-icons set (MIT). The Inter font is under the SIL Open Font License. Icons are from Phosphor Icons (MIT).

== Installation ==

1. Upload the sixandfour-live folder to /wp-content/plugins/, or upload the zip under Plugins, Add New.
2. Activate the plugin.
3. Open Cricket Sport, then Setup. Add the Page ID, the Page access token and the CricketData.org key, then press Test connection.
4. Under Tournaments, follow the tournaments you want.
5. For steady posting, add a real cron job. The Setup screen shows the exact command.

== Getting the Facebook access token ==

1. Go to developers.facebook.com, create an app of the Business type, and add the Facebook Login product.
2. In the Graph API Explorer, pick your app, choose "Get User Access Token", and tick pages_show_list, pages_manage_posts and pages_read_engagement. Make sure you are an admin of the Page.
3. Exchange that token for a long-lived user token, then call /me/accounts. The access_token returned for your Page is a Page token that does not expire.
4. Paste the Page ID and that Page token into Cricket Sport, then Setup.

While your Meta app is in Development mode you can post to a Page you admin without App Review.

== Frequently Asked Questions ==

= Why is a toss post missing? =

The toss post depends on the match status text from your cricket API. If the status does not mention the toss, there is nothing to post.

= Why is the card called Top performer, not Player of the match? =

The free API data does not include the official award. The plugin picks the standout player from the scorecard using runs plus 25 points per wicket.

= Can I post to Facebook with no approval? =

Yes. Auto-post is the default. Choose Ask me first in Setup if you want to approve each post.

== Changelog ==

= 1.4.0 =
* New card header: a dark rounded card with the logo, the name and your Page link under it, on every card.
* New: Test matches. A summary card at stumps each day (for example "Stumps Day 2"), the toss as the match start, and the final result, including "won by an innings and 25 runs" and draws. Tests show every innings (312 & 145/3) and do not post a card for every wicket.
* New: Players screen. Add your own photo for a player and it shows on that player's century, five-wicket, top performer and summary cards. The plugin never downloads player photos.

= 1.3.0 =
* New: the brand is now Cricket Sport (facebook.com/cricketsportinfo). New logo, cyan accent colour, card header and watermark.
* The plugin folder and settings keep their old names, so updating loses nothing.

= 1.2.0 =
* New: only men's matches between the top 20 teams are covered by default. Women's matches are skipped. Tournaments you follow are always covered. The team list is editable in Setup.
* New: up to three backup cricket API keys. When one is used up for the day, the next one takes over.
* Changed: the plugin now checks often only around live matches and the hour before a match. Between matches it sleeps up to two hours. Two quick presses of Check now cost one call.
* The Setup screen shows the calls used today over all keys.

= 1.1.0 =
* New: password-free sign-in for administrators with passkeys (fingerprint, face or screen lock) and one-time email links. Passwords can be switched off for administrators once a passkey exists.
* New: a phone-first console on your home page with the shortcode [sixandfour_admin]. Approve posts, post your own picture, pause posting and run the checks from your phone.
* New: the settings screens fit phone screens, with large tap targets and scrolling tabs.
* Emergency way back in: add define( 'SF_ALLOW_PASSWORD', true ); to wp-config.php.

= 1.0.1 =
* Fixed: the five-minute checks were not scheduled until the second page load after activation.
* Fixed: the match summary could use a stale scorecard fetched while the match was still live.
* Security: the Facebook token and cricket API key are now encrypted in the database and scrubbed from logs and error messages.
* Security: uploaded pictures are re-drawn before posting, which strips hidden code and metadata. Files over 8 MB or 5000 pixels are refused.
* Security: the picture folder now has index.php and .htaccess files that block listings and PHP.
* Security: posted items can no longer be edited, and requests to Facebook and the cricket API no longer follow redirects.
* New Security screen with a health check and switches for a log-in limiter, blocking the file editor, turning off XML-RPC, hiding the WordPress version, hiding user names and safety headers.
* New sf_capability filter to choose who can use the plugin.

= 1.0.0 =
* First release.
