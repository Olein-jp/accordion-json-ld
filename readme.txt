=== Accordion JSON-LD ===
Contributors: Koji Kuno
Tags: accordion, json-ld, faq, schema
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.2.0
License: GPL 2.0 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates FAQPage JSON-LD from the core Accordion block and outputs it in wp_head.

== Description ==

When you use the Accordion block as an FAQ, this plugin automatically generates FAQPage JSON-LD.
It outputs JSON-LD only when the “Output structured data (JSON-LD)” toggle in the block inspector is enabled.

== Installation ==

1. Upload and activate the plugin.
2. Insert the core Accordion block in a post.
3. Turn on JSON-LD output from the Accordion block inspector.

== Frequently Asked Questions ==

= JSON-LD is not output =

Please check the following:

* The page is a singular view.
* The Accordion block toggle is enabled.
* The theme calls wp_head().

== Changelog ==

= 0.2.0 =
* Translate readme.txt

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 0.2.0 =
* Add JSON-LD toggle in the block inspector.
* Add GitHub updater and release workflow.

= 0.1.0 =
* Initial release
