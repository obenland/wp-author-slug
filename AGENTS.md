# AGENTS.md

This file provides guidance to coding agents (Claude Code, Codex, etc.) when working with code in this repository.

## What this is

WP Author Slug is a single-purpose WordPress plugin published on WordPress.org. It rewrites the author archive URL slug (`user_nicename`) to a sanitized version of the user's `display_name` instead of the login name.

The plugin itself is plain PHP loaded by WordPress — no build step, no JS/CSS pipeline. The `wp-env` + PHPUnit setup exists only for local development and CI.

Default branch is `trunk` (not `main`).

## Commands

Setup:

```bash
composer install   # PHPCS + WPCS + phpunit-polyfills
npm install        # @wordpress/env (requires Docker)
```

Local WordPress (Docker via `@wordpress/env`):

```bash
npm run start      # boot wp-env (first run downloads WP + MySQL images)
npm run stop
npm run destroy    # full reset
```

Lint:

```bash
composer lint      # PHPCS, matches CI
composer lint:fix  # PHPCBF auto-fix
```

Tests (require `npm run start` first; PHPUnit runs inside the `tests-cli` container):

```bash
npm test                    # alias for test-php (single-site)
npm run test-php            # PHPUnit single-site
npm run test-php-multisite  # PHPUnit multisite
```

To run a single test, append the PHPUnit `--filter` flag through the wp-env wrapper:

```bash
npm run test-php -- --filter test_pre_user_nicename_overrides_with_display_name
```

CI runs both `wpcs.yml` (PHPCS, **`continue-on-error: true` + `cs2pr` annotations** — violations surface as PR annotations but do not block merges) and `phpunit.yml` (PHP 7.4 + 8.4 matrix against WP latest, single-site **and** multisite).

## Architecture

Three PHP files form a small inheritance chain:

- `wp-author-slug.php` — plugin bootstrap. Registers `register_activation_hook` / `register_deactivation_hook` and instantiates the singleton via `Obenland_Wp_Author_Slug::get_instance()`.
- `class-obenland-wp-author-slug.php` — plugin logic. Hooks `pre_user_nicename` (overwrites nicename whenever a user is saved with a `display_name` in the request) and `admin_notices` (surfaces stored slug/page conflicts).
- `class-obenland-wp-plugins-v5.php` — Konstantin's reusable base class for his plugins. Provides the `hook()` helper (auto-maps WP hook names to method names by replacing `.`/`-` with `_`), donate-link plumbing, `donate_box`, and `feed_box`. Treat it as vendored utility code — changes here affect the inheritance contract, but it is rarely the right place to add plugin-specific behavior. Note the parent always loads its strings under the `obenland-wp` text domain even though the plugin's text domain is `wp-author-slug`.

Tests live in `tests/` and use the WordPress core PHPUnit harness:

- `tests/bootstrap.php` resolves `WP_TESTS_DIR` from env (set by wp-env), loads composer autoload, force-activates the plugin via `$GLOBALS['wp_tests_options']['active_plugins']`, then loads the plugin on `muplugins_loaded`.
- The `npm run test-php*` scripts execute `composer test[-multisite]` inside the wp-env `tests-cli` container with `--env-cwd=/var/www/html/wp-content/plugins/wp-author-slug`. Don't try to run `phpunit` directly on the host — it has no WP test harness.

### Activation/deactivation are bulk user mutations

`wp_author_slug_activation()` iterates **every** user on the site and rewrites `user_nicename` to `sanitize_title( display_name )`. `wp_author_slug_deactivation()` restores it to `sanitize_title( user_login )`. The `readme.txt` explicitly warns against using this on sites with >1000 users — keep that constraint in mind for any change that touches activation.

During activation, slugs that collide with an existing page (`get_page_by_path()`) are recorded in the `wp_author_slug_conflicts` option. The `admin_notices` handler renders these to users with the `edit_users` capability and instructs them to fix the page slug then deactivate/reactivate (the option is only cleared on deactivation).

### The `pre_user_nicename` filter trusts `$_REQUEST['display_name']`

`Obenland_Wp_Author_Slug::pre_user_nicename()` reads `$_REQUEST['display_name']` directly (with `phpcs:ignore` for nonce + sanitization) and runs `sanitize_title()` on it. Keep this behavior intact when modifying — WP's profile-update flow is the intended caller, and stripping the ignore comments will trip CI.

## Release / deploy flow

This repo is the source of truth; WordPress.org's SVN is a publish target driven by GitHub Actions:

- **Push any tag** → `.github/workflows/deploy.yml` runs `10up/action-wordpress-plugin-deploy` (publishes to wp.org SVN) **and** `.github/workflows/release.yml` creates a GitHub Release with an auto-generated changelog from `.github/changelog_configuration.json`. Both workflows trigger on `*`.
- **Push to `trunk`** → `.github/workflows/push-asset-readme-update.yml` syncs `readme.txt` and `.wordpress-org/` assets to SVN without cutting a release.
- **Weekly cron (Mon 09:00 UTC)** → `.github/workflows/update-tested-up-to.yml` bumps `Tested up to:` in `readme.txt` to the latest WP major.minor. The workflow first runs a `validate` job (single-site + multisite PHPUnit against latest WP) and only then opens a PR, auto-merges it, and re-syncs to SVN. The merge step branches on `mergeStateStatus` (immediate squash for CLEAN/UNSTABLE, `--auto` for BLOCKED/BEHIND) and refreshes the workspace from merged trunk before the SVN sync — preserve this ordering when editing.

**Cutting a release:**
1. Bump `Version:` in `wp-author-slug.php` and `Stable tag:` in `readme.txt` to the same bare integer (e.g. `6`).
2. Add a `= 6 =` block to the `== Changelog ==` section of `readme.txt`.
3. Tag the commit with the bare integer (`git tag 6 && git push origin 6`). Existing tags are bare integers — match that scheme so `Stable tag` and the git tag align.

`.distignore` controls what `10up/action-wordpress-plugin-deploy` excludes from the SVN checkout. Anything dev-only (build configs, tests, CI, `vendor/`, `node_modules/`, `.wp-env.json`) belongs there.

## Conventions

- WordPress Coding Standards (enforced by CI, but non-blocking — see Commands). PHP `^7.4|^8.0`; composer pins `platform.php: 7.4` so dev installs stay compatible with the floor.
- Docblocks on every class/method/property with aligned `@since`/`@access`/`@param`/`@return` — the existing files use dated `@since` tags (e.g. `1.1 - 03.04.2011`); follow that style when adding new methods.
- Translatable strings in plugin code use the `wp-author-slug` text domain; strings in `class-obenland-wp-plugins-v5.php` use `obenland-wp` (do not "fix" this — it's intentional shared-base behavior).
- The `lang/` directory ships compiled translations (currently `de_DE`); WordPress.org translate handles the rest, so do not regenerate POT files here.
