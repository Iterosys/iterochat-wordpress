# IteroChat WordPress plugin

A WordPress plugin that connects a site to IteroChat with no code and injects the chat widget. It is one of four IteroChat repos:
- `support-chat-backend` (FastAPI, the OAuth provider this plugin talks to)
- `support-chat-dashboard` (Next.js, hosts the device approval page)
- `support-chat-widget` (the embeddable `widget.js`)
- **`iterochat-wordpress`** (you are here)

## What it does

OAuth 2.0 public client using the **Device Authorization Grant (RFC 8628)** with PKCE. There is no `redirect_uri` and no callback; the plugin polls for its token. Flow:

1. Admin clicks "Connect to IteroChat" (`includes/admin.php`, nonce + `manage_options`).
2. The plugin generates a PKCE pair and calls `POST {API}/api/oauth/device/code`, stores `{device_code, verifier, verification_uri_complete, interval}` in a transient (`iterochat_device_flow`), and renders a waiting state.
3. `assets/connect.js` opens the approval page (`verification_uri_complete`, on the dashboard) in a new tab and polls the `iterochat_poll` admin-ajax action.
4. That handler calls `POST {API}/api/oauth/token` (grant `urn:ietf:params:oauth:grant-type:device_code`) until approved, then stores `{access_token, widget_key, org_id, org_name}` in the `iterochat_connection` option.
5. `includes/frontend.php` emits `<script src="{WIDGET}/widget.js" data-widget-key="...">` in `wp_footer`.

The access token stays server-side (option saved with no autoload); only the public widget key is ever printed. A liveness check on the settings page detects revocation (401 from `GET {API}/api/connect/widget`).

## File structure

```
iterochat.php                       # header + constants + requires
includes/config.php                 # origin resolvers (wp-config overridable)
includes/options.php                # connection state get/save/clear
includes/class-iterochat-oauth.php  # PKCE (pure, unit-tested) + device-code/poll/read (WP HTTP)
includes/admin.php                  # menu, settings render, actions, AJAX poll handler, enqueue
includes/frontend.php               # wp_footer widget injection
assets/connect.js                   # opens approval tab + polls + updates UI
uninstall.php                       # removes the option + transient
tests/PkceTest.php                  # RFC 7636 PKCE vector
build.sh                            # produces a clean distributable zip
```

## Origins (prod defaults, overridable per install)

`includes/config.php` defaults: dashboard `https://iterochat.com`, API `https://api.iterochat.com`, widget `https://widget.iterochat.com`. Override any of them in `wp-config.php` for dev/self-hosting:

```php
define( 'ITEROCHAT_DASHBOARD_URL', 'http://localhost:3000' );
define( 'ITEROCHAT_API_URL', 'http://localhost:8540' );
define( 'ITEROCHAT_WIDGET_URL', 'http://localhost:3001' );
```

## Develop and test

PHP is required for `php -l` and the unit test (install on macOS with `brew install php`; Composer with `brew install composer`).

```bash
composer install            # dev deps (phpunit)
vendor/bin/phpunit          # runs the PKCE test
find . -path ./vendor -prune -o -name '*.php' -print | xargs -n1 php -l   # syntax check
```

Local end-to-end: run the backend stack (`make dev` in support-chat-backend gives API on :8540) and the dashboard (`npm run dev` on :3000), symlink this repo into a local WordPress `wp-content/plugins/iterochat`, set the three `wp-config.php` constants above, activate, and run the connect flow.

## Conventions

- Prefix everything `iterochat_` (functions) / `ITEROCHAT_` (constants). Option `iterochat_connection`; transient `iterochat_device_flow`; nonces `iterochat_connect`/`cancel`/`disconnect`/`toggle`/`poll`.
- Every state-changing request: `current_user_can('manage_options')` + a nonce (`check_admin_referer` / `check_ajax_referer`). Sanitize input (`wp_unslash` + `sanitize_*`), escape all output (`esc_html`/`esc_attr`/`esc_url`).
- Never print the access token. Only the widget key (public) is output.
- No em dashes in any prose or comments. Use commas, colons, parentheses.
- Build to WordPress.org standards (GPLv2+, `readme.txt` with the External services disclosure).

## Git

Remote is the Iterosys org over the `github-personal` SSH alias (maps to the `shahad-mahmud` account, which has Iterosys access; `shahad-dl` does not). If a push is denied, check `git remote get-url origin` is `git@github-personal:Iterosys/iterochat-wordpress.git`. No `Co-Authored-By` trailers.

This repo has no prod deploy, so initial work goes on `main`; review before tagging a release.

## Build and publish

`./build.sh` makes `build/iterochat-<version>.zip` (plugin files only). Distribute that zip from iterochat.com immediately; submit the same zip to the WordPress.org plugin directory (review then SVN). Before submitting, confirm the `readme.txt` `Contributors` handle, `Tested up to`, and that the terms/privacy URLs resolve.
