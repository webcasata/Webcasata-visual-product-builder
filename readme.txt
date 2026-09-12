=== Webcasata Visual Product Builder ===
Contributors: webcasata
Tags: woocommerce, product configurator, product customizer, visual builder, cake builder
Requires at least: 6.3
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn any WooCommerce product into a visual, layer-based customizer — build-your-own cakes, pizzas, gift boxes and more.

== Description ==

Webcasata Visual Product Builder lets a WooCommerce store owner attach a step-by-step visual customizer to any product. Customers pick options — weight, layers, flavour, toppings — from a two-column popup, and see their choices composited live on the right as PNG or SVG image layers, instead of filling out a plain options form.

Every option (and every existing WooCommerce attribute term) can have its own PNG or SVG image attached from the admin panel, so the live preview is built entirely from images you upload — no code, and no real-time AI image generation slowing down or destabilizing the checkout experience.

**Core features in this release:**

* Visual, step-by-step customizer builder in wp-admin
* PNG layer images or SVG fill-recolor per option
* Attach images directly to existing WooCommerce attribute terms
* Conditional logic — show or hide options based on earlier choices
* Your data stays yours: deactivating the plugin never touches your customizers, and uninstalling it keeps everything unless you explicitly opt in to deleting it

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/webcasata-visual-product-builder`, or install directly through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Make sure WooCommerce is installed and active — this plugin requires it.
4. Go to Customizers → Add New to build your first visual customizer.

== Frequently Asked Questions ==

= Does this delete my data if I deactivate the plugin? =

No. Deactivating a plugin never deletes anything in WordPress, and this plugin makes no exception — your customizers, uploaded layer images, and settings are all still there if you reactivate it later.

= What happens if I delete the plugin? =

By default, nothing is deleted — your data is kept in case you reinstall the plugin later. If you'd rather have everything permanently removed when you delete the plugin, go to Customizers → Settings and check "Delete all data on uninstall" first.

= Does this work without WooCommerce? =

No, WooCommerce must be installed and active. You'll see an admin notice if it isn't.

== Screenshots ==

1. The customizer builder screen in wp-admin.
2. A customer-facing product customizer popup.

== Changelog ==

= 1.0.0 =
* Initial release: customizer builder screen, custom post type, settings screen with opt-in uninstall data deletion.
