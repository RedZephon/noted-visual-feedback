=== Noted Visual Feedback ===
Contributors: mountainthirteen
Tags: feedback, comments, review, annotation, collaboration
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect visual feedback with pinned comments directly on your live WordPress site. All data stored locally in your WordPress database.

== Description ==

**Noted Visual Feedback** adds a lightweight feedback overlay to your WordPress site. Pin comments anywhere on any page, start threaded discussions, and share a review link with clients or teammates. No screenshots, no separate tools, no external accounts.

All feedback data is stored in your own WordPress database in custom tables. The plugin makes no external HTTP calls of any kind.

= What's included =

* Pin comments anywhere on any page, anchored by position or by page element
* Threaded replies on each pin
* Page-scoped share links for guest reviewers (no WordPress account required)
* Password and expiry controls on share links
* Admin dashboard with pin list, pin detail view, and per-pin resolve/delete
* CSV export of pins and comments
* Email notifications on new pins and replies
* Opt-in "Powered by Noted" attribution (off by default)
* Works with any theme or page builder

= How it works =

1. Install and activate the plugin.
2. Visit any page on your site and append `?noted` to the URL.
3. Click anywhere on the page to place a pin and leave a comment.
4. Generate a share link for that page from the admin and send it to a reviewer. They enter their name and can start leaving feedback immediately.

= Noted Pro =

A separate premium plugin, Noted Pro, extends this free plugin with:

* Drawing annotations (rectangles, arrows, freehand)
* Text-edit suggestions with accept/reject workflow
* Priority and category labels on pins
* Internal-only pins hidden from guests
* Bulk resolve
* Password-protected and expiring share links
* Export to Asana, Trello, Slack, Jira, Linear, GitHub Issues, Monday.com
* Generic webhook export
* White-label mode
* WordPress Multisite support

Learn more at [wpnoted.com/pricing](https://wpnoted.com/pricing/). Noted Pro installs alongside this plugin; you do not need to replace or reconfigure anything.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install from the WordPress plugin directory.
2. Activate through the **Plugins** menu in WordPress.
3. Open any page on your site and add `?noted` to the URL to activate the overlay.
4. Use **Noted > Pages** in the admin to generate share links for individual pages.

== Frequently Asked Questions ==

= How do I activate the review overlay on a page? =

Append `?noted` to any page URL (for example, `https://example.com/?noted`). A "Review" link is also added to the WordPress admin bar for logged-in users with the required capability.

= Where is my feedback data stored? =

In your own WordPress database, in custom tables prefixed `noted_`. No data is sent anywhere else.

= Can guests leave feedback without a WordPress account? =

Yes. Generate a share link for a page in **Noted > Pages**, send it to your reviewer, they enter a display name, and they can start pinning feedback. Share links are scoped to the specific page they were generated for.

= Does the overlay slow down my site for regular visitors? =

No. The overlay JavaScript loads only when `?noted` is present in the URL or when a valid guest share link is being used. Anonymous site visitors are not affected.

= What happens when I deactivate the plugin? =

Your data remains in the database. Reactivating restores everything. Deleting (uninstalling) the plugin removes all of its tables and options.

= Does this plugin make external network requests? =

No. The free plugin stores all data locally and makes no outbound HTTP calls.

== External Services ==

This plugin does not connect to any external services. All feedback data is stored locally in your WordPress database.

== Source Code ==

The frontend review overlay is a TypeScript module compiled to `assets/js/noted-overlay.min.js`. The human-readable source and build instructions are available in two places:

1. **Public repository:** [https://github.com/RedZephon/noted-visual-feedback](https://github.com/RedZephon/noted-visual-feedback)
2. **Included with every release** under the `overlay-src/` directory inside the plugin zip.

Build with Node 18+:

`cd overlay-src && npm install && npm run build`

This produces the bundle that the plugin enqueues. See `overlay-src/README.md` for details on layout and tooling (Vite + TypeScript). The admin-side JavaScript at `assets/js/noted-admin.js` is not compiled and can be read directly.

== Screenshots ==

1. Pin comments directly on your live site
2. Threaded comments panel with status filtering
3. Guest reviewer entry screen, no account needed
4. Admin dashboard with feedback overview

== Changelog ==

= 1.0.5 =
* Free-version release for the WordPress plugin directory
* Page-scoped share links with password and expiry controls
* No external service dependencies
* Opt-in branding attribution
* Hardened REST API guest permissions

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.5 =
Initial WordPress.org release of Noted Visual Feedback.
