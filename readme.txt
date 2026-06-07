=== YES Age Verification ===
Contributors: yesagency
Tags: age verification, age gate, alcohol, age check, popup
Requires at least: 5.9
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, configurable age verification popup for websites selling age-restricted products.

== Description ==

YES Age Verification adds a configurable popup that asks visitors to confirm they are of legal age before accessing your website. Built with performance in mind — CSS is output as a tiny inline style (no extra HTTP request) and JavaScript is a small footer script with no dependencies and no render-blocking.

**Features**

* Fully configurable popup content — logo, title, body text, question, button labels, footer note
* Remembers verified visitors via a first-party cookie (configurable duration, 1–365 days)
* Redirects underage visitors to any URL you choose
* Exclude specific pages from showing the popup (e.g. Privacy Policy, Terms)
* Configurable overlay colour (hex, rgb, rgba)
* Zero PageSpeed impact — no render-blocking resources, no external dependencies
* Multilingual ready — ships with English and Greek (el_GR) translations
* Clean uninstall — removes all data on plugin deletion

== Installation ==

1. Upload the `yes-age-verification` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Age Verification** and configure the popup.

== Frequently Asked Questions ==

= Does this plugin affect PageSpeed scores? =

No. The popup CSS (~800 bytes) is output as an inline `<style>` tag — zero extra HTTP requests. The JavaScript is a small file loaded in the footer so it never blocks rendering.

= How does it remember returning visitors? =

It sets a first-party `SameSite=Lax` cookie in the visitor's browser when they click "Yes". You control the expiry (default 30 days).

= Can I exclude certain pages? =

Yes. Under **Settings → Age Verification → Exclude pages**, enter one full URL per line.

= Can I translate the popup content? =

The popup text (title, body, buttons, footer) is directly editable in the settings panel — no translation files needed for the popup itself. The admin interface is translation-ready and ships with a Greek (el_GR) translation.

= Will this affect my SEO or AI crawler indexing? =

No. The page itself is always rendered in full — the popup is just an overlay added in the browser, so the underlying content is identical for every visitor and fully crawlable. On top of that, the plugin recognizes major search-engine and AI-crawler user agents (Googlebot, Bingbot, GPTBot, ClaudeBot, PerplexityBot and others) and skips showing the overlay to them entirely. This check happens in the browser, not on the server, so it works safely even with full-page caching or a CDN in front of your site — every visitor still receives the exact same cached HTML.

== Screenshots ==

1. The age verification popup as seen by visitors.
2. The plugin settings panel in WordPress admin.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
