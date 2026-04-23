# Noted Visual Feedback

WordPress plugin for pinning visual feedback comments directly on a live site. Share a page-scoped link with clients or teammates and collect threaded feedback without screenshots or external tools. All data stays in your WordPress database.

This is the source repository for the free plugin distributed on WordPress.org.

## Install

From the WordPress admin, search for "Noted Visual Feedback" in the plugin directory, or download the latest zip from the WordPress.org listing.

## What's included

- Pin comments anywhere on any page, anchored by coordinate or page element
- Threaded replies
- Page-scoped share links for guest reviewers (no WordPress account required)
- Admin dashboard with pin list and per-pin detail
- CSV export
- Email notifications on new pins and replies
- Opt-in "Powered by Noted" attribution (off by default)

## Repository layout

- `noted.php` — main plugin file with the header and bootstrap
- `includes/` — PHP classes (activator, REST API, admin UI, script loader, notifications, cron, capabilities, walkthrough)
- `templates/` — admin page templates
- `assets/` — compiled overlay bundle (`js/noted-overlay.min.js`), admin CSS/JS, WP.org banner/icon/screenshots
- `overlay-src/` — **TypeScript source for the frontend review overlay**. See `overlay-src/README.md` for build instructions
- `languages/` — translation templates

## Building the overlay

The frontend review overlay is compiled from TypeScript. Node 18+ required.

```bash
cd overlay-src
npm install
npm run build
```

This produces `assets/js/noted-overlay.min.js`. Iterative development with `npm run dev` (Vite watch mode).

The admin-side JavaScript at `assets/js/noted-admin.js` is plain JS and not compiled.

## Premium features

A separate companion plugin, Noted Pro, adds drawing annotations, text-edit suggestions, priority labels, internal pins, bulk resolve, password-protected and expiring share links, third-party integrations (Asana, Trello, Slack, Jira, Linear, GitHub Issues, Monday.com, webhooks), and white-label mode. Learn more at [wpnoted.com/pricing](https://wpnoted.com/pricing/).

## License

GPL-2.0-or-later. See `LICENSE`.

## Author

[Mountain Thirteen Media](https://mountainthirteen.com)
