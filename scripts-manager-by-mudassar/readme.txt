=== Scripts Manager By Mudassar ===
Contributors: mudassarshakeel
Tags: snippets, header, footer, code, conditional logic
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely manage header/footer scripts and PHP, JavaScript, CSS and HTML snippets in simple step-by-step screens, with conditional logic, history, scheduling and an error log file.

== Description ==

Scripts Manager By Mudassar lives under Settings > Scripts Manager.

* Global header, body and footer code, plus GA4, GTM, Meta Pixel and TikTok Pixel fields
* PHP, JavaScript, CSS, HTML, text and universal snippets
* Auto-insert locations, shortcode `[msst_snippet id=""]` and per-page header/footer code
* Smart conditional logic (page, user, device, date, WooCommerce, EDD)
* Revisions with compare and restore, scheduling, import and export (JSON)
* Error log file, problem list, activity list, auto-disable on errors, safe mode
* Local snippet library and generator. No external connections and no tracking.

== Security ==

* Every action requires a nonce and the `msst_manage_snippets` capability.
* PHP snippets additionally require `unfiltered_html`, super admin on multisite and file editing allowed (or `MSST_ALLOW_PHP_EDIT` in wp-config.php).
* Snippets are HMAC-signed; code changed outside the plugin is blocked until re-approved.
* Add `define( 'MSST_DISABLE_SNIPPETS', true );` to wp-config.php to turn every snippet off.

== Changelog ==

= 1.1.2 =
* Smaller dropdown and search boxes in the Add Snippet and My Snippets screens.

= 1.1.1 =
* Fixed a fatal error when activating while the older "Mudassar Snippet Studio" copy was still active. A notice now explains what to do.

= 1.1.0 =
* New simple screens: Start Here, 4-step Add Snippet with a plain-words summary and checklist.
* New error log file (protected folder in uploads) with download and clear buttons.
* Plugin renamed to Scripts Manager By Mudassar.
* Fixed the code editor pushing the side panel off-screen.

= 1.0.0 =
* Initial release.
