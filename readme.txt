=== Accordion JSON-LD ===
Contributors: Koji Kuno
Tags: accordion, json-ld, faq, schema
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL 2.0 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

コアのアコーディオンブロックから FAQPage の JSON-LD を生成し、wp_head に出力します。

== Description ==

アコーディオンブロックを FAQ として使う場合に、FAQPage の JSON-LD を自動生成します。
ブロックインスペクターの「構造化データ（JSON-LD）を出力する」トグルがオンのときのみ出力します。

== Installation ==

1. プラグインをアップロードし有効化します。
2. 投稿でコアのアコーディオンブロックを挿入します。
3. アコーディオンブロックのインスペクターで JSON-LD 出力をオンにします。

== Frequently Asked Questions ==

= JSON-LD が出力されません =

以下を確認してください。

* 対象ページが単一表示である
* アコーディオンブロックのトグルがオンになっている
* テーマで wp_head() が呼ばれている

== Changelog ==

= 0.1.0 =
* 初版

== Upgrade Notice ==

= 0.1.0 =
* 初版
