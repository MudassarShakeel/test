=== Mudassar Snippet Studio ===
Contributors: mudassarshakeel
Tags: snippets, header, footer, code, conditional logic
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely manage header/footer scripts and PHP, JavaScript, CSS and HTML snippets with conditional logic, revisions, scheduling and an audit log.

== Description ==

Mudassar Snippet Studio lives under Settings > Snippet Studio.

* Global header, body and footer code, plus GA4, GTM, Meta Pixel and TikTok Pixel fields
* PHP, JavaScript, CSS, HTML, text and universal snippets
* Auto-insert locations, shortcode `[msst_snippet id=""]` and per-page header/footer code
* Smart conditional logic (page, user, device, date, WooCommerce, EDD)
* Revisions with compare and restore, scheduling, import and export (JSON)
* Error log, audit log, auto-disable on errors, safe mode
* Local snippet library and generator. No external connections and no tracking.

== Security ==

* Every action requires a nonce and the `msst_manage_snippets` capability.
* PHP snippets additionally require `unfiltered_html`, super admin on multisite and file editing allowed (or `MSST_ALLOW_PHP_EDIT` in wp-config.php).
* Snippets are HMAC-signed; code changed outside the plugin is blocked until re-approved.
* Add `define( 'MSST_DISABLE_SNIPPETS', true );` to wp-config.php to turn every snippet off.

== Changelog ==

= 1.0.0 =
* Initial release.
