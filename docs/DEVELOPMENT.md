# IteroChat plugin: development, testing, and shipping

This is the maintainer guide. End-user instructions live in `readme.txt` and at iterochat.com/docs.

## Prerequisites

- **PHP** (for `php -l` and the unit test). macOS: `brew install php`.
- **Composer** (for the dev test dependency, PHPUnit). macOS: `brew install composer`.
- **A local WordPress** for end-to-end testing. Easiest: [LocalWP](https://localwp.com/) (GUI) or `@wordpress/env` (`npm i -g @wordpress/env`, Docker-based).
- For a full local run you also need the IteroChat backend, dashboard, and widget running (see their repos).

## Static checks (fast, no WordPress)

```bash
# Syntax-check every PHP file
find . -path ./vendor -prune -o -name '*.php' -print | xargs -n1 php -l

# Install dev tooling (PHPUnit + WordPress Coding Standards), then run both
composer install
composer run test        # PHPUnit (the PKCE test)
composer run lint        # phpcs against phpcs.xml.dist (the approval-critical WPCS sniffs)
```

`composer run lint` runs the WordPress Coding Standards security sniffs (escaping, sanitizing, nonce verification, prefixing, i18n) defined in `phpcs.xml.dist`, without needing a WordPress install. Keep it at zero violations; those sniffs are what the directory enforces.

## WordPress.org Plugin Check (run before every submission)

The directory reviewers run the [Plugin Check](https://wordpress.org/plugins/plugin-check/) tool (WPCS + more). Run it yourself first so you catch issues before they delay review:

```bash
# inside a local WordPress
wp plugin install plugin-check --activate
wp plugin check iterochat
```

The three most common rejection reasons it enforces, and how this plugin satisfies them:

- **Unescaped output**: every echo/print escapes at the point of output (`esc_html`, `esc_attr`, `esc_url`, `esc_html_e`, `esc_html__`). The only token ever printed to the front end is the public widget key (`esc_attr`); the access token is never output.
- **Unsanitized input**: the only request input is `$_POST['iterochat_action']` (`sanitize_key( wp_unslash( ... ) )`) and the `iterochat_enabled` checkbox (read as a boolean via `isset`).
- **No nonce on form processing**: a single nonce (`iterochat_admin`) is verified with `check_admin_referer()` at the very top of the POST handler, before any request data is read; the AJAX poll handler verifies `check_ajax_referer( 'iterochat_poll' )`. Every handler also calls `current_user_can( 'manage_options' )` (a nonce is not authorization).

Other guideline points already handled: GPLv2+ license; unique `iterochat_` / `ITEROCHAT_` prefixes; no obfuscation; the WordPress HTTP API (`wp_remote_*`) instead of cURL; the `== External services ==` disclosure in `readme.txt`; direct-access `ABSPATH` guards on every shipped PHP file.

## Local end-to-end test

1. **Run the IteroChat services locally:**
   - backend: `make dev` in `support-chat-backend` (API on `:8540`). Ensure its `SITE_BASE_URL` is `http://localhost:3000`, because the approval URL the plugin opens comes from the backend's device-code response.
   - dashboard: `npm run dev` in `support-chat-dashboard` (`:3000`).
   - widget: build and serve `support-chat-widget` on `:3001` (only needed for the bubble to render on the front end; the connect flow itself does not need it).
2. **Add the plugin to a local WordPress:**
   ```bash
   ln -s /absolute/path/to/iterochat-wordpress /path/to/wordpress/wp-content/plugins/iterochat
   ```
3. **Point the plugin at the local services** in that WordPress's `wp-config.php`:
   ```php
   define( 'ITEROCHAT_DASHBOARD_URL', 'http://localhost:3000' );
   define( 'ITEROCHAT_API_URL',       'http://localhost:8540' );
   define( 'ITEROCHAT_WIDGET_URL',    'http://localhost:3001' );
   ```
4. **Walk the flow:** activate the plugin → IteroChat menu → Connect → approve as an owner/admin in the tab that opens → the plugin page auto-updates to "Connected" → load a front-end page and confirm the `widget.js` script tag is present and the bubble loads. Then test Disconnect, the enable/disable toggle, and revoke-from-dashboard (the plugin should flip to the "revoked" state on the next settings-page load).

## Build a distributable zip

```bash
./build.sh        # produces build/iterochat-<version>.zip (plugin files only)
unzip -l build/iterochat-*.zip   # verify: no tests/, vendor/, composer*, build.sh, docs/
```

## Shipping

The plugin only works against live infrastructure, so first ensure the **coordinated prod release** is done: the backend and dashboard are released, and `api.iterochat.com` + `widget.iterochat.com` are deployed and DNS-resolved.

Then distribute through both channels:

- **Direct download** (immediate, no gatekeeper): host `build/iterochat-<version>.zip` on iterochat.com. Users install via Plugins, Add New, Upload Plugin.
- **WordPress.org directory** (discoverable; manual review):
  1. Pre-submission checklist: confirm `readme.txt` `Contributors` is a real WordPress.org username; `Tested up to` matches the WP version you actually tested; `/terms` and `/privacy` resolve on iterochat.com; `php -l`, `phpunit`, and `wp plugin check` all pass.
  2. Submit the zip at https://wordpress.org/plugins/developers/add/ for review (days to a couple of weeks).
  3. On approval you get an **SVN** repository (not git). Push the plugin to `trunk/` and copy to `tags/<version>/`, and set `Stable tag` in the trunk `readme.txt`. This git repo stays the source of truth; SVN is only the distribution channel.

## Versioning and release

Bump the version in **both** `iterochat.php` (the `Version:` header) and `readme.txt` (`Stable tag`), commit, then `git tag v<version>`. Tag a release only after local e2e passes and the backend/dashboard are live.
