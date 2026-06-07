# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A single-file-architecture WordPress plugin (`yes-age-verification.php`) that shows a configurable
age-verification popup on the frontend. No build step, no package manager, no test suite — it's
plain PHP/JS/CSS deployed directly into `wp-content/plugins/`. There is no `composer.json` or
`package.json`; everything is hand-written WordPress-native code (`jQuery`, `wp.media`, settings API).

## Development workflow

There is no build/lint/test tooling in this repo. To verify changes:

- **Syntax-check PHP**: `php -l yes-age-verification.php` (and any other changed `.php` file).
- **Manual testing**: this plugin only does anything inside a running WordPress install (it's
  loaded via `plugins_loaded` from `/home/haris/DevKinsta/public/wordpress/`). Activate it from
  **Plugins**, configure it under **Settings → Age Verification**, then view the frontend with
  the popup enabled.
- **Translations**: when adding/changing translatable strings, regenerate `languages/*.pot` and
  keep the `.po`/`.mo` pairs in sync (see "Translations" section below) — there is no `msgfmt`/
  `msginit` on this system, so `.mo` files were hand-compiled with a custom pure-PHP PO→MO
  compiler when system gettext tools weren't available.

## Architecture

Everything funnels through one singleton class, `YES_Age_Verification`
(`yes-age-verification.php:106`), instantiated on `plugins_loaded`. Reading the class docblock
(lines 26-105) first is the fastest way to understand the available filters/actions and the
multilingual design — it documents the full developer-hook surface
(`yes_age_verification_*` filters and actions) that external code is expected to use for
customization, so prefer extending behavior via those hooks rather than editing core methods.

### Data flow

1. **Options**: a single array stored under the `yes_age_verification_options` option, merged
   with `defaults()` (`yes-age-verification.php:347`) and filterable via
   `yes_age_verification_options`. Sanitized on save by `sanitize_options()`
   (`yes-age-verification.php:414`), which is the canonical place new settings fields must be
   both whitelisted and cleaned.
2. **Visibility decision**: `is_active()` (`yes-age-verification.php:520`) — checks the enabled
   flag, admin/AJAX context, excluded URLs, then `matches_target_rules()`
   (`yes-age-verification.php:563`) combined with the `mode` setting (`exclusion` vs
   `inclusion`). Targeting supports pages, categories, custom post types, arbitrary
   taxonomy:term pairs, URL regex patterns, and WooCommerce product categories — each resolved
   through `term_matches()` (`yes-age-verification.php:651`) for hierarchical (ancestor-aware)
   matching.
3. **Asset loading**: `frontend_enqueue()` (`yes-age-verification.php:706`) only runs when
   `is_active()`. CSS is generated inline as a string by `get_css()` (no stylesheet file, no
   extra HTTP request) and JS config is inlined as `window.yesAgeVerificationConfig` followed by
   the contents of `assets/age-verify.js` — also inlined, not enqueued as a separate file, to
   avoid render-blocking requests.
4. **Rendering**: `render_popup()` (`yes-age-verification.php:738`) hooks `wp_footer` and prints
   the modal markup, running every text field through both the
   `yes_age_verification_popup_*` filters and translation (see below).

### Frontend behavior (`assets/age-verify.js`)

Vanilla JS, no dependencies. Reads `window.yesAgeVerificationConfig` (cookie name, expiry days,
redirect URL), checks for the existing cookie, and if absent: shows the overlay, traps focus
inside the modal (basic focus-trap + `Escape`-to-redirect), and sets a first-party
`SameSite=Lax` cookie when the visitor confirms their age. "No" and `Escape` redirect to the
configured URL.

### Admin UI (`admin/settings.php`, `admin/admin.js`)

Standard WordPress Settings API page (`register_setting`/`settings_fields`/`do_settings_*`
pattern) rendered from a template file required by `settings_page()`. `admin/admin.js` wires up
the `wp.media` frame for the logo picker. WooCommerce-specific UI (enhanced selects, product
category targeting) is conditionally enqueued only `if ( class_exists( 'WooCommerce' ) )`.

### Multilingual support (WPML & Polylang)

This is the trickiest part of the codebase — read `yes-age-verification.php:86-104` (class
docblock) and the "Multilingual support" section (lines ~158-284) before touching any
translatable content or targeting logic. The design deliberately keeps **settings single-language**
(saved once, in the site's default language) rather than adding per-language duplicate fields:

- Popup text fields (title, body, question, button labels, footer, redirect URL) are registered
  as translatable strings with WPML String Translation (`wpml_register_single_string`) and
  Polylang (`pll_register_string`) under the `yes-age-verification` context — see
  `translatable_strings()` (`yes-age-verification.php:169`) for the canonical list/mapping. Site
  owners translate them through the multilingual plugin's own UI, **not** this plugin's settings
  page.
- At render time, `translate_string()` / `translate_popup_options()` resolve these to the
  visitor's current language via `wpml_translate_single_string` / `pll__`.
- Targeting IDs (page IDs, category/taxonomy/WooCommerce term IDs) are resolved to their
  translated equivalents via `localize_post_id()` / `localize_term_id()`
  (`wpml_object_id`, `pll_get_post`, `pll_get_term`) before rule matching, so a rule configured
  against a default-language page/term also matches its translations.
- **If you add a new translatable popup field**: add it to `translatable_strings()` — that one
  array drives registration, translation, and (combined with `sanitize_options()`) the whole
  pipeline; do not write separate registration/translation code per field.

### Translations (`languages/`)

- Plugin headers: `Text Domain: yes-age-verification`, `Domain Path: /languages` — files follow
  the `yes-age-verification-{locale}.po/.mo` naming convention (Loco Translate / WP-core
  compatible). `yes-age-verification.pot` is the source template.
- Ships with: Greek (`el`, `el_GR`) plus 15 major-language translations added later — Spanish,
  French, German, Italian, Portuguese (Brazil), Dutch, Russian, Chinese (Simplified), Japanese,
  Turkish, Polish, Arabic, Swedish, Korean, Hindi (`es_ES`, `fr_FR`, `de_DE`, `it_IT`, `pt_BR`,
  `nl_NL`, `ru_RU`, `zh_CN`, `ja`, `tr_TR`, `pl_PL`, `ar`, `sv_SE`, `ko_KR`, `hi_IN`).
- `defaults_for_site()` (`yes-age-verification.php:296`) is distinct from `defaults()`: it loads
  and translates the *site's* locale `.mo` file directly via WordPress's `MO` class — used so
  that admin-side default text shown to a user (e.g. an admin browsing in their own locale) still
  reflects the site's configured language, not the admin's personal language preference.
- When regenerating `.mo` files without system `msgfmt`/`msginit` available, a `.mo` must satisfy
  WordPress's `MO::import_from_reader()` validation: `hash_addr` must equal the offset where the
  string blob begins (even when `hash_length` is 0) — `hash_addr - translations_lengths_addr ===
  total * 8` is checked explicitly. Always verify generated `.mo` files by loading them through
  WordPress's actual `MO` class (`wp-includes/pomo/mo.php`) and confirming `translate()` returns
  the expected strings, not just that the binary parses.

## Conventions

- WordPress Coding Standards throughout (tabs for indentation, Yoda-less but WP-style spacing,
  `phpcs:ignore` comments where WP sniffs are intentionally bypassed e.g. for inline
  scripts/styles with no version).
- All output is escaped at render time (`esc_html`, `esc_url`, `esc_attr`, `wp_kses_post` for
  rich-text fields); all input is sanitized in `sanitize_options()`.
- Uninstall cleanup lives in `uninstall.php`, which deletes the `yes_age_verification_options`
  option — keep this in sync with the option name used in `register_settings()` /
  `load_textdomain()` if it ever changes.
