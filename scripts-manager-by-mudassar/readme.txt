=== Scripts Manager By Mudassar ===
Contributors: mudassarshakeel
Tags: snippets, header, footer, code, scripts
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add HTML, CSS, JavaScript and PHP snippets with a simple list and form.

== Description ==

Scripts Manager By Mudassar adds HTML, CSS, JavaScript and PHP snippets to your site, with a simple list, a simple form and nothing else to learn.

* All Snippets list with ON/OFF switch, search, filters, bulk actions and shortcodes
* Add New form: name, type, where to show it (site wide, specific pages, posts, categories, tags, post types, home, search, archives, latest posts or shortcode only), location (header, body, footer, before or after content), device and status
* Tools: export selected snippets and import them again (imported snippets arrive OFF)
* Settings: PHP switch, auto-OFF on errors, test view for you only, Safe Mode link
* No external connections and no tracking

== Security ==

* Every action requires a nonce and the `msst_manage_snippets` capability.
* PHP snippets additionally require `unfiltered_html`, super admin on multisite and file editing allowed (or `MSST_ALLOW_PHP_EDIT` in wp-config.php).
* Snippets are HMAC-signed; code changed outside the plugin is blocked until you turn it ON again.
* Add `define( 'MSST_DISABLE_SNIPPETS', true );` to wp-config.php to turn every snippet off.

== Changelog ==

= 2.0.2 =
* Snippet list: bulk actions and search are now one tidy row, all controls the same height.

= 2.0.2 =
* Removed the All / Active / Inactive links and the type filter from the snippet list.
* The code box is now tall by default (480 px), resizable, with a full-height line gutter.

= 2.0.2 =
* Rebuilt around a simple All Snippets / Add New / Tools / Settings workflow with its own top-level menu. Starts fresh (snippets from 1.x are not converted).

= 2.0.2 =
* New main tab: Settings. General & Safety, History & Schedule, Import / Export, Problems & Activity and Help & Contact now live inside it. The "More" row is gone; old links still work.

= 1.2.0 =
* Cleaner screens: removed the Start Here, Ready-made and Rules guide tabs and all help/guide text. Headers & Footers is now the first tab, then My Snippets.

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
