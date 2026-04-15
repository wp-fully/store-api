=== Store API – Flutter App ===
Contributors: store-api
Tags: woocommerce, api, flutter, mobile, rest, telr
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 6.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API متكامل لتطبيق Flutter — إدارة التصنيفات والمنتجات والطلبات والدفع مع WooCommerce.

== Description ==

Store API provides a secure REST API for WooCommerce mobile apps (Flutter).

**Key Features:**
* Categories management (merge, hide, rename)
* Products with overrides (custom price/name/desc/hide)
* Cart & Checkout
* Telr Payments
* API Key protection
* CORS enabled

**Update-Safe:** All settings stored in wp_options with `store_*` prefix. Updates preserve data automatically via DB version tracking.

== Installation ==

1. Upload to `/wp-content/plugins/store-api/`
2. Activate.
3. Go to Store API settings, set API key, configure categories.
4. Use namespace: `/wp-json/store/v1/` with `X-Store-Api-Key` header.

== Changelog ==
= 6.1.0 =
* Added per-category image source control (primary image or alternative URL for app responses).
* Improved Categories admin UX with search, clearer controls, and preview of returned API image.
* Added in-place plugin upgrade hook for smoother updates without delete/reinstall.

= 6.0.0 =
* Initial release with DB versioning.

== Upgrade Notes ==

* Settings preserved automatically.
* New version runs migrations if needed.
* Test API endpoints in Status tab.

== Frequently Asked Questions ==

**Settings lost on update?**
No - unique prefixes + activation defaults.

**Translation?**
Add .po/.mo to `/languages/`

